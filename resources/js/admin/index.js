import { initAdminForms } from './modules/forms';
import { initAdminPos } from './modules/pos';
import { initAdminShell } from './modules/shell';
import { initConfirmActions } from './modules/confirm';

const initAdmin = () => {
    if (!document.querySelector('[data-admin-app]')) {
        return;
    }

    initAdminShell();
    initAdminForms();
    initConfirmActions();
    initAdminPos();
};

document.addEventListener('DOMContentLoaded', initAdmin);
