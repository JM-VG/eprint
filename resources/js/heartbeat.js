// Keeps a staff station open: POST /api/heartbeat every 30 seconds while a staff page is open.
const INTERVAL_MS = 30_000;

export function startHeartbeat() {
    const meta = document.querySelector('meta[name="eprint-heartbeat"]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!meta || !token) {
        return;
    }

    const beat = () =>
        fetch(meta.content, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            credentials: 'same-origin',
        }).catch(() => {});

    beat();
    setInterval(beat, INTERVAL_MS);
}
