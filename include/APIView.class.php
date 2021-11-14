<?php
require_once 'include/utils.php';
require_once 'include/sentry.php';
require_once 'include/View.class.php';

/**
 * APIView: A class to manage a view for a JSON based API
 */
abstract class APIView extends View
{
    /** Run the view */
    public function run() {
        try {
            echo $this->render_response($this->run_api());
        } catch (Exception $e) {
            echo $this->render_response($this->run_exception($e));
        } catch (TypeError $e) {
            echo $this->render_response($this->run_exception($e));
        }
    }

    /** Run the API logic */
    abstract public function run_api();

    /** Handle exceptions encountered during running */
    protected function run_exception($e) {
        if ($e instanceof HttpException){
            $html_message = $e->getHtmlMessage();
            $status = $e->getStatus();
        } else {
            sentry_report_exception($e);
            $html_message = null;
            $status = 500;
        }
        
        http_response_code($status);

        return [
            'status' => 'error',
            'message' => $e->getMessage(),
        ];
    }

    protected function render_response($data) {
        header('Content-type: application/json');
        return json_encode($data);
    }
}
