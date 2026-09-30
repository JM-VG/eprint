// Updates the sidebar Queue badge from GET /api/queue-count every 15 seconds.
const INTERVAL_MS = 15_000;

export function startQueueBadge() {
    const badge = document.querySelector('[data-queue-badge]');
    if (!badge?.dataset.endpoint) {
        return;
    }

    const refresh = () =>
        fetch(badge.dataset.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data) {
                    return;
                }
                badge.textContent = String(data.pending);
                badge.hidden = data.pending === 0;
            })
            .catch(() => {});

    refresh();
    setInterval(refresh, INTERVAL_MS);
}
