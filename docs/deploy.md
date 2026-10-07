# Deploying to Laravel Cloud

Stash runs on Laravel Cloud with one app environment, a Postgres database and one queue worker. Redis is not required in production: queue, cache and sessions use the database (the tables are created by the default migrations).

## 1. Create the application

1. In Laravel Cloud, create an application from the GitHub repository `Iodize13/stash`, branch `main`.
2. PHP version: **8.4** (what CI tests against).
3. Attach a **Postgres** database to the environment. Cloud injects the `DB_*` variables.

## 2. Environment variables

Cloud sets `APP_KEY`, `APP_ENV=production` and the database variables. Add:

```dotenv
APP_NAME=Stash
APP_URL=https://<your-domain>
APP_LOCALE=en

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

DEMO_LOGIN=true
# Ready articles of this account are copied into every "Try the demo" sandbox
SANDBOX_TEMPLATE_EMAIL=you@example.com
REPOSITORY_URL=https://github.com/Iodize13/stash
```

## 3. Build and deploy commands

The frontend uses Bun (there is no `package-lock.json`), so replace the default npm build step.

Build commands:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
curl -fsSL https://bun.sh/install | bash
~/.bun/bin/bun install --frozen-lockfile
~/.bun/bin/bun run build
```

Deploy commands:

```bash
php artisan migrate --force
php artisan optimize
```

## 4. Queue worker

Saved links are fetched by a queued job, so the environment needs a worker. Add a background process / queue worker:

```bash
php artisan queue:work --tries=3 --timeout=90 --sleep=3
```

Without it, saved links stay `queued`.

## 4b. Scheduler

Enable the scheduler on the app instance. It runs `model:prune` hourly to delete expired demo sandboxes (and their articles) and `activitylog:clean` daily. With scale-to-zero on, tasks run once the instance wakes.

## 5. First accounts and content

Production has no seeder. After the first deploy, run these from the Cloud command runner:

```bash
# Your account (prints a generated password)
php artisan stash:create-user you@example.com --name="Your Name"

# Optional: a read-only account that can browse everything, including the admin panel
php artisan stash:create-user viewer@example.com --name=Viewer --role=demo

# Optional: curated real articles, then highlights + a public collection once fetched
php artisan stash:demo-content you@example.com
php artisan stash:demo-content you@example.com --highlight
```

## 6. Check

- `/up` returns 200.
- `/` shows the landing page; "Try the demo" opens a fresh sandbox with the template's sample articles.
- Saving a link in `/library` goes from QUEUED to READY within a few seconds (the worker is running).
