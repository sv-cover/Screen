document.querySelectorAll('[data-slide-form]').forEach((el) => {
    function onSubmit(evt) {
        if (el.elements.type.value == 'web') {
            el.elements.fit.value = null;
            el.elements.background_color.value = '#ffffff';
        }
    }
    function setFormMode(evt) {
        if (el.elements.type.value == 'web') {
            el.elements.fit.closest('.form-group').hidden = true;
            el.elements.background_color.closest('.form-group').hidden = true;
        } else {
            el.elements.fit.closest('.form-group').hidden = false;
            el.elements.background_color.closest('.form-group').hidden = false;
        }
    }
    el.elements.type.addEventListener('input', setFormMode);
    el.addEventListener('submit', onSubmit);
    setFormMode();
});
