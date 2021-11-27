<?php
require_once 'include/init.php';
require_once 'include/form.php';


class SlideForm extends Bootstrap3Form
{
    public function __construct($name) {
        $model = get_model('Slide');

        $fields = [
            // name                        | type         | label                    | optional | attributes
            'name'                   => new StringField  ('Name',                        false, ['maxlength' => 255]),
            'description'            => new TextareaField('Description (optional)',      true,  ['maxlength' => 2048, 'placeholder' => 'Describe details about this that could be useful for future reference.']),
            'type'                   => new SelectField  ('Type', $model::$type_options, false, ['placeholder' => 'Select slide type']),
            'url'                    => new UrlField     ('URL',                         true,  ['maxlength' => 2048]),
            'filemanager_image_path' => new StringField  ('Image',                       true,  ['maxlength' => 2048]),
            'background_color'       => new StringField  ('Background colour',           true,  ['maxlength' => 7]),
            'fit'                    => new SelectField  ('Fit', $model::$fit_options,   true),
            'duration'               => new NumberField  ('Duration',                    false, ['min' => 0]),
            'frequency'              => new NumberField  ('Frequency',                   false),
            'start'                  => new DateTimeField('Start',                       false),
            'end'                    => new DateTimeField('End (optional)',              true),
            'is_active'              => new CheckBoxField('Slide is visible',            true),
        ];

        parent::__construct($name, $fields);

        $this->populate_field('background_color', '#ffffff');
        $this->populate_field('duration', 20);
        $this->populate_field('frequency', 1);
        $this->populate_field('is_active', true);
        $this->populate_field('start', new DateTime());
    }

    /** Updates the values of multiple fields */
    public function set_values($values) {
        $should_patch_url = !array_key_exists('filemanager_image_path', $values)
            && array_key_exists('url', $values)
            && strpos($values['url'], COVER_FILEMANAGER_URL) === 0;

        if ($should_patch_url) {
            // Remove base url + /
            $values['filemanager_image_path'] = substr($values['url'], strlen(COVER_FILEMANAGER_URL) + 1);
            unset($values['url']);
        }

        foreach ($values as $field => $value) {
            if (isset($this->fields[$field]))
                $this->fields[$field]->value = $value;
        }
    }

    /** Implement custom validation */
    public function validate() {
        $result = parent::validate();

        // Validate exclusive or requirement for url and filemanager_image_path
        if ($this->get_value('type') == 'web' && empty($this->get_value('url'))) {
            $this->get_field('url')->errors[] = 'URL is required for web page slides.';
            $result = false && $result;
        }

        if ($this->get_value('type') == 'image' && empty($this->get_value('url')) && empty($this->get_value('filemanager_image_path'))) {
            // filemanager_image_path is managed by JS, so still need to support url for JS blockers
            $this->get_field('url')->errors[] = 'URL or Image is required for image slides.';
            $this->get_field('filemanager_image_path')->errors[] = 'URL or image is required for image slides.';
            $result = false && $result;
        }

        if (!empty($this->get_value('url')) && !empty($this->get_value('filemanager_image_path'))) {
            $this->get_field('url')->errors[] = 'Cannot set both URL and image.';
            $this->get_field('filemanager_image_path')->errors[] = 'Cannot set both URL and image.';
        }

        // Validate fit
        if ($this->get_value('type') == 'image' && empty($this->get_value('fit'))) {
            $this->get_field('fit')->errors[] = 'Fit is required for image slides.';
            $result = false && $result;
        }

        // Validate end date
        if (!empty($this->get_value('end')) && $this->get_value('end') <= $this->get_value('start')) {
            $this->get_field('end')->errors[] = 'End date needs to be after start date.';
            $result = false && $result;
        }

        if (!empty($this->get_value('filemanager_image_path'))) {
            // Only accept image file (using naive extension check)
            $ext = pathinfo(parse_url($this->get_value('filemanager_image_path'), PHP_URL_PATH), PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), ALLOWED_IMAGE_EXTENSIONS)) {
                $this->get_field('filemanager_image_path')->errors[] = sprint('Only the following extensions are allowed: %s', join(', ', ALLOWED_IMAGE_EXTENSIONS));
                $result = false && $result;
            }
        }

        return $result;
    }
}
