import { bootPage } from './shared/boot-page';
import { createDashboardHeartbeatPoller } from './shared/dashboard-heartbeat';

const initAdminDashboardPage = () => {
    const pageRoot = document.querySelector('[data-admin-dashboard-page]');
    if (!pageRoot) return;

    const initialToken = pageRoot.dataset.snapshotToken || '';
    if (!initialToken) return;

    const heartbeatUrl = new URL(pageRoot.dataset.heartbeatUrl || window.location.href, window.location.origin);
    heartbeatUrl.searchParams.set('heartbeat', '1');

    createDashboardHeartbeatPoller({
        pageRoot,
        initialToken,
        heartbeatUrl,
        pollDelayMs: 10000,
        resumeDelayMs: 2000,
        errorLabel: 'Admin dashboard heartbeat check failed.',
    }).start();
};

bootPage(initAdminDashboardPage);
