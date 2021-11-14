<?php
require_once 'include/init.php';

class ScreenAPIView extends APIView
{
    public function run_api() {
        $model = get_model('Slide');
        return $model->get_active_slides();
    }
}

// Create and run json view
$view = new ScreenAPIView();
$view->run();
