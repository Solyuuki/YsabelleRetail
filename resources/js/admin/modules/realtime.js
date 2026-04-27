import { showToast } from '../../storefront/modules/toasts';

const renderActivityList = (entries) => {
    if (!Array.isArray(entries) || entries.length === 0) {
        return '<div class="ys-admin-empty-panel">Live activity will appear here once sales or stock updates happen.</div>';
    }

    return entries.map((entry) => `
        <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-ys-ivory">${entry.title}</p>
                    <p class="mt-1 text-sm leading-6 text-ys-ivory/58">${entry.message}</p>
                </div>
                <span class="shrink-0 text-xs uppercase tracking-[0.2em] text-ys-ivory/36">${entry.timestamp ?? ''}</span>
            </div>
        </div>
    `).join('');
};

export const initAdminRealtime = () => {
    const root = document.querySelector('[data-admin-app]');
    const endpoint = root?.dataset.adminActivityEndpoint;

    if (!root || !endpoint) {
        return;
    }

    const listNode = document.querySelector('[data-admin-live-feed-list]');
    const statusNode = document.querySelector('[data-admin-live-status]');
    let cursor = null;
    let booted = false;
    let inFlight = false;

    const syncSnapshot = async () => {
        if (inFlight) {
            return;
        }

        inFlight = true;

        try {
            const params = new URLSearchParams();

            if (cursor !== null) {
                params.set('after', String(cursor));
            }

            const response = await fetch(`${endpoint}${params.toString() ? `?${params.toString()}` : ''}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Admin activity polling failed with ${response.status}`);
            }

            const payload = await response.json();

            if (listNode && Array.isArray(payload.activities)) {
                listNode.innerHTML = renderActivityList(payload.activities);
            }

            if (statusNode) {
                statusNode.textContent = payload.mode === 'polling_fallback'
                    ? 'Live via polling'
                    : 'Live';
            }

            if (booted && Array.isArray(payload.notifications)) {
                payload.notifications.forEach((notification) => showToast(notification));
            }

            cursor = typeof payload.cursor === 'number' ? payload.cursor : cursor;
            booted = true;
        } catch (error) {
            if (statusNode) {
                statusNode.textContent = 'Polling retrying';
            }
        } finally {
            inFlight = false;
        }
    };

    syncSnapshot();
    window.setInterval(() => {
        if (document.hidden) {
            return;
        }

        syncSnapshot();
    }, 10000);
};
