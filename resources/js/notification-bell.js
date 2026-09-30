// Polls for new notifications: updates the bell's unread count and pops up each new one once.
const INTERVAL_MS = 15_000;
const SEEN_KEY = 'eprint:toasted';

function readSeen() {
    try {
        return new Set(JSON.parse(localStorage.getItem(SEEN_KEY) ?? '[]'));
    } catch {
        return new Set();
    }
}

function remember(seen) {
    try {
        localStorage.setItem(SEEN_KEY, JSON.stringify([...seen].slice(-50)));
    } catch {
        // Storage blocked: pop-ups may repeat on the next page, which is harmless.
    }
}

export function startNotificationBell() {
    const badge = document.querySelector('[data-notification-bell]');
    const endpoint = badge?.dataset.recentEndpoint;
    if (!endpoint) {
        return;
    }

    const refresh = () =>
        fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data) {
                    return;
                }
                badge.textContent = String(data.unread);
                badge.hidden = data.unread === 0;

                const seen = readSeen();
                for (const item of data.recent) {
                    if (!seen.has(item.id)) {
                        seen.add(item.id);
                        window.dispatchEvent(new CustomEvent('eprint-toast', { detail: item }));
                    }
                }
                remember(seen);
            })
            .catch(() => {});

    refresh();
    setInterval(refresh, INTERVAL_MS);
}
