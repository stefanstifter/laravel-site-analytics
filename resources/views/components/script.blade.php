{{--
    Click tracking. Put it once in the layout of the pages you track, e.g. before </body>.
    Counts clicks on links and buttons inside data-track-group="…", on elements with data-track or data-track="Label",
    and (with clicks.outbound) on every link that leaves the site. data-track="false" leaves an element or container out.
    Pass a CSP nonce with nonce="…"; Vite's nonce is used when set.
--}}
@php
    $render = config('site-analytics.enabled')
        && config('site-analytics.clicks.enabled', true)
        && Route::has(\Stifter\SiteAnalytics\SiteAnalyticsServiceProvider::CLICK_ROUTE)
        && ! (config('site-analytics.ignore_authenticated') && \Illuminate\Support\Facades\Auth::check());
    $nonce = null;

    if ($render && ! $attributes->has('nonce')) {
        try {
            $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
        } catch (\Throwable) {
            // No Vite in this app.
        }
    }
@endphp
@if($render)
<script {{ $attributes->merge(array_filter(['nonce' => $nonce, 'data-site-analytics' => 'clicks'])) }}>
(() => {
    if (window.siteAnalyticsClicks) return;
    window.siteAnalyticsClicks = true;

    const endpoint = @js(route(\Stifter\SiteAnalytics\SiteAnalyticsServiceProvider::CLICK_ROUTE, [], false));
    const outbound = @js((bool) config('site-analytics.clicks.outbound', true));
    const hidden = '.sr-only, .visually-hidden, [aria-hidden="true"], svg, script, style, template';
    const clean = (text, length = 150) => (text || '').replace(/\s+/g, ' ').trim().slice(0, length);

    const labelOf = (element, url) => {
        if (element.dataset.track) return clean(element.dataset.track);
        const copy = element.cloneNode(true);
        copy.querySelectorAll(hidden).forEach((node) => node.remove());

        return clean(element.getAttribute('aria-label'))
            || clean(copy.textContent)
            || clean(element.getAttribute('title'))
            || clean(element.querySelector('img[alt]')?.alt)
            || (url ? clean(url.host ? url.host + url.pathname : url.href) : '');
    };

    const track = (event) => {
        if (event.type === 'auxclick' && event.button !== 1) return;

        const element = event.target instanceof Element ? event.target.closest('a[href], button, [data-track]') : null;
        if (!element || element.closest('[data-track="false"]')) return;

        const group = clean(element.closest('[data-track-group]')?.dataset.trackGroup, 100);
        let url = null;
        try { url = element.matches('a[href]') ? new URL(element.href, location.href) : null; } catch {}
        const leaves = !!url && (/^(mailto|tel):$/.test(url.protocol) || (/^https?:$/.test(url.protocol) && url.host !== location.host));

        if (!('track' in element.dataset) && !group && !(outbound && leaves)) return;

        const label = labelOf(element, url);
        if (!label) return;

        const body = new FormData();
        body.append('label', label);
        body.append('page', location.pathname);
        if (group) body.append('group', group);
        if (url && /^(https?|mailto|tel):$/.test(url.protocol)) body.append('href', url.href.slice(0, 2048));

        if (!(navigator.sendBeacon && navigator.sendBeacon(endpoint, body))) {
            fetch(endpoint, { method: 'POST', body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
        }
    };

    document.addEventListener('click', track, { capture: true, passive: true });
    document.addEventListener('auxclick', track, { capture: true, passive: true });
})();
</script>
@endif
