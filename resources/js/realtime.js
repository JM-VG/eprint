// Live values from the Firebase Realtime Database over its REST streaming API
// (Server-Sent Events), so no Firebase SDK is needed. Only /public is
// readable from the browser (database.rules.json).
//
// subscribe(url, onValue, onUnavailable): onValue gets the whole value at the
// URL every time it changes; onUnavailable is called once if streaming is not
// possible (old browser, rules not deployed, offline) so callers can poll.

function setIn(target, path, value) {
    const keys = path.split('/').filter(Boolean);

    if (keys.length === 0) {
        return value;
    }

    const root = target !== null && typeof target === 'object' ? target : {};
    let node = root;

    keys.slice(0, -1).forEach((key) => {
        if (node[key] === null || typeof node[key] !== 'object') {
            node[key] = {};
        }
        node = node[key];
    });

    const last = keys[keys.length - 1];
    if (value === null) {
        delete node[last];
    } else {
        node[last] = value;
    }

    return root;
}

export function subscribe(url, onValue, onUnavailable) {
    if (!url || typeof window.EventSource !== 'function') {
        onUnavailable();
        return null;
    }

    let value = null;
    let failed = false;
    const source = new EventSource(url);

    const fail = () => {
        if (!failed) {
            failed = true;
            source.close();
            onUnavailable();
        }
    };

    const apply = (event, merge) => {
        try {
            const { path, data } = JSON.parse(event.data);

            if (merge) {
                Object.entries(data ?? {}).forEach(([key, child]) => {
                    value = setIn(value, `${path}/${key}`, child);
                });
            } else {
                value = setIn(value, path, data);
            }

            onValue(value);
        } catch {
            // Ignore malformed events; the next one carries the full state again.
        }
    };

    source.addEventListener('put', (event) => apply(event, false));
    source.addEventListener('patch', (event) => apply(event, true));
    source.addEventListener('cancel', fail);
    source.addEventListener('auth_revoked', fail);
    source.addEventListener('error', () => {
        // The browser retries dropped connections itself; a refused one is closed.
        if (source.readyState === EventSource.CLOSED) {
            fail();
        }
    });

    return source;
}
