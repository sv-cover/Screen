<?php
error_reporting(E_ERROR | E_PARSE);

/** 
This page (optimized for full screen display on a 1080p screen) provides an overview of the current weather in Groningen.
The weather data is provided by buienradar.nl (documentation https://www.buienradar.nl/overbuienradar/gratis-weerdata).
The icons used are a (slightly) modified version of the ones created by Spovv (http://spovv.com/free-and-premium-resources/#toggle-id-14-closed).

Author: Martijn Luinstra (martijnluinstra@gmail.com)
2014-06-06: created
2021-11-15: improved
*/

function escape_text($text) {
    return htmlspecialchars($text, ENT_COMPAT, 'utf-8');
}

function clip($value, $min, $max) {
    return min($max, max($min, $value));
}

// http://schepers.cc/getting-to-the-point
function catmullrom_to_bezier($pts) {
    $bezier = [];
    $length = count($pts);

    for ($i = 0; count($pts) - 1 > $i; $i++) {
        if ($i == 0)
            $p = [$pts[$i],     $pts[$i], $pts[$i + 1], $pts[$i + 2]];
        elseif (count($pts) - 2 == $i)
            $p = [$pts[$i - 1], $pts[$i], $pts[$i + 1], $pts[$i + 1]];
        else
            $p = [$pts[$i-1],   $pts[$i], $pts[$i + 1], $pts[$i + 2]];

        $bezier[] = [
            [
                'x' => (-$p[0]['x'] + 6 * $p[1]['x'] + $p[2]['x']) / 6,
                'y' => (-$p[0]['y'] + 6 * $p[1]['y'] + $p[2]['y']) / 6
            ],
            [
                'x' => ($p[1]['x']  + 6 * $p[2]['x'] - $p[3]['x']) / 6,
                'y' => ($p[1]['y']  + 6 * $p[2]['y'] - $p[3]['y']) / 6
            ],
            $p[2]
        ];
    }

    return $bezier;
}

function generate_forecast_graph($forecast, $x_min, $x_max, $y_min, $y_max) {
    // rescale points, y is inverted compared to svg coordinates
    $pts = [];
    foreach($forecast as $idx => $point)
        $pts[] = [
            'x' => ($idx * ($x_max - $x_min) / (count($forecast)-1)) + $x_min,
            'y' => $y_max - (($point[0] * ($y_max - $y_min) / 255))
        ];

    // Convert points to bezier according to simplified catmull-rom
    $bez = catmullrom_to_bezier($pts);

    // Generate svg path and clip to boundaries
    // First, render starting point
    $svg_path = sprintf(
        'M%f,%f',
        clip($pts[0]['x'], $x_min, $x_max),
        clip($pts[0]['y'], $y_min, $y_max)
    );
    // Now render bezier points
    foreach($bez as $p)
        $svg_path .= sprintf(
            'C%f,%f %f,%f %f,%f',
            clip($p[0]['x'], $x_min, $x_max), clip($p[0]['y'], $y_min, $y_max), 
            clip($p[1]['x'], $x_min, $x_max), clip($p[1]['y'], $y_min, $y_max), 
            clip($p[2]['x'], $x_min, $x_max), clip($p[2]['y'], $y_min, $y_max)
        );
    return $svg_path;
}

function get_data() {
    $context = stream_context_create(['http'=> ['timeout' => 3]]);    
    $json_str = file_get_contents('https://data.buienradar.nl/2.0/feed/json', false, $context);
    if (empty($json_str))
        throw new Exception('Cannot load weather data from API');

    $json_data = json_decode($json_str);

    // Find the data of Groningen
    foreach ($json_data->actual->stationmeasurements as $data) {
        if ($data->stationid == 6280)
            break;
    }

    // Set sunrise and sunset times
    $data->sunrise = $json_data->actual->sunrise;
    $data->sunset = $json_data->actual->sunset;

    return $data;
}

function get_now_offset($forecast) {
    $start = strtotime($forecast[0][1]);
    $end = strtotime($forecast[count($forecast) - 1][1]);
    return (time() - $start) / ($end - $start);
}

function get_time_class($data) {
    $sunrise = strtotime($data->sunrise);
    $sunset = strtotime($data->sunset);
    if (time() > $sunrise && time() < $sunset)
        return 'day';
    return 'night';
}

function get_icon($data) {
    // Get icon, default to unknown.svg
    $icon = 'img/unknown.svg';
    if (preg_match('/\/([a-z]?).png$/', $data->iconurl, $matches))
        $icon = 'img/'.$matches[1].'.svg';
    if(!is_file($icon))
        $icon = 'img/unknown.svg';
    return $icon;
}

function get_rain_forecast() {
    $context = stream_context_create(['http'=> ['timeout' => 3]]);    

    // Get rain forecast from the coordinates of the data
    $rain_data = file_get_contents('https://gpsgadget.buienradar.nl/data/raintext?lat=53.24&lon=6.54', false, $context);
    // Test data in case of a boring forecast
    // $rain_data = "60|00:05\n50|00:10\n128|00:15\n115|00:20\n100|00:25\n255|00:30\n128|00:35\n100|00:40\n060|00:45\n050|00:50\n090|00:55\n130|01:00\n080|01:05\n015|01:10\n020|01:15\n025|01:20\n000|01:25\n000|01:30\n000|01:35\n000|01:40\n050|01:45\n130|01:50\n180|01:55\n200|02:00\n150|02:05\n";

    if (empty($rain_data))
        throw new Exception('Cannot load rain data from API');

    if (!is_string($rain_data))
        throw new Exception('Rain data is not a string');

    $rain = [];

    // Make array from rain forecast string
    foreach(explode("\n", $rain_data) as $point){
        if(empty($point)) continue;
        $rain[] = explode('|', $point);
    }

    return $rain;
}

$error = null;
try {
    $data = get_data();
    $rain_forecast = get_rain_forecast();
} catch (Exception $e) {
    $error = $e;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weather at Zernike</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?= get_time_class($data) ?>">
<?php if (!empty($error)): ?>
<div class="error">
    <h1>Cannot load weather data!</h1>
    <p>An error was encountered while loading weather data. Here's more information:</p>
    <p><?= $error->getMessage() ?></p>
</div>
<?php else: ?>
<div class="weather-slide">
    <div class="main">
        <!-- Include weather icon -->
        <?= @file_get_contents(get_icon($data)) ?>
    </div>
    <div class="sidebar">
        <div class="temperature">
            <p class="">
                <!-- Temperature in degrees Celcius (rounded to 0 decimals) -->
                <?= round(floatval($data->temperature)) ?>&deg;C
            </p>
            <p class="feel">
                Feels like <?= round(floatval($data->feeltemperature)) ?>&deg;C
            </p>
        </div>
        <div class="wind">
            <!-- Wind direction -->
            <svg class="icon" viewbox="0 0 200 200">
                <g>
                    <polygon points="100,25 67,175 100,160 133,175" transform="rotate(<?= (intval($data->winddirectiondegrees) + 180) % 360 ?>, 100, 100)"/>
                </g>
            </svg>
            <!-- Wind speed in Beaufort -->
            <?= escape_text($data->windspeedBft) ?>
        </div>
        <div class="rain">
            <svg class="rain-graph" viewbox="0 0 400 400">
                <defs>
                    <linearGradient id="rain-graph-fill-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                      <stop class="stop-1" offset="0%"/>
                      <stop class="stop-2" offset="100%"/>
                    </linearGradient>
                </defs>
                <!-- Labels of the X-axis, leave some space for curves to extend below the baseline -->
                <text x="0" y="398" class="label" font-size="40"><?= escape_text($rain_forecast[0][1]) ?></text>
                <text x="400" y="398" text-anchor="end" class="label" font-size="40"><?= escape_text($rain_forecast[count($rain_forecast) - 1][1]) ?></text>
                <?php $forecast_path = generate_forecast_graph($rain_forecast, 3, 397, 3, 350); ?>
                <path class="graph-fill" d="<?= $forecast_path ?> L397,350 L3,350Z"/>
                <!-- Draw a 394*347 grid (leave space for linewidth) -->
                <g>
                    <line class="grid" x1="3"   y1="3" x2="3"   y2= "350" stroke-width="6"/>
                    <line class="grid" x1="50"  y1="3" x2="50"  y2= "350" stroke-width="4"/>
                    <line class="grid" x1="100" y1="3" x2="100" y2= "350" stroke-width="4"/>
                    <line class="grid" x1="150" y1="3" x2="150" y2= "350" stroke-width="4"/>
                    <line class="grid" x1="200" y1="3" x2="200" y2= "350" stroke-width="6"/>
                    <line class="grid" x1="250" y1="3" x2="250" y2= "350" stroke-width="4"/>
                    <line class="grid" x1="300" y1="3" x2="300" y2= "350" stroke-width="4"/>
                    <line class="grid" x1="350" y1="3" x2="350" y2= "350" stroke-width="4"/>
                    <line class="grid" x1="397" y1="3" x2="397" y2= "350" stroke-width="6"/>
                </g>
                <!-- Plot the rain forecast data -->
                <path class="graph" d="<?= $forecast_path ?>" stroke-width="6"/>
                <?php $now_offset = get_now_offset($rain_forecast); ?>
                <?php if ($now_offset >= 0 && $now_offset < 1): ?>
                    <line class="now" x1="<?= ($now_offset * 394) + 3?>" y1="5" x2="<?= ($now_offset * 394) + 3?>" y2= "350" stroke-width="6" stroke-dasharray="8,20"/>
                <?php endif ?>
            </svg>
        </div>
    </div>
    <div class="credits">
        Source: Buienradar.nl
    </div>
</div>
<?php endif ?>
</body>
</html>
