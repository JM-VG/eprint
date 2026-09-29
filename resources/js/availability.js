// Refreshes the availability banner from GET /api/availability every 15 seconds.
const INTERVAL_MS = 15_000;

function render(banner, locations) {
    for (const location of locations) {
        const item = banner.querySelector(`[data-location="${location.id}"]`);
        if (!item) {
            continue;
        }

        item.querySelector('[data-status]').textContent = location.open ? 'Open' : 'Closed';

        const dot = item.querySelector('[data-dot]');
        dot.classList.toggle('bg-green-400', location.open);
        dot.classList.toggle('bg-red-400', !location.open);
    }
}

export function startAvailabilityBanner() {
    const banner = document.getElementById('availability-banner');
    if (!banner?.dataset.endpoint) {
        return;
    }

    const refresh = () =>
        fetch(banner.dataset.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => data && render(banner, data.locations))
            .catch(() => {});

    setInterval(refresh, INTERVAL_MS);
}
