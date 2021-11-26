<?php
set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
require_once 'include/init.php';

class SlideAPIView extends APIView
{
    protected $_view = null;
    protected $model;

    public function run_api() {
        if (!cover_session_logged_in())
            throw new HttpException(401, 'Unauthorized', sprintf('<a href="%s" class="btn btn-primary">Login and get started!</a>', cover_login_url()));

        $this->model = get_model('Slide');

        if (isset($_GET['view']))
            $this->_view = $_GET['view'];

        if ($this->_view === 'slide_order')
            return $this->run_slide_order();
        else if ($this->_view === 'slide_update')
            return $this->run_slide_update();
        else
            throw new HttpException(404, 'View not found!');
    }

    protected function run_slide_order() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            throw new HttpException(405, 'Method not allowed!');

        $data = json_decode(file_get_contents('php://input'), TRUE);

        if (!array_key_exists('order', $data))
            throw new HttpException(400, 'No order provided');

        foreach ($data['order'] as $order => $id)
            $this->model->update_by_id(intval($id), ['order' => intval($order)]);

        return ['status' => 'success'];
    }

    protected function run_slide_update() {
        $ALLOWED_FIELDS = ['is_active'];

        if ($_SERVER['REQUEST_METHOD'] !== 'PATCH')
            throw new HttpException(405, 'Method not allowed!');

        $data = json_decode(file_get_contents('php://input'), TRUE);

        // Verify ID and existence of slide
        if (!isset($_GET['id']))
            throw new HttpException(400, 'Please provide an ID!');

        $slide = $this->model->get_by_id($_GET['id']);

        if (empty($slide))
            throw new HttpException(404, 'No object found for id');

        // Verify submitted data
        $update = [];
        foreach ($data as $key => $value) {
            if ($key === 'id')
                continue;
            elseif (in_array($key, $ALLOWED_FIELDS))
                $update[$key] = $value;
            else
                throw new HttpException(400, sprintf('Updating "%s" field not allowed!', $key));
        }

        if (empty($update))
            throw new HttpException(400, 'No fields provided');

        $update = $this->model->sanitize_data($update);

        // Actual update
        $this->model->update_by_id($slide['id'], $update);

        return ['status' => 'success'];
    }
}

// Create and run json view
$view = new SlideAPIView();
$view->run();
