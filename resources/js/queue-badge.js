// Keeps the sidebar Queue badge live: subscribes to /public/queue in the Realtime
// Database for the staff member's station, and falls back to GET /api/queue-count
// every 15 seconds when streaming is unavailable.
import { subscribe } from './realtime';

const INTERVAL_MS = 15_000;

function show(badge, pending) {
    badge.textContent = String(pending);
    badge.hidden = pending === 0;
}

function poll(badge) {
    const refresh = () =>
        fetch(badge.dataset.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => data && show(badge, data.pending))
            .catch(() => {});

    refresh();
    setInterval(refresh, INTERVAL_MS);
}

export function startQueueBadge() {
    const badge = document.querySelector('[data-queue-badge]');
    if (!badge?.dataset.endpoint) {
        return;
    }

    const locationId = badge.dataset.locationId;

    if (!badge.dataset.realtimeUrl || !locationId) {
        poll(badge);
        return;
    }

    subscribe(
        badge.dataset.realtimeUrl,
        (value) => show(badge, Number(value?.pending?.[locationId] ?? 0)),
        () => poll(badge),
    );
}
