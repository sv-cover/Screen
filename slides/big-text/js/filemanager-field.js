class FilemanagerField {
    static parseDocument(context) {
        const elements = context.querySelectorAll('input[data-filemanager]');

        elements.forEach(element => {
            new FilemanagerField({
                element: element,
                filemanagerUrl: element.dataset.filemanagerUrl || 'https://filemanager.svcover.nl',
            });
        });

    }

    constructor(options) {
        this.element = options.element;
        this.parentElement = options.element.closest('.filemanager-field');
        this.filemanagerUrl = options.filemanagerUrl;

        this.setupEvents();
    }

    createModal() {
        let url = new URL(`${this.filemanagerUrl}/fileman`);
        if (this.element.value) {
            let searchParams = url.searchParams;
            searchParams.set('selected', this.element.value);
            url.search = searchParams.toString();
        }

        let dialog = document.createElement('dialog');
        dialog.classList.add('filemanager-dialog');
        let dialogForm = document.createElement('form');
        dialogForm.method = 'dialog';
        dialogForm.innerHTML = `
            <h3>Choose an image…</h3>
            <button aria-label="Close"><svg class="icon" aria-hidden="true"><use href="img/feather-icons.svg#x"/></svg></button>
        `;
        dialog.append(dialogForm);

        let iframeElement = document.createElement('iframe');
        iframeElement.src = url;
        dialog.append(iframeElement);

        document.body.append(dialog);
        this.dialogElement = dialog;
        this.iframeElement = iframeElement;
    }

    showModal() {
        if (!this.dialogElement)
            this.createModal();
        this.dialogElement.showModal();
        this.iframeElement.focus();
    }

    setupEvents() {
        this.parentElement.addEventListener('click', this.handleClick.bind(this));
        window.addEventListener('message', this.handleMessage.bind(this), false);
    }

    handleClick() {
        this.showModal();
    }

    handleMessage(event) {
        // Make sure the message originates from our own iframe
        if (this.iframeElement.contentWindow === event.source) {
            const file = JSON.parse(event.data);
            this.pickFile(file);
        }
    }

    pickFile(file) {
        // Derive correct url
        const fileUrl = `${file.fullPath}`;

        // Set url everywhere
        this.element.value = fileUrl;
        this.element.dispatchEvent(new Event('input', {bubbles:true, cancelable: true}));

        // Close modal
        if (this.dialogElement)
            this.dialogElement.close();
    }
}

FilemanagerField.parseDocument(document);
