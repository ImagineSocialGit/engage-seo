{{-- Local-only development aid; the public layout guards this include. --}}
<script>
    (() => {
        'use strict';

        const entries = {{ Illuminate\Support\Js::from($sourceLocations) }};
        const locations = new Map();

        for (const entry of entries) {
            const text = normalize(entry.text);

            if (!text) continue;

            if (!locations.has(text)) locations.set(text, []);
            locations.get(text).push(entry);
        }

        const selectors = [
            'header a', 'header button',
            'main h1', 'main h2', 'main h3', 'main h4',
            'main p', 'main a', 'main button', 'main label',
            'main summary', 'main dt', 'main dd',
            'footer a', 'footer p', 'footer h2', 'footer h3',
        ].join(', ');

        for (const element of document.querySelectorAll(selectors)) {
            const value = normalize(element.textContent);
            const matches = locations.get(value);

            if (!matches || value.length > 2048) continue;

            const scope = element.closest('header, footer') ? 'site' : 'page';
            const preferred = matches.filter((entry) => entry.scope === scope);
            const candidateSources = (preferred.length ? preferred : matches)
                .map((entry) => entry.source);
            const uniqueSources = [...new Set(candidateSources)];

            if (!uniqueSources.length) continue;

            element.title = uniqueSources.join('\n');
            element.dataset.seoSourceTooltip = 'true';
        }

        function normalize(value) {
            return typeof value === 'string'
                ? value.replace(/\s+/g, ' ').trim()
                : '';
        }
    })();
</script>
<style>
    [data-seo-source-tooltip="true"] { text-decoration: underline dotted transparent; text-underline-offset: .18em; }
    [data-seo-source-tooltip="true"]:hover { text-decoration-color: var(--site-color-primary, currentColor); }
</style>