import { bootPage } from './shared/boot-page';
import { syncDepartmentByRole } from './shared/user-role';

const initAdminUsersCreatePage = () => {
    const pageRoot = document.querySelector('[data-admin-users-create-page]');
    if (!pageRoot) return;

    const roleSelect = document.getElementById('role');
    const departmentSelect = document.getElementById('department');
    const departmentHidden = document.getElementById('department_hidden');
    const clientNotesWrap = document.getElementById('client-notes-wrap');
    const clientNotesField = document.getElementById('client_notes');
    const supportDepartment = pageRoot.dataset.supportDepartment || '';

    if (!roleSelect || !departmentSelect || !departmentHidden) return;

    const syncClientNotesByRole = () => {
        if (!clientNotesWrap || !clientNotesField) return;

        const isClient = roleSelect.value === 'client';
        clientNotesWrap.classList.toggle('hidden', !isClient);
        clientNotesField.disabled = !isClient;
    };

    roleSelect.addEventListener('change', () => {
        syncDepartmentByRole({ roleSelect, departmentSelect, departmentHidden, supportDepartment });
        syncClientNotesByRole();
    });

    syncDepartmentByRole({ roleSelect, departmentSelect, departmentHidden, supportDepartment });
    syncClientNotesByRole();
};

bootPage(initAdminUsersCreatePage);
