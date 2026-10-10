// Checks chosen files against the server's real limits before the form is sent, so people are told
// which file is too big instead of losing their typed text to a bare "413" page.

export const formatFileSize = (bytes) => {
    if (bytes >= 1024 * 1024) {
        return `${(Math.round((bytes / (1024 * 1024)) * 10) / 10).toString()} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

// files: array of { name, size }. A limit of 0 (or less) means "no limit".
export const findUploadProblems = (files, maxFileBytes, maxRequestBytes, maxFiles = 0) => {
    const problems = [];

    if (maxFiles > 0 && files.length > maxFiles) {
        problems.push(`You chose ${files.length} files, but at most ${maxFiles} can be attached at a time. Send the rest in a reply.`);
    }

    files.forEach((file) => {
        if (maxFileBytes > 0 && file.size > maxFileBytes) {
            problems.push(`"${file.name}" is ${formatFileSize(file.size)}. Each file can be at most ${formatFileSize(maxFileBytes)}.`);
        }
    });

    const total = files.reduce((sum, file) => sum + file.size, 0);
    if (maxRequestBytes > 0 && total > maxRequestBytes) {
        problems.push(`Together your files are ${formatFileSize(total)}, but one submission can carry at most ${formatFileSize(maxRequestBytes)}. Send fewer files at a time.`);
    }

    return problems;
};

const applyGuard = (input) => {
    const feedback = document.getElementById(input.dataset.uploadGuard || '');
    const maxFileBytes = Number(input.dataset.maxFileBytes) || 0;
    const maxRequestBytes = Number(input.dataset.maxRequestBytes) || 0;
    const maxFiles = Number(input.dataset.maxFiles) || 0;

    const problems = findUploadProblems(Array.from(input.files || []), maxFileBytes, maxRequestBytes, maxFiles);
    const message = problems.join(' ');

    // A custom validity message blocks submitting the form and is announced by the browser.
    input.setCustomValidity(message);
    input.setAttribute('aria-invalid', message ? 'true' : 'false');

    if (feedback) {
        feedback.textContent = message;
        feedback.classList.toggle('hidden', message === '');
    }
};

export const registerUploadGuard = () => {
    document.querySelectorAll('input[type="file"][data-upload-guard]').forEach((input) => {
        input.addEventListener('change', () => applyGuard(input));
    });
};
