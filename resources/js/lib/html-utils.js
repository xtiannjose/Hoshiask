export const escapeHtml = (value) => String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

export const normalizeLeadingUppercase = (value) => {
    const trimmed = String(value || '').trim();
    if (trimmed.length === 0) return '';
    return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
};
