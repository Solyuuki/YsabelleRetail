const currency = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const renderCart = (root, lines) => {
    const body = root.querySelector('[data-pos-cart]');
    const totalNode = root.querySelector('[data-pos-total]');
    const hidden = root.querySelector('[name="lines_json"]');

    if (!body || !totalNode || !hidden) {
        return;
    }

    if (lines.length === 0) {
        body.innerHTML = '<div class="ys-admin-empty-panel">Search products and add them to begin a walk-in sale.</div>';
        totalNode.textContent = '0.00';
        hidden.value = '[]';
        return;
    }

    body.innerHTML = lines.map((line, index) => `
        <div class="ys-admin-cart-row">
            <div>
                <p class="ys-admin-cart-title">${line.name}</p>
                <p class="ys-admin-cart-meta">${line.variant_name} / ${line.sku}</p>
            </div>
            <div class="ys-admin-cart-actions">
                <input type="number" min="1" max="${line.available_quantity}" value="${line.quantity}" data-pos-quantity="${index}" class="ys-admin-qty-input">
                <button type="button" class="ys-admin-link-danger" data-pos-remove="${index}">Remove</button>
            </div>
            <div class="ys-admin-cart-price">${currency.format(line.quantity * line.price)}</div>
        </div>
    `).join('');

    const total = lines.reduce((sum, line) => sum + (line.quantity * line.price), 0);
    totalNode.textContent = currency.format(total);
    hidden.value = JSON.stringify(lines.map(({ id, quantity }) => ({
        variant_id: id,
        quantity,
    })));
};

export const initAdminPos = () => {
    const root = document.querySelector('[data-admin-pos]');

    if (!root) {
        return;
    }

    const endpoint = root.dataset.searchEndpoint;
    const results = root.querySelector('[data-pos-results]');
    const search = root.querySelector('[data-pos-search]');
    const lines = [];
    const oldLines = JSON.parse(root.dataset.oldLines || '[]');

    const refresh = () => renderCart(root, lines);

    const addLine = (item) => {
        if (item.available_quantity < 1) {
            return;
        }

        const existing = lines.find((line) => line.id === item.id);

        if (existing) {
            existing.quantity = Math.min(existing.quantity + 1, existing.available_quantity);
        } else {
            lines.push({ ...item, quantity: 1 });
        }

        refresh();
    };

    const loadResults = async () => {
        const term = search?.value?.trim() || '';
        const response = await fetch(`${endpoint}?search=${encodeURIComponent(term)}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        const payload = await response.json();
        const items = payload.data || [];

        if (!results) {
            return;
        }

        if (items.length === 0) {
            results.innerHTML = '<div class="ys-admin-empty-panel">No matching products found.</div>';
            return;
        }

        results.innerHTML = items.map((item) => `
            <button type="button" class="ys-admin-search-card" data-pos-add="${item.id}" ${item.available_quantity < 1 ? 'disabled' : ''}>
                <div>
                    <p class="ys-admin-search-title">${item.name}</p>
                    <p class="ys-admin-search-meta">${item.variant_name} / ${item.sku}</p>
                </div>
                <div class="ys-admin-search-side">
                    <span>PHP ${currency.format(item.price)}</span>
                    <span>${item.available_quantity < 1 ? 'Out of stock' : `${item.available_quantity} in stock`}</span>
                </div>
            </button>
        `).join('');

        results.querySelectorAll('[data-pos-add]').forEach((button) => {
            button.addEventListener('click', () => {
                const item = items.find((entry) => entry.id === Number(button.dataset.posAdd));

                if (item) {
                    addLine(item);
                }
            });
        });
    };

    search?.addEventListener('input', () => {
        window.clearTimeout(search.dataset.timeoutId);
        const timeoutId = window.setTimeout(loadResults, 180);
        search.dataset.timeoutId = String(timeoutId);
    });

    root.addEventListener('input', (event) => {
        const field = event.target.closest('[data-pos-quantity]');

        if (!field) {
            return;
        }

        const line = lines[Number(field.dataset.posQuantity)];

        if (!line) {
            return;
        }

        line.quantity = Math.max(1, Math.min(Number(field.value || 1), line.available_quantity));
        refresh();
    });

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-pos-remove]');

        if (!button) {
            return;
        }

        lines.splice(Number(button.dataset.posRemove), 1);
        refresh();
    });

    oldLines.forEach((line) => {
        if (line && typeof line.id === 'number') {
            lines.push(line);
        }
    });

    refresh();
    loadResults();
};
