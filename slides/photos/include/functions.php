<?php
	require_once 'include/exception.php';
	require_once 'include/data.php';

	/**
	 * Format a string with php-style variables with optional modifiers.
	 * 
	 * Format description:
	 *     $var            Will be replaced by the value of $params['var'].
	 *     $var|modifier   Will be replaced by the value of modifier($params['var'])
	 *
	 * Example:
	 *     format_string('This is the $day|ordinal day', array('day' => 5))
	 *     results in "This is the 5th day"
	 *
	 * @param string $format the format of the string
	 * @param array $params a table of variables that will be replaced
	 * @return string a formatted string in which all the variables are replaced
	 * as far as they can be found in $params.
	 */
	function format_string($format, $params)
	{
		if (!(is_array($params) || $params instanceof ArrayAccess))
			throw new \InvalidArgumentException('$params has to behave like an array');

		$callback =  function($match) use ($params) {
			// If this key does not exist, just return the matched pattern
			if (!isset($params[$match[1]]))
				return $match[0];

			// Find the value for this key
			$value = $params[$match[1]];

			// If there is a modifier, apply it
			if (isset($match[2])) {
				$value = call_user_func($match[2], $value);
			}

			return $value;
		};

		return preg_replace_callback('/\$([a-z][a-z0-9_]*)(?:\|([a-z_]+))?\b/i', $callback, $format);
	}

	/**
	 * Give a number the correct suffix. E.g. 1, 2, 3 will become 1st, 2nd and
	 * 3th, depending on the locale returned bij i18n_get_locale().
	 *
	 * @param int $n the number
	 * @return string number with suffix.
	 */
	function ordinal($n) {
		if ($n == 1)
			return sprintf('%dst', $n);
		elseif ($n == 2)
			return sprintf('%dnd', $n);
		else
			return sprintf('%dth', $n);
	}

	/**
	 * Shortcut to add and remove query parameters from urls. First all parameters
	 * named in $remove are removed, then parameters from $add are recursively
	 * merged with the existing parameters in the url.
	 * 
	 * @param string $url the url to edit
	 * @param string[] $add key-value pairs of query parameters to add to the url
	 * @param string[] $remove keys of query parameters to remove.
	 * @return string
	 */
	function edit_url($url, array $add = array(), array $remove = array())
	{
		$query_start = strpos($url, '?');

		$fragment_start = strpos($url, '#');

		$query_end = $fragment_start !== false
			? $fragment_start
			: strlen($url);

		if ($query_start !== false)
			parse_str(substr($url, $query_start + 1, $query_end - $query_start), $query);
		else
			$query = array();

		foreach ($remove as $key)
			if (isset($query[$key]))
				unset($query[$key]);

		$query = array_merge_recursive($query, $add);

		$query_str = http_build_query($query);

		$out = $query_start !== false
			? substr($url, 0, $query_start)
			: $url;

		if ($query_str != '')
			$out .= '?' . $query_str;

		if ($fragment_start !== false)
			$out .= substr($url, $fragment_start);

		return $out;
	}

	/** @group Markup
	  * Format to be used in for example a textarea. This function 
	  * strips slashes and replaces htmlentities
	  * @text the text to be formatted
	  *
	  * @result the formatted text
	  */
	function markup_format_text($text) {
		$text = htmlspecialchars($text, ENT_COMPAT, WEBSITE_ENCODING);
		
		/*$text = str_replace('&','&amp;',$str);
		$text = str_replace('"','&quot;',$str);
		$text = str_replace('<','&lt;',$str);
		$text = str_replace('>','&gt;',$str);*/

		return $text;
	}
	
	function markup_format_attribute($text) {
		return htmlspecialchars($text, ENT_QUOTES, WEBSITE_ENCODING);
	}