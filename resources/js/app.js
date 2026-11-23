document.querySelectorAll('[data-booking-form]').forEach((form) => {
    const container = form.querySelector('[data-companions]');
    const template = form.querySelector('[data-companion-template]');
    const addButton = form.querySelector('[data-add-companion]');

    if (!container || !template || !addButton) {
        return;
    }

    const existingIndexes = [...container.querySelectorAll('[data-companion-row]')]
        .map((row) => Number.parseInt(row.dataset.index, 10))
        .filter(Number.isInteger);
    let nextIndex = existingIndexes.length > 0 ? Math.max(...existingIndexes) + 1 : 0;

    const updateAddButton = () => {
        addButton.disabled = container.querySelectorAll('[data-companion-row]').length >= 39;
    };

    addButton.addEventListener('click', () => {
        if (container.querySelectorAll('[data-companion-row]').length >= 39) {
            return;
        }

        const row = template.content.firstElementChild.cloneNode(true);
        row.dataset.index = String(nextIndex);
        row.dataset.companionRow = '';

        row.querySelectorAll('[data-field]').forEach((field) => {
            field.name = `companions[${nextIndex}][${field.dataset.field}]`;
        });

        row.querySelector('[data-remove-companion]').addEventListener('click', () => {
            row.remove();
            updateAddButton();
        });

        container.append(row);
        nextIndex += 1;
        updateAddButton();
    });

    container.querySelectorAll('[data-remove-companion]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('[data-companion-row]').remove();
            updateAddButton();
        });
    });

    updateAddButton();
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('LuxStay service worker registration failed.', error);
        });
    });
}
