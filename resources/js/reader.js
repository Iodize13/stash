/**
 * Reader highlights, anchored with W3C-style text-quote selectors
 * ({ exact, prefix, suffix }) against the plain text of the article body.
 */
const CONTEXT = 32;

/** Every text node under root, with its [start, end) offset in the joined text. */
function textIndex(root) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let text = '';

    while (walker.nextNode()) {
        const node = walker.currentNode;
        nodes.push({ node, start: text.length, end: text.length + node.data.length });
        text += node.data;
    }

    return { nodes, text };
}

/** Start offset of the quote, preferring the occurrence whose context matches best. */
function locate(text, { exact, prefix, suffix }) {
    const full = text.indexOf(prefix + exact + suffix);
    if (full !== -1) return full + prefix.length;

    let best = -1;
    let bestScore = -1;
    for (let at = text.indexOf(exact); at !== -1; at = text.indexOf(exact, at + 1)) {
        const score = sharedSuffix(text.slice(Math.max(0, at - prefix.length), at), prefix)
            + sharedPrefix(text.slice(at + exact.length, at + exact.length + suffix.length), suffix);
        if (score > bestScore) [best, bestScore] = [at, score];
    }

    return best;
}

function sharedSuffix(a, b) {
    let n = 0;
    while (n < a.length && n < b.length && a[a.length - 1 - n] === b[b.length - 1 - n]) n++;
    return n;
}

function sharedPrefix(a, b) {
    let n = 0;
    while (n < a.length && n < b.length && a[n] === b[n]) n++;
    return n;
}

/** Wrap [start, end) in <mark> elements, one per text node it spans. */
function wrap(root, start, end, anchor) {
    const { nodes } = textIndex(root);

    for (const { node, start: nodeStart, end: nodeEnd } of nodes) {
        if (nodeEnd <= start || nodeStart >= end) continue;

        let target = node;
        const from = Math.max(start, nodeStart) - nodeStart;
        const to = Math.min(end, nodeEnd) - nodeStart;
        if (to < target.data.length) target.splitText(to);
        if (from > 0) target = target.splitText(from);
        if (!target.data.trim()) continue;

        const mark = document.createElement('mark');
        mark.className = `hl hl-${anchor.color}`;
        mark.dataset.highlightId = anchor.id;
        target.parentNode.insertBefore(mark, target);
        mark.appendChild(target);
    }
}

function clearMarks(root) {
    root.querySelectorAll('mark[data-highlight-id]').forEach((mark) => {
        mark.replaceWith(...mark.childNodes);
    });
    root.normalize();
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('reader', ({ anchors, canEdit }) => ({
        anchors,
        canEdit,
        missing: [],
        selection: null,
        popover: { top: 0, left: 0 },
        color: 'amber',
        note: '',
        tags: '',
        progress: 0,
        size: 18,
        family: 'sans',
        notesOpen: true,
        filter: null,

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem('reader-prefs') || '{}');
                this.size = saved.size ?? this.size;
                this.family = saved.family ?? this.family;
            } catch {}

            this.$watch('size', () => this.savePrefs());
            this.$watch('family', () => this.savePrefs());
            this.render();
            this.onScroll();
        },

        savePrefs() {
            try {
                localStorage.setItem('reader-prefs', JSON.stringify({ size: this.size, family: this.family }));
            } catch {}
        },

        render() {
            const body = this.$refs.body;
            if (!body) return;

            clearMarks(body);
            this.missing = [];

            for (const anchor of this.anchors) {
                const { text } = textIndex(body);
                const start = locate(text, anchor);
                if (start === -1) {
                    this.missing.push(anchor.id);
                    continue;
                }
                wrap(body, start, start + anchor.exact.length, anchor);
            }
        },

        refresh(anchors) {
            this.anchors = anchors;
            this.render();
        },

        capture() {
            if (!this.canEdit) return;

            const sel = window.getSelection();
            const body = this.$refs.body;
            if (!sel || sel.isCollapsed || !body.contains(sel.anchorNode) || !body.contains(sel.focusNode)) {
                return;
            }

            const range = sel.getRangeAt(0);
            const exact = range.toString();
            if (!exact.trim()) return;

            const before = document.createRange();
            before.setStart(body, 0);
            before.setEnd(range.startContainer, range.startOffset);
            const start = before.toString().length;
            const { text } = textIndex(body);

            this.selection = {
                exact,
                prefix: text.slice(Math.max(0, start - CONTEXT), start),
                suffix: text.slice(start + exact.length, start + exact.length + CONTEXT),
            };

            const rect = range.getBoundingClientRect();
            const box = this.$refs.article.getBoundingClientRect();
            this.popover = {
                top: rect.bottom - box.top + 8,
                left: Math.max(0, Math.min(rect.left - box.left, box.width - 340)),
            };
        },

        cancel() {
            this.selection = null;
            this.note = '';
            this.tags = '';
            window.getSelection()?.removeAllRanges();
        },

        async save(color = this.color) {
            if (!this.selection) return;

            const { exact, prefix, suffix } = this.selection;
            await this.$wire.addHighlight(exact, prefix, suffix, color, this.note, this.tags);
            this.cancel();
        },

        copySelection() {
            if (this.selection) navigator.clipboard.writeText(this.selection.exact);
        },

        jump(id) {
            const mark = this.$refs.body.querySelector(`mark[data-highlight-id="${id}"]`);
            if (!mark) return;

            mark.scrollIntoView({ behavior: 'smooth', block: 'center' });
            mark.classList.add('hl-flash');
            setTimeout(() => mark.classList.remove('hl-flash'), 1200);
        },

        onScroll() {
            const article = this.$refs.article;
            if (!article) return;

            const rect = article.getBoundingClientRect();
            const scrollable = rect.height - window.innerHeight;
            this.progress = scrollable <= 0
                ? 100
                : Math.round(Math.min(1, Math.max(0, -rect.top / scrollable)) * 100);
        },
    }));
});
