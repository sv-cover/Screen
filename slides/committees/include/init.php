<?php
	ini_set('display_errors', true);
	
	error_reporting(E_ALL ^ E_NOTICE ^ E_USER_NOTICE ^ E_DEPRECATED ^ E_STRICT);

	define('WEBSITE_ENCODING', 'UTF-8');

	require_once '../../include/config.php';

	function get_config_value($key, $default = '') {
		if ($key === 'db')
			return defined('COVER_WEBSITE_DB') ? COVER_WEBSITE_DB : $default;

		if ($key === 'url_to_cover')
			return defined('COVER_WEBSITE_URL') ? COVER_WEBSITE_URL : $default;

		if ($key === 'path_to_committee_photos')
			return defined('COMMITTEE_PHOTOS_PATH') ? COMMITTEE_PHOTOS_PATH : $default;

		return $default;
	}

	set_include_path ( dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' );
	
	require_once 'include/functions.php';

	date_default_timezone_set('Europe/Amsterdam');
