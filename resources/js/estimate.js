// Shows the price estimate on job forms by POSTing the form to /api/price-estimate.
export function startPriceEstimate() {
    const form = document.querySelector('[data-estimate-form]');
    const output = form?.querySelector('[data-estimate]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!form || !output || !token) {
        return;
    }

    let timer;

    const show = (text) => {
        output.textContent = text;
    };

    const refresh = () => {
        const data = new FormData(form);
        const file = data.get('file');
        if (file instanceof File && file.size === 0) {
            data.delete('file');
        }

        fetch(form.dataset.estimateUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
            credentials: 'same-origin',
            body: data,
        })
            .then(async (response) => {
                const body = await response.json();
                if (!response.ok) {
                    const errors = Object.values(body.errors ?? {}).flat();
                    show(errors[0] ?? 'Could not estimate the price.');
                    return;
                }

                if (body.total === null) {
                    show(body.note ?? 'The final price is confirmed at the counter.');
                    return;
                }

                const pages = body.pages === null ? '' : `${body.pages} page(s), `;
                show(`Estimated total: ₱${body.total} (${pages}₱${body.unit_price} each)`);
            })
            .catch(() => show('Could not estimate the price.'));
    };

    form.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 300);
    });
}
