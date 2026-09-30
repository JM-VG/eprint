// Refreshes the notification bell's unread count every 30 seconds.
const INTERVAL_MS = 30_000;

export function startNotificationBell() {
    const badge = document.querySelector('[data-notification-bell]');
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
                badge.textContent = String(data.unread);
                badge.hidden = data.unread === 0;
            })
            .catch(() => {});

    setInterval(refresh, INTERVAL_MS);
}
