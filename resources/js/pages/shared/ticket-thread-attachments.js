export const createAttachmentLink = (attachment, options = {}) => {
    const {
        borderClass = 'border-slate-200',
        hoverClass = 'hover:bg-slate-50',
    } = options;

    const link = document.createElement('a');
    link.href = attachment.download_url;
    const canPreview = Boolean(attachment.can_preview && attachment.preview_url);
    if (canPreview) {
        link.dataset.fileUrl = attachment.preview_url;
        link.dataset.fileName = attachment.original_filename;
        link.dataset.fileMime = attachment.mime_type;
    }

    if (attachment.is_image) {
        link.className = `${canPreview ? 'js-attachment-link ' : ''}block w-[240px] max-w-full overflow-hidden rounded-xl border ${borderClass} bg-white p-2 text-sm transition ${hoverClass}`;

        const image = document.createElement('img');
        image.src = canPreview ? attachment.preview_url : attachment.download_url;
        image.alt = attachment.original_filename || 'Attachment image';
        image.className = 'h-36 w-full rounded-lg object-cover';

        const caption = document.createElement('span');
        caption.className = 'mt-2 block truncate text-xs text-slate-600';
        caption.textContent = attachment.original_filename;

        link.appendChild(image);
        link.appendChild(caption);
        return link;
    }

    link.className = `${canPreview ? 'js-attachment-link ' : ''}flex max-w-full items-center rounded-xl border ${borderClass} bg-white px-3 py-2 text-sm transition ${hoverClass}`;

    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('class', 'mr-2 h-4 w-4 text-slate-500');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('viewBox', '0 0 24 24');

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('d', 'M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13');
    icon.appendChild(path);

    const name = document.createElement('span');
    name.className = 'truncate';
    name.textContent = attachment.original_filename;

    link.appendChild(icon);
    link.appendChild(name);
    return link;
};
