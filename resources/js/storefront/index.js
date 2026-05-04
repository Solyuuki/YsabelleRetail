import { initChatWidget } from './modules/chat';
import { initCheckoutOptions } from './modules/checkout';
import { initStorefrontFilters } from './modules/filters';
import { initHeaderMenus } from './modules/header';
import { initProductMedia } from './modules/media';
import { initTrustMarquees } from './modules/marquee';
import { initRevealMotion } from './modules/motion';
import { initInlineVisualSearch } from './modules/inline-visual-search';
import { initProductDetailForm } from './modules/product-detail';
import { initRoleShortcuts } from './modules/role-shortcuts';
import { initCartQuantityForms } from './modules/cart';
import { initHeroShowcase } from './modules/hero-showcase';
import { initSupportPages } from './modules/support-pages';
import { initToasts } from './modules/toasts';

const initStorefront = () => {
    initHeaderMenus();
    initHeroShowcase();
    initSupportPages();
    initProductMedia();
    initTrustMarquees();
    initRevealMotion();
    initStorefrontFilters();
    initInlineVisualSearch();
    initProductDetailForm();
    initCartQuantityForms();
    initCheckoutOptions();
    initToasts();
    initRoleShortcuts();
    initChatWidget();
};

document.addEventListener('DOMContentLoaded', initStorefront);
