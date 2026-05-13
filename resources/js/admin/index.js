import { initAdminForms } from './modules/forms';
import { initAdminPos } from './modules/pos';
import { initAdminShell } from './modules/shell';
import { initConfirmActions } from './modules/confirm';
import { initAdminRealtime } from './modules/realtime';
import { initAdminDashboard } from './modules/dashboard';
import { initProductBuilder } from './modules/product-builder';

const initAdmin = () => {
    if (!document.querySelector('[data-admin-app]')) {
        return;
    }

    initAdminShell();
    initAdminForms();
    initConfirmActions();
    initAdminPos();
    initAdminRealtime();
    initAdminDashboard();
    initProductBuilder();
};

document.addEventListener('DOMContentLoaded', initAdmin);
