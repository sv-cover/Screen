<?php
set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
require_once 'include/init.php';

// Create and run home view
$view = new TemplateView('admin/help', 'Help');
$view->run();
