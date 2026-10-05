// Keeps the availability banner live: subscribes to /public/availability in the
// Realtime Database, and falls back to GET /api/availability every 15 seconds
// when streaming is unavailable.
import { subscribe } from './realtime';

const INTERVAL_MS = 15_000;

function render(banner, locations) {
    for (const location of locations) {
        const item = location && banner.querySelector(`[data-location="${location.id}"]`);
        if (!item) {
            continue;
        }

        item.querySelector('[data-status]').textContent = location.open ? 'Open' : 'Closed';

        const dot = item.querySelector('[data-dot]');
        dot.classList.toggle('bg-green-400', location.open);
        dot.classList.toggle('bg-red-400', !location.open);
    }
}

function poll(banner, refreshNow) {
    const refresh = () =>
        fetch(banner.dataset.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => data && render(banner, data.locations))
            .catch(() => {});

    if (refreshNow) {
        refresh();
    }
    setInterval(refresh, INTERVAL_MS);
}

export function startAvailabilityBanner() {
    const banner = document.getElementById('availability-banner');
    if (!banner?.dataset.endpoint) {
        return;
    }

    if (!banner.dataset.realtimeUrl) {
        poll(banner, false);
        return;
    }

    subscribe(
        banner.dataset.realtimeUrl,
        (value) => render(banner, Object.values(value?.locations ?? {})),
        () => poll(banner, true),
    );
}
