import { bootPage } from './shared/boot-page';
import { createAdminTicketResultsController } from './shared/admin-ticket-index-results';

const initClientTicketsIndexPage = () => {
    const pageRoot = document.querySelector('[data-client-tickets-index-page]');
    if (!pageRoot) return;

    const filterForm = pageRoot.querySelector('form[data-search-history-form]');
    const routeBase = pageRoot.dataset.routeBase || window.location.pathname;

    const resultsController = createAdminTicketResultsController({
        pageRoot,
        filterForm,
        statusView: null,
        routeBase,
        filterFieldSelectors: ['select[name="status"]'],
        getResultsContainer: () => pageRoot.querySelector('[data-client-tickets-results]'),
        hasOpenModal: () => false,
        onResultsUpdated: () => {},
    });

    pageRoot.addEventListener('click', (event) => {
        const paginationLink = event.target.closest('[data-client-ticket-pagination] a');
        if (paginationLink) {
            event.preventDefault();
            void resultsController.loadTicketResults(paginationLink.href, { history: 'push' });
        }
    });

    resultsController.bind();
};

bootPage(initClientTicketsIndexPage);
