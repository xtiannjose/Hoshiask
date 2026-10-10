import { bootPage } from './shared/boot-page';
import { syncDepartmentByRole } from './shared/user-role';

const initAdminUsersEditPage = () => {
    const pageRoot = document.querySelector('[data-admin-users-edit-page]');
    if (!pageRoot) return;

    const roleSelect = document.getElementById('role');
    const departmentSelect = document.getElementById('department');
    const departmentHidden = document.getElementById('department_hidden');
    const profileLockInput = document.getElementById('is_profile_locked');
    const profileLockToggle = document.getElementById('profile-lock-toggle');
    const profileLockStateLabel = document.getElementById('profile-lock-state-label');
    const profileLockIconLocked = document.getElementById('profile-lock-icon-locked');
    const profileLockIconUnlocked = document.getElementById('profile-lock-icon-unlocked');
    const profileEditLockedBanner = document.getElementById('profile-edit-locked-banner');
    const lockableFields = document.querySelectorAll('[data-profile-edit-lockable]');
    const supportDepartment = pageRoot.dataset.supportDepartment || '';

    if (!roleSelect || !departmentSelect || !departmentHidden || !profileLockInput || !profileLockToggle) return;

    const applyProfileLockState = () => {
        const isLocked = profileLockInput.value === '1';

        profileLockToggle.setAttribute('aria-pressed', isLocked ? 'true' : 'false');
        profileLockToggle.classList.add('border-slate-300');
        profileLockToggle.classList.remove('border-red-300', 'bg-red-50', 'border-emerald-300', 'bg-emerald-50');

        if (profileLockStateLabel) {
            profileLockStateLabel.textContent = isLocked ? 'Locked' : 'Unlocked';
        }

        if (profileLockIconLocked) {
            profileLockIconLocked.classList.toggle('hidden', !isLocked);
        }

        if (profileLockIconUnlocked) {
            profileLockIconUnlocked.classList.toggle('hidden', isLocked);
        }

        if (profileEditLockedBanner) {
            profileEditLockedBanner.classList.toggle('hidden', !isLocked);
        }

        lockableFields.forEach((field) => {
            const isTextLike = field.matches('input[type="text"], input[type="email"], input[type="password"], input[type="tel"]');

            if (isTextLike) {
                field.readOnly = isLocked;
                field.classList.toggle('bg-gray-100', isLocked);
                field.classList.toggle('cursor-not-allowed', isLocked);
                return;
            }

            if (!field.dataset.profileLockTabIndex) {
                field.dataset.profileLockTabIndex = String(field.tabIndex);
            }

            if (isLocked) {
                field.classList.add('pointer-events-none', 'opacity-60');
                field.setAttribute('aria-disabled', 'true');
                field.tabIndex = -1;
                return;
            }

            field.classList.remove('pointer-events-none', 'opacity-60');
            field.removeAttribute('aria-disabled');
            field.tabIndex = Number(field.dataset.profileLockTabIndex || 0);
        });
    };

    profileLockToggle.addEventListener('click', () => {
        profileLockInput.value = profileLockInput.value === '1' ? '0' : '1';
        applyProfileLockState();
        syncDepartmentByRole({ roleSelect, departmentSelect, departmentHidden, supportDepartment });
    });

    roleSelect.addEventListener('change', () => syncDepartmentByRole({ roleSelect, departmentSelect, departmentHidden, supportDepartment }));

    syncDepartmentByRole({ roleSelect, departmentSelect, departmentHidden, supportDepartment });
    applyProfileLockState();

    const revealTimers = new Map();
    const peekButtons = document.querySelectorAll('[data-peek-password-for]');

    peekButtons.forEach((button) => {
        const targetId = button.getAttribute('data-peek-password-for');
        const input = targetId ? document.getElementById(targetId) : null;

        if (!input) return;

        button.addEventListener('click', () => {
            input.setAttribute('type', 'text');
            button.disabled = true;
            button.classList.add('opacity-60');

            const existingTimer = revealTimers.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
            }

            const timer = window.setTimeout(() => {
                input.setAttribute('type', 'password');
                button.disabled = false;
                button.classList.remove('opacity-60');
                revealTimers.delete(input);
            }, 500);

            revealTimers.set(input, timer);
        });
    });
};

bootPage(initAdminUsersEditPage);
