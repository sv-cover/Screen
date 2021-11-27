document.querySelectorAll('[data-slide-list-sortable]').forEach((el) => {
    new Sortable(el, {
        animation: 150,
        handle: '.sortable-handle',
        ghostClass: 'active',
        async onSort() {
            try {
                const response = await fetch(el.dataset.slideOrderUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({order: this.toArray()}),
                });
                if (!response.ok) {
                    console.error(await response.json());
                    throw new Error(`API returned unsuccessful response: ${response.status}`);
                }
            } catch (error) {
                console.error(error);
                alert('Could not update order! Check the console for more info.');
            }
        },
    });
});

document.querySelectorAll('[data-slide-active-switch]').forEach((el) => {
    el.addEventListener('input', async (evt) => {
        try {
            const response = await fetch(el.dataset.slideUpdateUrl, {
                method: 'PATCH',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({is_active: evt.target.checked}),
            });
            if (!response.ok) {
                console.error(await response.json());
                throw new Error(`API returned unsuccessful response: ${response.status}`);
            }
        } catch (error) {
            console.error(error);
            alert('Could not update slide visibility! Check the console for more info.');
            evt.target.checked = !evt.target.checked;
        }
    });
});
