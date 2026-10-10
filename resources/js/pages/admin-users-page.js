import { bootPage } from './shared/boot-page';

const initAdminUsersPage = () => {
    const pageRoot = document.querySelector('[data-admin-users-page]');
    if (!pageRoot) return;

    const usersBaseUrl = pageRoot.dataset.usersBaseUrl || '/admin/users';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const bindAvatarFallbacks = () => {
        document.querySelectorAll('img.js-avatar-logo').forEach((avatarImage) => {
            avatarImage.addEventListener('error', () => {
                avatarImage.classList.add('hidden');
                const fallbackNode = avatarImage.nextElementSibling;
                if (fallbackNode) {
                    fallbackNode.classList.remove('hidden');
                }
            }, { once: true });
        });
    };

    const statusModal = document.getElementById('statusConfirmModal');
    const statusModalTitle = document.getElementById('statusModalTitle');
    const statusPromptText = document.getElementById('statusPromptText');
    const statusTargetUserName = document.getElementById('statusTargetUserName');
    const statusCheckbox = document.getElementById('statusConfirmCheckbox');
    const statusModalError = document.getElementById('statusModalError');
    const statusCheckboxText = document.getElementById('statusCheckboxText');
    const confirmStatusButton = document.getElementById('confirmStatusChange');
    const cancelStatusButton = document.getElementById('cancelStatusChange');

    const deleteModal = document.getElementById('deleteModal');
    const deleteUserNameNode = document.getElementById('deleteUserName');
    const deleteCheckbox = document.getElementById('deleteConfirmCheckbox');
    const confirmDeleteButton = document.getElementById('confirmDelete');
    const cancelDeleteButton = document.getElementById('cancelDelete');

    const notificationWrap = document.getElementById('actionNotification');
    const notificationMessage = document.getElementById('actionNotificationMessage');

    const togglePasswordButton = document.getElementById('toggleManagedUserPassword');
    const managedPasswordInput = document.getElementById('managedUserPassword');

    let statusModalState = {
        userId: null,
        userName: '',
        nextIsActive: null,
    };

    let deleteModalState = {
        userId: null,
        userName: '',
    };

    let notificationTimer = null;

    const statusModalController = window.ModalKit && statusModal
        ? window.ModalKit.bind(statusModal, {
            closeButtons: cancelStatusButton ? [cancelStatusButton] : [],
            onClose: () => {
                statusModalState = { userId: null, userName: '', nextIsActive: null };
                if (statusCheckbox) statusCheckbox.checked = false;
                syncStatusSubmitState();
            },
        })
        : null;

    const deleteModalController = window.ModalKit && deleteModal
        ? window.ModalKit.bind(deleteModal, {
            closeButtons: cancelDeleteButton ? [cancelDeleteButton] : [],
            onClose: () => {
                deleteModalState = { userId: null, userName: '' };
                if (deleteCheckbox) deleteCheckbox.checked = false;
                syncDeleteSubmitState();
            },
        })
        : null;

    const buildUserUrl = (userId) => `${usersBaseUrl}/${userId}`;
    const buildToggleUrl = (userId) => `${usersBaseUrl}/${userId}/toggle-status`;

    const showNotification = (message, type = 'info') => {
        if (!notificationWrap || !notificationMessage) return;

        if (notificationTimer !== null) {
            window.clearTimeout(notificationTimer);
            notificationTimer = null;
        }

        notificationMessage.textContent = message;
        notificationWrap.classList.remove('hidden');

        if (type === 'error') {
            notificationWrap.classList.remove('border-amber-200', 'bg-amber-50', 'text-amber-800');
            notificationWrap.classList.add('border-red-200', 'bg-red-50', 'text-red-800');
        } else {
            notificationWrap.classList.remove('border-red-200', 'bg-red-50', 'text-red-800');
            notificationWrap.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-800');
        }

        notificationTimer = window.setTimeout(() => {
            notificationWrap.classList.add('hidden');
            notificationTimer = null;
        }, 3500);
    };

    function syncStatusSubmitState() {
        if (!confirmStatusButton || !statusCheckbox) return;
        confirmStatusButton.disabled = !statusCheckbox.checked;
    }

    function syncDeleteSubmitState() {
        if (!confirmDeleteButton || !deleteCheckbox) return;
        confirmDeleteButton.disabled = !deleteCheckbox.checked;
    }

    const setStatusButtonVisual = (button, isActive) => {
        if (!button) return;
        button.textContent = isActive ? 'Active' : 'Inactive';
        button.setAttribute('aria-label', isActive ? 'Active – click to deactivate' : 'Inactive – click to activate');
        button.dataset.newStatus = isActive ? '0' : '1';
        button.classList.toggle('bg-green-100', isActive);
        button.classList.toggle('text-green-800', isActive);
        button.classList.toggle('bg-red-100', !isActive);
        button.classList.toggle('text-red-800', !isActive);
    };

    const updateStatusUI = (userId, isActive) => {
        document.querySelectorAll(`.js-toggle-user-status[data-user-id="${userId}"]`).forEach((button) => {
            const inAccountActions = button.classList.contains('w-full');
            if (inAccountActions) {
                button.textContent = isActive ? 'Deactivate Account' : 'Activate Account';
                button.dataset.newStatus = isActive ? '0' : '1';
                return;
            }

            setStatusButtonVisual(button, isActive);
        });
    };

    const openStatusModal = ({ userId, userName, nextIsActive }) => {
        if (!statusModal) return;

        if (statusModalError) statusModalError.classList.add('hidden');

        statusModalState = {
            userId,
            userName,
            nextIsActive,
        };

        const targetName = userName || 'this user';

        if (statusTargetUserName) {
            statusTargetUserName.textContent = targetName;
        }

        if (nextIsActive) {
            if (statusModalTitle) statusModalTitle.textContent = 'Activate Account';
            if (statusPromptText) statusPromptText.textContent = 'Are you sure you want to activate';
            if (statusCheckboxText) statusCheckboxText.textContent = 'I understand this user will be able to sign in.';
            if (confirmStatusButton) confirmStatusButton.textContent = 'Activate Account';
        } else {
            if (statusModalTitle) statusModalTitle.textContent = 'Deactivate Account';
            if (statusPromptText) statusPromptText.textContent = 'Are you sure you want to deactivate';
            if (statusCheckboxText) statusCheckboxText.textContent = 'I understand this user will not be able to sign in.';
            if (confirmStatusButton) confirmStatusButton.textContent = 'Deactivate Account';
        }

        if (statusCheckbox) {
            statusCheckbox.checked = false;
        }
        syncStatusSubmitState();

        if (statusModalController) statusModalController.open();
    };

    const openDeleteModal = ({ userId, userName }) => {
        if (!deleteModal) return;

        deleteModalState = {
            userId,
            userName,
        };

        if (deleteUserNameNode) {
            deleteUserNameNode.textContent = userName || 'this user';
        }

        if (deleteCheckbox) {
            deleteCheckbox.checked = false;
        }
        syncDeleteSubmitState();

        if (deleteModalController) deleteModalController.open();
    };

    pageRoot.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const statusButton = target.closest('.js-toggle-user-status');
        if (statusButton instanceof HTMLElement) {
            const userId = statusButton.dataset.userId;
            const userName = statusButton.dataset.userName || '';
            const nextIsActive = statusButton.dataset.newStatus === '1';

            if (!userId) return;
            openStatusModal({ userId, userName, nextIsActive });

            return;
        }

        const deleteButton = target.closest('.delete-user-btn');
        if (!(deleteButton instanceof HTMLElement)) return;

        const userId = deleteButton.dataset.userId;
        const userName = deleteButton.dataset.userName || '';
        if (!userId) return;

        openDeleteModal({ userId, userName });
    });

    if (statusCheckbox) {
        statusCheckbox.addEventListener('change', syncStatusSubmitState);
        syncStatusSubmitState();
    }

    if (deleteCheckbox) {
        deleteCheckbox.addEventListener('change', syncDeleteSubmitState);
        syncDeleteSubmitState();
    }

    if (confirmStatusButton) {
        confirmStatusButton.addEventListener('click', async () => {
            if (!statusModalState.userId) return;
            if (statusCheckbox && !statusCheckbox.checked) return;

            if (statusModalError) statusModalError.classList.add('hidden');
            confirmStatusButton.disabled = true;
            const previousLabel = confirmStatusButton.textContent;
            confirmStatusButton.textContent = statusModalState.nextIsActive ? 'Activating...' : 'Deactivating...';

            try {
                const response = await fetch(buildToggleUrl(statusModalState.userId), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || !payload || payload.success !== true) {
                    const message = payload && payload.error ? payload.error : 'Unable to update user status.';
                    throw new Error(message);
                }

                const isActive = Boolean(payload.is_active);
                updateStatusUI(statusModalState.userId, isActive);

                if (statusModalController) statusModalController.close();

                showNotification(payload.message || 'User status updated successfully.');
            } catch (error) {
                // The dialog stays open and sits above the page toast, so the reason is shown inside it.
                const message = error?.message || 'Unable to update user status.';
                if (statusModalError) {
                    statusModalError.textContent = message;
                    statusModalError.classList.remove('hidden');
                } else {
                    showNotification(message, 'error');
                }
                confirmStatusButton.disabled = false;
                confirmStatusButton.textContent = previousLabel;
            }
        });
    }

    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', () => {
            if (!deleteModalState.userId) return;
            if (deleteCheckbox && !deleteCheckbox.checked) return;

            confirmDeleteButton.disabled = true;
            confirmDeleteButton.textContent = 'Deleting...';

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = buildUserUrl(deleteModalState.userId);

            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';

            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrfToken;

            form.appendChild(methodInput);
            form.appendChild(tokenInput);
            document.body.appendChild(form);
            form.submit();
        });
    }

    if (togglePasswordButton && managedPasswordInput) {
        togglePasswordButton.addEventListener('click', () => {
            const shouldShow = managedPasswordInput.type === 'password';
            managedPasswordInput.type = shouldShow ? 'text' : 'password';
            togglePasswordButton.textContent = shouldShow ? 'Hide' : 'Show';
            togglePasswordButton.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
        });
    }

    bindAvatarFallbacks();
};

bootPage(initAdminUsersPage);

