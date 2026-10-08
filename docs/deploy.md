# Deploying

Production runs on a single-node k3s cluster on Hetzner, at https://stash.ionize13.com. Traffic reaches it through a Cloudflare Tunnel ingress, so the server exposes no public ports. The Kubernetes manifests live in the homelab repository ([`Iodize13/homelab`](https://github.com/Iodize13/homelab), `apps/stash/`); this repository builds the image.

```
push to main ──► CI: pint + pest ──► CI: build & push ghcr.io/iodize13/stash:{latest,<sha>}
                                                │
kubectl rollout restart ◄───────────────────────┘ (manual)

Cloudflare Tunnel ──► Ingress ──► stash-web (FrankenPHP, migrate in initContainer)
                                   stash-worker (queue:work)       ─┐
                                   stash-scheduler (schedule:work) ─┼─► stash-cluster (CloudNativePG Postgres)
```

Redis is not used in production: queue, cache and sessions use the database (the tables come from the default migrations).

## The image

The root `Dockerfile` is multi-stage:

1. **vendor**: `composer install --no-dev` on the FrankenPHP base (PHP 8.4 with `pdo_pgsql`, `intl`, `zip`, `bcmath`, `pcntl`, `opcache`).
2. **assets**: `bun install --frozen-lockfile && bun run build` (Tailwind scans some vendor views, so `vendor/` is copied in first).
3. **runtime**: app code, `vendor/`, `public/build`, Filament's published assets; runs as UID `10001` and listens on `8080`.

The entrypoint runs `php artisan optimize` at container start, so configuration is cached with the real environment variables from Kubernetes, not at build time.

The same image runs every process; Kubernetes chooses the command:

| Deployment | Command |
|---|---|
| `stash-web` | `frankenphp php-server --listen :8080 --root public/` (default) |
| `stash-worker` | `php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-jobs=500 --memory=128` |
| `stash-scheduler` | `php artisan schedule:work` (hourly `model:prune` of expired demo sandboxes, daily `activitylog:clean`) |

CI builds and pushes `ghcr.io/iodize13/stash:latest` and `:<commit sha>` only after the test job passes on `main`. The package is public, so the cluster pulls it without credentials.

Build and run it locally:

```bash
docker build -t stash:local .
docker run --rm -p 8080:8080 -e APP_KEY=base64:... -e DB_HOST=... stash:local
```

## Kubernetes resources (`apps/stash/` in the homelab repo)

| File | What it is |
|---|---|
| `namespace.yaml` | Namespace `stash` |
| `cnpg-cluster.yaml` | CloudNativePG `stash-cluster` (1 instance, 2Gi, database and owner `stash`). CNPG generates the `stash-cluster-app` secret that the pods read `DB_*` from. |
| `secret.sops.yaml` | `APP_KEY`, encrypted with SOPS + age |
| `configmap.yaml` | Non-secret environment (`APP_URL`, drivers, `DEMO_LOGIN`, `SANDBOX_TEMPLATE_EMAIL`, …) |
| `deployment.yaml` | `stash-web` (with a `migrate --force` initContainer), `stash-worker`, `stash-scheduler`, all non-root with memory limits |
| `service.yaml`, `ingress.yaml` | Service on port 80 → 8080, ingress class `cloudflare-tunnel` for `stash.ionize13.com` |

The tunnel ingress controller creates the DNS record for the ingress host by itself; do not add a manual record with the same name.

### Environment

```dotenv
APP_NAME=Stash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://stash.ionize13.com
APP_LOCALE=en
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
DEMO_LOGIN=true
# Ready articles of this account are copied into every "Try the demo" sandbox
SANDBOX_TEMPLATE_EMAIL=you@example.com
REPOSITORY_URL=https://github.com/Iodize13/stash
```

`APP_KEY` comes from `secret.sops.yaml`; `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` come from `stash-cluster-app`.

## First deploy

From the homelab repository, with `SOPS_AGE_KEY_FILE` pointing at the age key:

```bash
kubectl apply -f apps/stash/namespace.yaml -f apps/stash/cnpg-cluster.yaml
kubectl wait --for=condition=Ready clusters.postgresql.cnpg.io/stash-cluster -n stash --timeout=300s

sops -d apps/stash/secret.sops.yaml | kubectl apply -f -
kubectl apply -f apps/stash/configmap.yaml -f apps/stash/deployment.yaml \
              -f apps/stash/service.yaml -f apps/stash/ingress.yaml
```

To create a new `APP_KEY`: `echo "base64:$(head -c 32 /dev/urandom | base64)"`, then `sops apps/stash/secret.sops.yaml`.

### First accounts and content

Production has no seeder (`DatabaseSeeder` refuses to run there). Run the commands inside the web pod:

```bash
# Your account (prints a generated password)
kubectl exec -n stash deploy/stash-web -- php artisan stash:create-user you@example.com --name="Your Name"

# Optional: a read-only account that can browse everything, including the admin panel
kubectl exec -n stash deploy/stash-web -- php artisan stash:create-user viewer@example.com --name=Viewer --role=demo

# Optional: curated real articles, then highlights + a public collection once the worker has fetched them
kubectl exec -n stash deploy/stash-web -- php artisan stash:demo-content you@example.com
kubectl exec -n stash deploy/stash-web -- php artisan stash:demo-content you@example.com --highlight
```

## Releasing a new version

1. Push to `main` and wait for both CI jobs (`test`, `image`) to pass.
2. Roll out the new `:latest` image (the pods use `imagePullPolicy: Always`). The web pod runs pending migrations in its initContainer before it starts serving.

   ```bash
   kubectl rollout restart deploy/stash-web deploy/stash-worker deploy/stash-scheduler -n stash
   kubectl rollout status deploy/stash-web -n stash
   ```

To roll back, point the Deployments at a previous `:<commit sha>` tag instead of `latest`.

## Check

- `curl https://stash.ionize13.com/up` returns 200.
- `kubectl get pods -n stash` shows the web, worker, scheduler and `stash-cluster-1` pods running.
- `/` shows the landing page; "Try the demo" opens a fresh sandbox with the template's sample articles.
- Saving a link in `/library` goes from QUEUED to READY within a few seconds (`kubectl logs -n stash deploy/stash-worker` shows `FetchArticle … DONE`).

## Operating notes

- **Logs**: everything goes to stderr: `kubectl logs -n stash deploy/stash-web` (or `stash-worker`, `stash-scheduler`).
- **Shell / artisan**: `kubectl exec -it -n stash deploy/stash-web -- php artisan tinker`.
- **SSRF and the cluster**: the fetcher only connects to public IPs, so saved links can never reach in-cluster addresses (pod and service IPs are private ranges).
- **Resources**: web 128–384Mi, worker 96–256Mi, scheduler 64–192Mi, Postgres 128–512Mi.
