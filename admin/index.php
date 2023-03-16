<?php
set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
require_once 'include/init.php';
require_once 'include/forms/SlideForm.class.php';


/** Renders and processes CRUD operations for the Signup Model */
class AdminView extends ModelView
{
    protected $views = ['create', 'update', 'delete', 'list', 'preview'];
    protected $template_base_name = 'templates/admin/slide';

    /** 
     * Run the page, but only for logged in committee members. 
     * Non-admins are only allowed to see a list of their redirects
     */
    public function run_page() {
        if (!cover_session_logged_in())
            throw new HttpException(401, 'Unauthorized', sprintf('<a href="%s" class="btn btn-primary">Login and get started!</a>', cover_login_url()));
        elseif (!cover_session_in_committee(ADMIN_COMMITTEE))
            throw new HttpException(403, "You're not allowed to see this page!");
        elseif ($this->_view === 'preview')
            return $this->run_preview();
        else
            return parent::run_page();
    }

    /** Runs the preview view */
    protected function run_preview() {
        $object = $this->get_object();
        if ($object['type'] === 'web')
            return $this->redirect($object['url']);
        return $this->render_template($this->get_template(), ['object' => $object]);
    }

    /** Runs the list view */
    protected function run_list() {
        return $this->render_template($this->get_template(), ['objects' => $this->get_model()->get_slides()]);
    }

    protected function run_create() {
        $form = $this->get_form();
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
            $form->populate_fields($_GET);
        return $this->run_form($form);
    }

    /** Maps a valid form to its database representation */
    protected function process_form_data($data) {
        // Create url from filemanager_image_path
        if (!empty($data['filemanager_image_path']))
            $data['url'] = sprintf('%s/images/resize?f=%s&w=%d', COVER_FILEMANAGER_URL, $data['filemanager_image_path'], 1920);

        if (array_key_exists('filemanager_image_path', $data))
            unset($data['filemanager_image_path']);

        // Sanitize
        $data = $this->get_model()->sanitize_data($data);

        // Set null values
        if (empty($data['end']))
            $data['end'] = null;

        if (empty($data['description']))
            $data['description'] = null;

        // Set order on new objects
        if ($this->_view == 'create') {
            $data['order'] = $this->get_model()->get_next_order();
            var_dump($data['order']);
        }

        parent::process_form_data($data);
    }
}

// Create and run subdomain view
$view = new AdminView('_admin', 'Admin', get_model('Slide'), new SlideForm('slide'));
$view->run();
