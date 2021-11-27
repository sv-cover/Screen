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
        this.parentElement = options.element.closest('.form-group');
        this.filemanagerUrl = options.filemanagerUrl;

        this.setupEvents();
    }

    showModal() {
        // This project is using jquery anyway...
        let url = new URL(`${this.filemanagerUrl}/fileman`);
        if (this.element.value) {
            let searchParams = url.searchParams;
            searchParams.set('selected', this.element.value);
            url.search = searchParams.toString();
        }
        this.element.value
        this.$modal = $('<div class="modal">').appendTo(document.body);
        this.$modal.html(`
                <div class="modal-dialog modal-lg filemanager-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>\
                            <h4 class="modal-title">Choose an image…</h4>
                        </div>
                        <div class="modal-body">
                            <iframe
                                frameborder="0"
                                src="${url}"
                            ></iframe>
                        </div>
                    </div>
                </div>`);        this.$modal.on('click', '*[data-dismiss=modal]', (evt) => {
            evt.preventDefault();
            this.$modal.modal('hide');
        });
        // Remove dialog from HTML when hidden
        this.$modal.on('hidden.bs.modal', () => {
            this.$modal.remove();
        });
        this.$modal.modal('show');

        this.filemanagerIframe = this.$modal[0].querySelector('iframe');
        this.filemanagerIframe.focus();
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
        if (this.filemanagerIframe.contentWindow === event.source) {
            const file = JSON.parse(event.data);
            this.pickFile(file);
        }
    }

    pickFile(file) {
        // Derrive correct url
        const fileUrl = `${file.fullPath}`;

        // Set url everywhere
        this.element.value = fileUrl;

        // Close modal
        this.$modal.modal('hide');
    }
}

document.querySelectorAll('[data-slide-form]').forEach((el) => {
    FilemanagerField.parseDocument(el);

    function onSubmit(evt) {
        if (el.elements.type.value === 'web') {
            el.elements.fit.value = null;
            el.elements.background_color.value = '#ffffff';
            el.elements.filemanager_image_path.value = null;
        } else if (el.elements.type.value == 'image') {
            el.elements.url.value = null;
        }
    }
    function setFormMode(evt) {
        if (el.elements.type.value === 'web') {
            // Disable fit, background_color and filemanager_image_path
            el.elements.fit.closest('.form-group').hidden = true;
            el.elements.fit.required = false;
            el.elements.background_color.closest('.form-group').hidden = true;
            el.elements.background_color.required = false;
            el.elements.filemanager_image_path.closest('.form-group').hidden = true;
            el.elements.filemanager_image_path.required = false;
            // Enable url
            el.elements.url.closest('.form-group').hidden = false;
            el.elements.url.required = true;
        } else if (el.elements.type.value == 'image')  {
            // Enable fit, background_color and filemanager_image_path
            el.elements.fit.closest('.form-group').hidden = false;
            el.elements.fit.required = true;
            el.elements.background_color.closest('.form-group').hidden = false;
            el.elements.background_color.required = true;
            el.elements.filemanager_image_path.closest('.form-group').hidden = false;
            el.elements.filemanager_image_path.required = true;
            // Disable url
            el.elements.url.closest('.form-group').hidden = true;
            el.elements.url.required = false;
        } else {
            // Unknown type, let's show as much as possible
            // Enable fit, background_color and url
            el.elements.fit.closest('.form-group').hidden = false;
            el.elements.fit.required = true;
            el.elements.background_color.closest('.form-group').hidden = false;
            el.elements.background_color.required = true;
            el.elements.url.closest('.form-group').hidden = false;
            el.elements.url.required = true;
        }
    }

    el.elements.type.addEventListener('input', setFormMode);
    el.addEventListener('submit', onSubmit);
    setFormMode();
});
