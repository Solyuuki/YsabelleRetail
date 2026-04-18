import { initChatWidget } from './modules/chat';
import { initCheckoutOptions } from './modules/checkout';
import { initStorefrontFilters } from './modules/filters';
import { initHeaderMenus } from './modules/header';
import { initTrustMarquees } from './modules/marquee';
import { initRevealMotion } from './modules/motion';
import { initProductDetailForm } from './modules/product-detail';
import { initCartQuantityForms } from './modules/cart';
import { initToasts } from './modules/toasts';

const initStorefront = () => {
    initHeaderMenus();
    initTrustMarquees();
    initRevealMotion();
    initStorefrontFilters();
    initProductDetailForm();
    initCartQuantityForms();
    initCheckoutOptions();
    initToasts();
    initChatWidget();
};

document.addEventListener('DOMContentLoaded', initStorefront);
