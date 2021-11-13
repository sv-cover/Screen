<?php
set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
require_once 'include/init.php';
require_once 'include/forms/SlideForm.class.php';


/** Renders and processes CRUD operations for the Signup Model */
class AdminView extends ModelView
{
    protected $views = ['create', 'update', 'delete', 'list'];
    protected $template_base_name = 'templates/admin/slide';

    /** 
     * Run the page, but only for logged in committee members. 
     * Non-admins are only allowed to see a list of their redirects
     */
    public function run_page() {
        if (!cover_session_logged_in())
            throw new HttpException(401, 'Unauthorized', sprintf('<a href="%s" class="btn btn-primary">Login and get started!</a>', cover_login_url()));
        else if (!cover_session_in_committee(ADMIN_COMMITTEE))
            throw new HttpException(403, "You're not allowed to see this page!");
        else
            return parent::run_page();
    }

    /** Maps a valid form to its database representation */
    protected function process_form_data($data) {
        // Convert booleans to tinyints
        $data['is_active'] = empty($data['is_active']) ? 0 : 1;

        // Convert datetime to strings
        $data['start'] = $data['start']->format('Y-m-d H:i');
        if (!empty($data['end']))
            $data['end'] = $data['end']->format('Y-m-d H:i');
        else
            $data['end'] = null;

        // Set null values
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
