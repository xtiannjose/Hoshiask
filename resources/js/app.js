import Alpine from '@alpinejs/csp';
import { registerEnhancedSelects } from './lib/enhanced-selects';
import { registerFormBehaviors } from './lib/form-behaviors';
import { registerLocationSuggestions } from './lib/location-suggestions';
import { registerModalKit } from './lib/modal-kit';
import { registerRatingForms } from './lib/rating-form';
import { registerSearchHistory } from './lib/search-history';
import { registerUploadGuard } from './lib/upload-guard';
import { registerAlpineStateCollections, registerThemeGlobals } from './lib/theme-state';

registerThemeGlobals();

window.Alpine = Alpine;

registerAlpineStateCollections(Alpine);
Alpine.start();

registerFormBehaviors();
registerModalKit();
registerEnhancedSelects();
registerUploadGuard();
registerRatingForms();
registerLocationSuggestions();
registerSearchHistory();

const pageModules = [
    {
        selector: '[data-login-page]',
        loader: () => import('./pages/login-page'),
    },
    {
        selector: '[data-account-settings-page]',
        loader: () => import('./pages/account-settings-page'),
    },
    {
        selector: '[data-admin-dashboard-page]',
        loader: () => import('./pages/admin-dashboard-page'),
    },
    {
        selector: '[data-admin-online-users-page]',
        loader: () => import('./pages/admin-online-users-page'),
    },
    {
        selector: '[data-admin-ticket-create-page]',
        loader: () => import('./pages/admin-ticket-create-page'),
    },
    {
        selector: '[data-admin-reports-page]',
        loader: () => import('./pages/admin-reports-page'),
    },
    {
        selector: '[data-admin-ticket-show-page]',
        loader: () => import('./pages/admin-ticket-show-page'),
    },
    {
        selector: '[data-admin-tickets-index-page]',
        loader: () => import('./pages/admin-tickets-index-page'),
    },
    {
        selector: '[data-admin-users-create-page]',
        loader: () => import('./pages/admin-users-create-page'),
    },
    {
        selector: '[data-admin-users-edit-page]',
        loader: () => import('./pages/admin-users-edit-page'),
    },
    {
        selector: '[data-admin-users-page]',
        loader: () => import('./pages/admin-users-page'),
    },
    {
        selector: '.js-header-notification-list',
        loader: () => import('./pages/app-layout-notifications-page'),
    },
    {
        selector: '[data-knowledge-base-page]',
        loader: () => import('./pages/knowledge-base-page'),
    },
    {
        selector: '[data-client-dashboard-page]',
        loader: () => import('./pages/client-dashboard-page'),
    },
    {
        selector: '[data-client-ticket-create-page]',
        loader: () => import('./pages/client-ticket-create-page'),
    },
    {
        selector: '[data-client-ticket-show-page]',
        loader: () => import('./pages/client-ticket-show-page'),
    },
    {
        selector: '[data-client-tickets-index-page]',
        loader: () => import('./pages/client-tickets-index-page'),
    },
];

const loadPageModules = () => {
    pageModules.forEach(({ selector, loader }) => {
        if (!document.querySelector(selector)) {
            return;
        }

        void loader();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadPageModules, { once: true });
} else {
    loadPageModules();
}
