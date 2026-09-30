// Walk-in sale form: add/remove line items, prefill each amount from the price list
// (unit price x quantity) until staff edit it, and keep the total up to date.
export function startWalkinForm() {
    const form = document.querySelector('[data-walkin-form]');
    if (!form) {
        return;
    }

    const body = form.querySelector('[data-walkin-rows]');
    const totalEl = form.querySelector('[data-walkin-total]');
    const template = body.querySelector('[data-walkin-row]').cloneNode(true);

    const field = (row, name) => row.querySelector(`[data-field="${name}"]`);

    const renumber = () => {
        body.querySelectorAll('[data-walkin-row]').forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach((input) => {
                input.name = `items[${index}][${input.dataset.field}]`;
            });
        });
    };

    const updateTotal = () => {
        let cents = 0;
        body.querySelectorAll('[data-field="amount"]').forEach((input) => {
            cents += Math.round((parseFloat(input.value) || 0) * 100);
        });
        totalEl.textContent = (cents / 100).toFixed(2);
    };

    const prefill = (row) => {
        const amount = field(row, 'amount');
        if (amount.dataset.edited === '1') {
            return;
        }
        const option = field(row, 'service_id').selectedOptions[0];
        const price = parseFloat(option?.dataset.price ?? '');
        const quantity = parseInt(field(row, 'quantity').value, 10) || 0;
        if (!Number.isNaN(price)) {
            amount.value = ((Math.round(price * 100) * quantity) / 100).toFixed(2);
        }
    };

    body.addEventListener('input', (event) => {
        const row = event.target.closest('[data-walkin-row]');
        if (event.target.dataset.field === 'amount') {
            event.target.dataset.edited = '1';
        } else if (row) {
            prefill(row);
        }
        updateTotal();
    });

    body.addEventListener('change', (event) => {
        const row = event.target.closest('[data-walkin-row]');
        if (row && event.target.dataset.field === 'service_id') {
            prefill(row);
            updateTotal();
        }
    });

    body.addEventListener('click', (event) => {
        if (!event.target.matches('[data-walkin-remove]')) {
            return;
        }
        if (body.querySelectorAll('[data-walkin-row]').length > 1) {
            event.target.closest('[data-walkin-row]').remove();
            renumber();
            updateTotal();
        }
    });

    form.querySelector('[data-walkin-add]').addEventListener('click', () => {
        const row = template.cloneNode(true);
        row.querySelectorAll('ul').forEach((errors) => errors.remove());
        field(row, 'service_id').value = '';
        field(row, 'quantity').value = '1';
        field(row, 'sheets_used').value = '1';
        field(row, 'amount').value = '';
        delete field(row, 'amount').dataset.edited;
        body.appendChild(row);
        renumber();
    });

    updateTotal();
}
