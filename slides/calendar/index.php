<?php
// error_reporting(E_ERROR | E_PARSE);

require_once '../../include/config.php';

function escape_text($text) {
    return htmlspecialchars($text, ENT_COMPAT, 'utf-8');
}

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

function agenda_period_for_display($iter) {
    // If there is no till date, leave it out
    if (!$iter['tot'] || $iter['tot'] == $iter['van']) {
        
        // There is no time specified
        if ($iter['vanuur'] + 0 == 0)
            $format = '$from_dayname $from_day|ordinal of $from_month';
        else
            $format = '$from_dayname $from_day|ordinal of $from_month, $from_time';
    }

    /* Check if date part (not time) is not the same */
    else if (substr($iter['van'], 0, 10) != substr($iter['tot'], 0, 10)) {
        $format = '$from_dayname $from_day|ordinal of $from_month $from_time till $till_dayname $till_day|ordinal of $till_month $till_time';
    } else {
        $format = '$from_dayname $from_day|ordinal of $from_month, $from_time till $till_time';
    }

    $days = Array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
    $months = Array('no', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');

    $van = new DateTime($iter['van']);
    $tot = new DateTime($iter['tot'] ? $iter['tot'] : $iter['van']);
    
    return format_string($format, array(
        'from_dayname' => $days[$van->format('w')],
        'from_day' => $van->format('j'),
        'from_month' => $months[$van->format('n')],
        'from_time' => $van->format('H:i'),
        'till_dayname' => $days[$tot->format('w')],
        'till_day' => $tot->format('j'),
        'till_month' => $months[$tot->format('n')],
        'till_time' => $tot->format('H:i')
    ));
}


$json_data = file_get_contents(COVER_API_URL.'?method=agenda');
$agenda = json_decode($json_data, true);

// Only 10 items fit on the screen at the same time.
$punten = array_slice($agenda, 0, 10);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cover Calendar</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div style="overflow: hidden">
    <h1 style="text-indent: 20px;">Agenda</h1>
    <?php foreach ($punten as $punt): ?>
    <div style="display: block; float: left; width: 840px; height: 140px; margin: 0 20px 0 40px; padding: 20px; border-bottom: 1px solid #ccc">
        <h3><?=escape_text($punt['kop'])?></h3>
        <span class="date"><?=agenda_period_for_display($punt) ?></span>
        <?php if ($punt['locatie']): ?>
            <span class="location">in <?= escape_text($punt['locatie']) ?></span>
        <?php endif ?>
    </div>
    <?php endforeach ?>
</div>
</body>
</html>
