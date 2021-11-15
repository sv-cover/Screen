window.DEFAULT_DURATION = 20;

class Slide {
    static _template;

    constructor(data, progress) {
        this.data = data;
        this.progress = progress;
    }

    get_template() {
        if (!this.constructor._template)
            this.constructor._template = document.querySelector(this.constructor.templateSelector);
        return this.constructor._template.content.firstElementChild.cloneNode(true);
    }

    get element() {
        if (!this._element)
            this._element = this.render();
        return this._element;
    }

    get duration() {
        return this.data.duration || window.DEFAULT_DURATION;
    }
}

class ErrorSlide extends Slide {
    static templateSelector = '[data-error-template]';

    render() {
        const element = this.get_template();
        element.append(this.data.message || 'Unknown error');
        return element;
    }
}

class ImageSlide extends Slide {
    static templateSelector = '[data-image-slide-template]';

    render() {
        const element = this.get_template();

        element.style.setProperty('--background-color', this.data.background_color);
        element.style.setProperty('--object-fit', this.data.fit);

        let imgElement = element.querySelector('img');
        imgElement.src = this.data.url;
        return element;
    }
}

class WebSlide extends Slide {
    static templateSelector = '[data-web-slide-template]';

    render() {
        const element = this.get_template();

        element.style.setProperty('--background-color', this.data.background_color);

        let iframeElement = element.querySelector('iframe');
        iframeElement.src = this.data.url;
        return element;
    }
}

class Screen {
    constructor() {
        this.containerElement = document.querySelector('.slides');
        this.progressElement = document.querySelector('.iteration-progress');
        document.addEventListener('keydown', this.handleKeydown.bind(this));
    }

    async *slideGenerator() {
        let slides;

        this.iteration = 0;

        do {
            try {
                slides = await this.fetchSlides();
            } catch (error) {
                yield new ErrorSlide(
                    {message: 'Error while loading slides. Retrying in a bit…'},
                    {idx: 0, total: 0, iteration: this.iteration}
                );
                continue;
            }

            // Ensure slides appear once every slide.frequency iterations
            // Offset by slide index, this way the slides will be spread over multiple iterations
            slides = slides.filter((slide, idx) => (this.iteration % slide.frequency) == (idx % slide.frequency))

            // Show error if no slides to render. Don't continue so iteration counter will be increased
            if (slides.length === 0)
                yield new ErrorSlide(
                    {message: 'No slides to show. Retrying in a bit…'},
                    {idx: 0, total: 0, iteration: this.iteration}
                );

            // Render slides
            for (let idx = 0; idx < slides.length; idx ++)
                yield this.createSlide(
                    slides[idx],
                    {idx: idx, total: slides.length, iteration: this.iteration}
                );

            this.iteration ++;
            // TODO: periodically reset iteration counter
        } while (true);
    }

    async fetchSlides() {
        const response = await fetch('api.php');
        if (!response.ok)
            throw new Error('Unsuccessful response');
        return await response.json();
    }

    async nextSlide() {
        if (this.nextSlideTimout)
            clearTimeout(this.nextSlideTimout);

        if (!this.next)
            this.next = await this.loadSlide();

        if (this.current)
            this.current.element.hidden = true;

        this.next.element.hidden = false;

        if (this.current)
            this.current.element.remove();

        if (this.next.progress.total > 1)
            this.progressElement.hidden = false;
        else
            this.progressElement.hidden = true;
        this.progressElement.max = Math.max(this.next.progress.total - 1, 0);
        this.progressElement.value = this.next.progress.idx;

        this.nextSlideTimout = setTimeout(this.nextSlide.bind(this), this.next.duration * 1000);

        this.current = this.next;
        this.next = await this.loadSlide();
    }

    createSlide(data, progress) {
        if (data.type === 'image')
            return new ImageSlide(data, progress);
        else if (data.type === 'web')
            return new WebSlide(data, progress);
        return new ErrorSlide({...data, message: `Unkown slide type: ${data.type}`}, progress);
    }

    async loadSlide() {
        const slide = (await this.slides.next()).value;
        slide.element.hidden = true;
        this.containerElement.append(slide.element);
        return slide;
    }

    async run() {
        this.slides = this.slideGenerator();
        this.nextSlide();
    }

    handleKeydown(event) {
        // Don't prevent normal keyboard shortcuts
        if (event.shiftKey || event.metaKey || event.ctrlKey)
            return;

        switch (event.key) {
            case "Right": // IE/Edge specific value
            case "ArrowRight":
                this.nextSlide();
                break;
        }
    }
}

(new Screen('.screen')).run();
