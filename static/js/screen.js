window.DEFAULT_SLIDE_DURATION = 20;
window.DEFAULT_ERROR_DURATION = 60;
window.SLIDE_PROBE_INTERVAL = 5*60;
window.CONTROLS_TIMEOUT = 60;
window.RESTART_TIME = '6:00';
window.ITERATION_PARAM_NAME = 'cover-screen-iteration';


class Slide {
    static _template;

    constructor(data, progress) {
        this.data = data;
        this.progress = progress;
    }

    getTemplate() {
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
        return this.data.duration || window.DEFAULT_SLIDE_DURATION;
    }

    get url() {
        const url = new URL(this.data.url);
        let searchParams = url.searchParams;

        // Set iteration, compensated for frequency to prevent (non-visual) moiré patterns
        searchParams.set(
            window.ITERATION_PARAM_NAME,
            Math.floor(this.progress.iteration / this.data.frequency)
        );
        url.search = searchParams.toString();
        return url;
    }

    show() {
        this.element.hidden = false;
    }

    hide() {
        this.element.hidden = true;
    }

    remove() {
        this.element.remove();
    }
}


class ErrorSlide extends Slide {
    static templateSelector = '[data-error-template]';

    render() {
        const element = this.getTemplate();

        let titleElement = element.querySelector('[data-error-title]');
        titleElement.innerText = this.data.title || 'Something went wrong!';

        let messageElement = element.querySelector('[data-error-message]');
        messageElement.innerText = this.data.message || 'Unknown error. Retrying in a bit…';

        const progressElement = element.querySelector('[data-error-progress]');
        progressElement.max = this.duration;

        return element;
    }

    get duration() {
        // Ignore custom slide duration. Don't show error for longer than necessary.
        if (this.data.errorType === 'slide')
            return window.DEFAULT_SLIDE_DURATION;
        return window.DEFAULT_ERROR_DURATION;
    }

    show() {
        super.show();
        if (this.data.showErrorCountdown)
            this.countdown();
    }

    countdown() {
        /* This implementation deviates from the codestyle in the rest of this file, 
         * but this is less cumbersome. So I like it more this way.
         */
        const progressElement = this.element.querySelector('[data-error-progress]');
        progressElement.max = this.duration;
        progressElement.hidden = false;

        const to = new Date().getTime() + this.duration * 1000;
        const handleCountdown = () => {
            const now = new Date().getTime();
            const diff = to - now;
            progressElement.value = Math.floor(diff / 1000);
            if (diff < 0)
                clearInterval(interval);
        };
        handleCountdown();
        const interval = setInterval(handleCountdown.bind(this), 1000);
    }
}


class ImageSlide extends Slide {
    static templateSelector = '[data-image-slide-template]';

    render() {
        const element = this.getTemplate();

        element.style.setProperty('--background-color', this.data.background_color);
        element.style.setProperty('--object-fit', this.data.fit);

        let imgElement = element.querySelector('img');
        imgElement.src = this.url;
        return element;
    }
}


class WebSlide extends Slide {
    static templateSelector = '[data-web-slide-template]';

    render() {
        const element = this.getTemplate();

        element.style.setProperty('--background-color', this.data.background_color);

        let iframeElement = element.querySelector('iframe');
        iframeElement.src = this.url;
        return element;
    }
}


class Screen {
    constructor() {
        this.element = document.querySelector('.screen');
        this.containerElement = this.element.querySelector('.slides');
        this.progressElement = this.element.querySelector('.iteration-progress');
        this.pausedIndicatorElement = this.element.querySelector('.paused-indicator');
        this.controller = new ScreenController(this);
    }

    async *slideGenerator() {
        let slides;

        this.iteration = 0;

        do {
            try {
                slides = await this.fetchSlides();
            } catch (error) {
                yield new ErrorSlide({
                    message: 'Error while loading slides. Retrying in a bit…',
                    errorType: 'api',
                    showErrorCountdown: true
                }, {idx: 0, total: 0, iteration: this.iteration});
                continue;
            }

            // Ensure slides appear once every slide.frequency iterations
            // Offset by slide index, this way the slides will be spread over multiple iterations
            slides = slides.filter((slide, idx) => (this.iteration % slide.frequency) == (idx % slide.frequency))

            // Show error if no slides to render. Don't continue so iteration counter will be increased
            if (slides.length === 0)
                yield new ErrorSlide({
                    title: 'No slides to show!',
                    message: 'Retrying in a bit…',
                    errorType: 'api',
                    showErrorCountdown: true
                }, {idx: 0, total: 0, iteration: this.iteration});

            // Render slides
            for (let idx = 0; idx < slides.length; idx ++)
                yield this.createSlide(
                    slides[idx],
                    {idx: idx, total: slides.length, iteration: this.iteration}
                );

            this.iteration ++;
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
        if (this.slideProbeInterval)
            clearInterval(this.slideProbeInterval);

        if (!this.next)
            this.next = await this.loadSlide();


        if (this.current)
            this.current.hide();

        this.next.show();

        if (this.current)
            this.current.remove();

        if (this.next.progress.total > 1)
            this.progressElement.hidden = false;
        else
            this.progressElement.hidden = true;
        this.progressElement.max = Math.max(this.next.progress.total - 1, 0);
        this.progressElement.value = this.next.progress.idx;

        if (this.autoPlay)
            this.nextSlideTimout = setTimeout(
                this.nextSlide.bind(this),
                this.next.duration * 1000
            );

        this.slideProbeInterval = setInterval(
            this.handleSlideProbeInterval.bind(this),
            window.SLIDE_PROBE_INTERVAL * 1000
        );

        this.current = this.next;
        this.next = await this.loadSlide();
    }

    createSlide(data, progress) {
        if (data.type === 'image')
            return new ImageSlide(data, progress);
        else if (data.type === 'web')
            return new WebSlide(data, progress);
        return new ErrorSlide({
            ...data,
            message: `Unkown slide type "${data.type}" for slide "${data.name}" (id: ${data.id}).`,
            errorType: 'slide',
            showErrorCountdown: false,
        }, progress);
    }

    async loadSlide() {
        const slide = (await this.slides.next()).value;
        slide.element.hidden = true;
        this.containerElement.append(slide.element);
        return slide;
    }

    async run() {
        // Remove all current slides (if any)
        while (this.containerElement.firstChild)
            this.containerElement.removeChild(this.containerElement.firstChild);

        // Reset values
        this.current = null;
        this.next = null;
        this.autoPlay = true;
        this.pausedIndicatorElement.hidden = true;

        // Initiate slide generator
        this.slides = this.slideGenerator();

        // Start slideshow
        this.nextSlide();

        // Schedule periodic restart if not yet scheduled
        // This restart prevents 
        if (!this.restartTimeout && !this.restartInterval) {
            const now = new Date();
            let firstRestart = new Date(now.getFullYear(), now.getMonth(), now.getDate(), ...window.RESTART_TIME.split(':')) - now;
            if (firstRestart < 0)
                firstRestart += 24*60*60*1000;
            this.restartTimeout = setTimeout(this.handleRestartInterval.bind(this), firstRestart);
        }
    }

    pause() {
        if (this.nextSlideTimout)
            clearTimeout(this.nextSlideTimout);
        this.autoPlay = false;
    }

    resume() {
        this.autoPlay = true;
        this.nextSlide();
    }

    async handleSlideProbeInterval() {
        if (!this.autoPlay) {
            // Show pause indicator just so it's clear why the screen is "stuck" on one slide
            this.pausedIndicatorElement.hidden = false;
        } else {
            /* Restart if 
             * 1. slide is gone
             * 2. slide duration changed (use configured duration, rather than calculated duration for current slide)
             * 3. slide end time changed
             */
            const slides = await this.fetchSlides();
            const slideData = slides.find(s => s.id == this.current.data.id);
            const shouldRestart = !slideData ||
                slideData.duration != this.current.data.duration ||
                slideData.end != this.current.data.end;
            if (shouldRestart) 
                this.run();
        }
    }

    handleRestartInterval() {
        // Periodically restart slideshow, to "clean up" (even though it shouldn't be necessary)
        if (!this.restartInterval)
            this.restartInterval = setInterval(this.handleRestartInterval.bind(this), 24*60*60*1000);
        // TODO: Is this soft restart enough? Or would window.location.reload() be better?
        this.run();
    }
}


class ScreenController {
    constructor(screen) {
        this.screen = screen;
        this.controlsElement = screen.element.querySelector('.controls');
        this.initControls(this.controlsElement);
        document.addEventListener('keydown', this.handleKeydown.bind(this));
        document.addEventListener('fullscreenchange', this.handleFullscreenChange.bind(this));
        document.addEventListener('mousemove', this.handleMouseMove.bind(this));
        document.addEventListener('click', this.handleClick.bind(this));
    }

    initControls(el) {
        this.playButton = el.querySelector('[data-play-button]');
        this.playButton.addEventListener('click', this.handlePlay.bind(this));
        this.playButton.hidden = true;

        this.pauseButton = el.querySelector('[data-pause-button]');
        this.pauseButton.addEventListener('click', this.handlePause.bind(this));
        this.pauseButton.hidden = false;

        this.nextButton = el.querySelector('[data-next-button]');
        this.nextButton.addEventListener('click', this.handleNext.bind(this));
        this.nextButton.hidden = false;

        this.enterFullscreenButton = el.querySelector('[data-enter-fullscreen-button]');
        this.enterFullscreenButton.addEventListener('click', this.handleEnterFullscreen.bind(this));
        this.enterFullscreenButton.hidden = false;

        this.exitFullscreenButton = el.querySelector('[data-exit-fullscreen-button]');
        this.exitFullscreenButton.addEventListener('click', this.handleExitFullscreen.bind(this));
        this.exitFullscreenButton.hidden = true;

        this.controlsButton = el.querySelector('[data-controls-button]');
        this.controlsButton.addEventListener('click', this.hideControls.bind(this));
        this.controlsButton.hidden = false;

        this.showControls();
    }

    handleNext(event) {
        this.screen.nextSlide();
    }

    handlePlay(event) {
        this.screen.resume();
        this.playButton.hidden = true;
        this.pauseButton.hidden = false;
    }

    handlePause(event) {
        this.screen.pause();
        this.pauseButton.hidden = true;
        this.playButton.hidden = false;
    }

    handleEnterFullscreen(event) {
        this.screen.element.requestFullscreen();
    }

    handleExitFullscreen(event) {
        document.exitFullscreen();
    }

    handleFullscreenChange(event) {
        if (document.fullscreenElement) {
            this.enterFullscreenButton.hidden = true;
            this.exitFullscreenButton.hidden = false;
        } else {
            this.enterFullscreenButton.hidden = false;
            this.exitFullscreenButton.hidden = true;
        }
    }

    handleClick(event) {
        if (!this.controlsElement.contains(event.target))
            this.showControls();
    }

    handleMouseMove(event) {
        // Only show after substantial movement
        if (Math.abs(event.movementX) + Math.abs(event.movementY) > 100)
            this.showControls();
    }

    handleKeydown(event) {
        // Don't prevent normal keyboard shortcuts
        if (event.shiftKey || event.metaKey || event.ctrlKey)
            return;

        switch (event.key) {
            case ' ':
            case 'k':
            case 'K':
                this.togglePlay(event);
                break;
            case 'f':
            case 'F':
                this.toggleFullscreen(event);
                break;
            case 'Right': // IE/Edge specific value
            case 'ArrowRight':
                this.handleNext(event);
                break;
        }
    }

    hideControls() {
        this.controlsElement.hidden = true;
        this.screen.element.classList.remove('has-cursor');
    }

    showControls() {
        if (this.controlsTimeout)
            clearTimeout(this.controlsTimeout);
        this.controlsElement.hidden = false;
        this.screen.element.classList.add('has-cursor');
        this.controlsTimeout = setTimeout(this.hideControls.bind(this), window.CONTROLS_TIMEOUT * 1000);
    }

    togglePlay(event) {
        if (this.screen.autoPlay)
            this.handlePause(event);
        else
            this.handlePlay(event);
    }

    toggleFullscreen(event) {
        if (document.fullscreenElement)
            this.handleExitFullscreen(event);
        else
            this.handleEnterFullscreen(event);
    }
}


(new Screen()).run();
