const INTERNAL_ROLES = new Set(['shadow', 'admin', 'super_user', 'technical']);

export const isInternalRole = (role) => INTERNAL_ROLES.has(String(role || '').trim());

export const syncDepartmentByRole = ({ roleSelect, departmentSelect, departmentHidden, supportDepartment }) => {
    if (!roleSelect || !departmentSelect || !departmentHidden) return;

    const internalDepartment = String(supportDepartment || '').trim() || 'Hoshishi';

    if (isInternalRole(roleSelect.value)) {
        if (!String(departmentSelect.value || '').trim()) {
            departmentSelect.value = internalDepartment;
        }
        departmentSelect.disabled = false;
        departmentHidden.value = '';
        departmentHidden.disabled = true;
        return;
    }

    departmentSelect.disabled = false;
    departmentHidden.value = '';
    departmentHidden.disabled = true;
};
