// Pop-up stack for notifications announced by notification-bell.js.
export function toasts({ timeout = 9000 } = {}) {
    return {
        items: [],

        init() {
            window.addEventListener('eprint-toast', (event) => this.push(event.detail));
        },

        push(item) {
            this.items.push(item);
            setTimeout(() => this.dismiss(item.id), timeout);
        },

        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },

        async open(item) {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                await fetch(item.read_url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token ?? '', Accept: 'text/html' },
                    credentials: 'same-origin',
                    redirect: 'manual',
                });
            } finally {
                window.location.href = item.url ?? window.location.href;
            }
        },
    };
}
