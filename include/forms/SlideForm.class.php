<?php
require_once 'include/init.php';
require_once 'include/form.php';

class SlideForm extends Bootstrap3Form
{
    public function __construct($name) {
        $model = get_model('Slide');

        $fields = [
            'name' => new StringField('Name', false, ['maxlength' => 255]),
            'description' => new TextareaField('Description (optional)', true, ['maxlength' => 2048, 'placeholder' => 'Describe details about this that could be useful for future reference.']),
            'type' => new SelectField('Type', $model::$type_options, false, ['placeholder' => 'Select slide type']),
            'url' => new UrlField('URL', false),
            'background_color' => new StringField('Background colour', true, ['maxlength' => 7]),
            'fit' => new SelectField('Fit', $model::$fit_options, true),
            'duration' => new NumberField('Duration', false, ['min' => 0]),
            'frequency' => new NumberField('Frequency', false),
            'start' => new DateTimeField('Start', false),
            'end' => new DateTimeField('End (optional)', true),
            'is_active' => new CheckBoxField('Is active', true),
        ];

        parent::__construct($name, $fields);

        $this->populate_field('background_color', '#ffffff');
        $this->populate_field('duration', 20);
        $this->populate_field('frequency', 1);
        $this->populate_field('is_active', true);
        $this->populate_field('start', new DateTime());
    }

    /** Implement custom validation */
    public function validate() {
        $result = parent::validate();

        if ($this->get_value('type') == 'image' && empty($this->get_value('fit'))) {
            $this->get_field('fit')->errors[] = 'Fit is required for image slides.';
            $result = false && $result;
        }

        if (!empty($this->get_value('end')) && $this->get_value('end') <= $this->get_value('start')) {
            $this->get_field('end')->errors[] = 'End date needs to be after start date.';
            $result = false && $result;
        }

        return $result;
    }
}
