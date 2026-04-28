const currencyFormatter = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const integerFormatter = new Intl.NumberFormat('en-PH', {
    maximumFractionDigits: 0,
});

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const formatValue = (value, format) => {
    if (format === 'currency') {
        return `PHP ${currencyFormatter.format(value)}`;
    }

    return integerFormatter.format(Math.round(value));
};

const initCountups = () => {
    const nodes = document.querySelectorAll('[data-countup]');

    if (nodes.length === 0) {
        return;
    }

    nodes.forEach((node) => {
        const target = Number(node.dataset.countupValue ?? 0);
        const format = node.dataset.countupFormat ?? 'integer';

        if (!Number.isFinite(target)) {
            return;
        }

        if (prefersReducedMotion()) {
            node.textContent = formatValue(target, format);
            return;
        }

        const duration = 900;
        const startedAt = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - startedAt) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = target * eased;

            node.textContent = formatValue(current, format);

            if (progress < 1) {
                window.requestAnimationFrame(tick);
            }
        };

        window.requestAnimationFrame(tick);
    });
};

const tooltipMarkup = (point) => {
    const total = Number(point.dataset.chartTotal ?? 0);
    const online = Number(point.dataset.chartOnline ?? 0);
    const walkin = Number(point.dataset.chartWalkin ?? 0);
    const orders = Number(point.dataset.chartOrders ?? 0);

    return `
        <strong>${point.dataset.chartLabel ?? 'Recent sales'}</strong>
        <p>${orders} order(s)</p>
        <ul>
            <li>Total: ${formatValue(total, 'currency')}</li>
            <li>Online: ${formatValue(online, 'currency')}</li>
            <li>Walk-in: ${formatValue(walkin, 'currency')}</li>
        </ul>
    `;
};

let hideTooltipTimer = null;

const positionTooltip = (chart, point, tooltip, event = null) => {
    const container = chart.querySelector('.ys-admin-chart-grid');
    const surface = chart.querySelector('[data-chart-surface]');

    if (!container || !surface) {
        return;
    }

    const containerRect = container.getBoundingClientRect();
    const pointRect = point.getBoundingClientRect();
    const pointCenterX = (pointRect.left - containerRect.left) + (pointRect.width / 2);
    const pointCenterY = (pointRect.top - containerRect.top) + (pointRect.height / 2);
    const preferredLeft = event?.clientX
        ? event.clientX - containerRect.left
        : pointCenterX;
    const preferredTop = pointCenterY - 14;

    tooltip.hidden = false;
    tooltip.setAttribute('aria-hidden', 'false');
    tooltip.classList.add('is-active');
    tooltip.style.left = '0px';
    tooltip.style.top = '0px';

    const minLeft = (tooltip.offsetWidth / 2) + 12;
    const maxLeft = container.clientWidth - (tooltip.offsetWidth / 2) - 12;
    const clampedLeft = Math.min(Math.max(preferredLeft, minLeft), Math.max(maxLeft, minLeft));
    const aboveTop = preferredTop - tooltip.offsetHeight;
    const fallbackBelowTop = pointCenterY + 24;
    const isBelow = aboveTop < 12;
    const finalTop = !isBelow
        ? aboveTop
        : Math.min(fallbackBelowTop, Math.max(container.clientHeight - tooltip.offsetHeight - 12, 12));
    const arrowLeft = Math.min(
        Math.max(pointCenterX - (clampedLeft - (tooltip.offsetWidth / 2)), 16),
        tooltip.offsetWidth - 16,
    );

    tooltip.classList.toggle('is-below', isBelow);
    tooltip.style.left = `${clampedLeft - (tooltip.offsetWidth / 2)}px`;
    tooltip.style.top = `${finalTop}px`;
    tooltip.style.setProperty('--tooltip-arrow-left', `${arrowLeft}px`);
};

const initSalesChart = () => {
    const chart = document.querySelector('[data-admin-sales-chart]');

    if (!chart) {
        return;
    }

    const tooltip = chart.querySelector('[data-chart-tooltip]');
    const points = chart.querySelectorAll('[data-chart-point]');

    window.requestAnimationFrame(() => {
        chart.classList.add('is-ready');
    });

    if (!tooltip || points.length === 0) {
        return;
    }

    const showTooltip = (point, event = null) => {
        if (hideTooltipTimer !== null) {
            window.clearTimeout(hideTooltipTimer);
            hideTooltipTimer = null;
        }

        tooltip.innerHTML = tooltipMarkup(point);
        positionTooltip(chart, point, tooltip, event);
    };

    const hideTooltip = () => {
        tooltip.classList.remove('is-active');
        tooltip.setAttribute('aria-hidden', 'true');

        hideTooltipTimer = window.setTimeout(() => {
            tooltip.hidden = true;
            tooltip.classList.remove('is-below');
        }, 160);
    };

    points.forEach((point) => {
        point.addEventListener('mouseenter', (event) => showTooltip(point, event));
        point.addEventListener('focus', () => showTooltip(point));
        point.addEventListener('mousemove', (event) => showTooltip(point, event));
        point.addEventListener('mouseleave', hideTooltip);
        point.addEventListener('blur', hideTooltip);
    });
};

export const initAdminDashboard = () => {
    initCountups();
    initSalesChart();
};
