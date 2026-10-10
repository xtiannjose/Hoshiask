import { bootPage } from './shared/boot-page';
import { createDashboardHeartbeatPoller } from './shared/dashboard-heartbeat';

const initClientDashboardPage = () => {
    const pageRoot = document.querySelector('[data-client-dashboard-page]');
    if (!pageRoot) return;

    const initialToken = pageRoot.dataset.snapshotToken || '';
    if (!initialToken) return;

    const heartbeatUrl = new URL(pageRoot.dataset.heartbeatUrl || window.location.href, window.location.origin);
    heartbeatUrl.searchParams.set('heartbeat', '1');

    createDashboardHeartbeatPoller({
        pageRoot,
        initialToken,
        heartbeatUrl,
        pollDelayMs: 30000,
        resumeDelayMs: 5000,
        errorLabel: 'Client dashboard heartbeat check failed.',
    }).start();
};

bootPage(initClientDashboardPage);
