import { showToast } from '../../storefront/modules/toasts';

const renderActivityList = (entries) => {
    if (!Array.isArray(entries) || entries.length === 0) {
        return `
            <div class="ys-admin-empty-state is-compact">
                <span class="ys-admin-empty-state-icon">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <circle cx="12" cy="12" r="8"></circle>
                    </svg>
                </span>
                <div>
                    <p class="ys-admin-empty-state-title">Activity stream is on standby.</p>
                    <p class="ys-admin-empty-state-copy">New orders, counter sales, and stock updates will appear here in near real time.</p>
                </div>
            </div>
        `;
    }

    return entries.map((entry) => `
        <div class="ys-admin-live-item">
            <div class="ys-admin-live-item-main">
                <span class="ys-admin-live-indicator is-${entry.type ?? 'success'}"></span>
                <div>
                    <p class="ys-admin-live-title">${entry.title}</p>
                    <p class="ys-admin-live-copy">${entry.message}</p>
                </div>
            </div>
            <span class="ys-admin-live-time">${entry.timestamp ?? ''}</span>
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
