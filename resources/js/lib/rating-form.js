// A comment is optional for a good rating, but asked for when the client gives 1 or 2 stars, so the
// team learns what went wrong. The server enforces the same rule; this only makes it visible early.

export const LOW_RATING_MAX = 2;

export const commentRequiredForRating = (value) => {
    const rating = Number.parseInt(String(value), 10);

    return Number.isInteger(rating) && rating >= 1 && rating <= LOW_RATING_MAX;
};

const syncForm = (form) => {
    const checked = form.querySelector('input[name="rating"]:checked');
    const comment = form.querySelector('[data-rating-comment]');
    const hint = form.querySelector('[data-rating-comment-hint]');
    const marker = form.querySelector('[data-rating-comment-marker]');
    if (!comment) return;

    const required = commentRequiredForRating(checked ? checked.value : '');

    comment.required = required;
    if (marker) {
        marker.textContent = required ? '(required)' : '(optional)';
    }
    if (hint) {
        hint.hidden = !required;
    }
};

export const registerRatingForms = () => {
    document.querySelectorAll('form[data-rating-form]').forEach((form) => {
        form.addEventListener('change', (event) => {
            const target = event.target;
            if (target instanceof HTMLInputElement && target.name === 'rating') {
                syncForm(form);
            }
        });
        syncForm(form);
    });
};
