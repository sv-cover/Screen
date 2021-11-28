<?php
// © Nick Ubels & Alex-Jan Sigtermans
// Used by Study Assosication Cover with permission
// http://nickubels.nl
//
// Updated by Martijn Luinstra
// API Documentation: https://github.com/skywave/KV78Turbo-OVAPI/wiki

// Set PHP variables
date_default_timezone_set('Europe/Amsterdam');

// Defining constants
define('API_URL', 'http://v0.ovapi.nl');
define('MAX_ENTRIES', 6);

// Grabbing page from API
function grabPage($path){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$path);
    curl_setopt($ch, CURLOPT_FAILONERROR,1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $retValue = curl_exec($ch);
    // echo curl_error($ch);
    curl_close($ch);
    return $retValue;
}

// Getting JSON out of CURL
function getJSON($url){
    return json_decode(grabPage($url),TRUE);
}

// Fetching TPC data
function fetchTimingPoint($timingPointCode) {
    return getJSON(API_URL.'/tpc/'.$timingPointCode.'');
}

// Fetching SAC data
function fetchStopArea($stopAreaCode) {
    $data = getJSON(API_URL.'/stopareacode/'.$stopAreaCode.'');

    // To make compatible with other functions, go one level deep into array
    //return $data;
    return $data[$stopAreaCode];
}

// Fetching data from specific journey
function fetchJourney($journeycode){
    return getJSON(API_URL.'/journey/'.$journeycode.'');
}

function sortArraysByField(&$array, $field){
    usort($array, function($a, $b) use ($field) { return strnatcmp($a[$field], $b[$field]); });
}

function processTime(&$dateTime){
    if(substr($dateTime, -8) == '00:00:00'){
        $date = new DateTime($dateTime);
        $now = new DateTime();
        $diff = $date->diff($now);
        if($diff->format('%h') >= 4){
            $date->add(new DateInterval('P1D'));
            $dateTime = date_format($date,'Y-m-d').'T'.date_format($date,'H:i:s');
        }
    }
    $dateTime = str_replace('T', ' ', $dateTime);
}

function calcDelay($expected,$target){
    $expected = new DateTime($expected);
    $target = new DateTime($target);
    $diff = date_diff($expected,$target);
    if($diff->s <= 29){
        $diff->s = 0;
        if($diff->s == 0 && $diff->i == 0 && $diff->h == 0){
            $diff->invert = 0;
        }
    }
    if($diff->s >= 30) {
        $diff->s = 0;
        if($diff->i == 59){
            $diff->h = $diff->h+1;
        }
        else{
            $diff->i = $diff->i+1; }

    }
    $delay = $diff->h*60+$diff->i;
    $invert = $diff->invert;
    if($invert == 1){
        $status = '2';
    }
    elseif($delay == 0){
        $status = '1';
    }
    else{
        $status = '0';
    }
    // status: [0] = early, [1] = ontime, [2] = delay
    $result = array('delay' => $delay, 'status' => $status);
    return $result;
}

function calcMinutes($time){
    $now = new DateTime();
    $time = new DateTime($time);

    $diff = date_diff($now,$time);
    $minutes = $diff->h*60+$diff->i;

    return $minutes;
}

function getStopData($data){
    $stops = array();
    foreach($data as $stop){
        if(gettype($stop) == 'array'){
            array_push($stops, $stop['Stop']);
        }
    }
    return $stops;
}

function getDepartures($data,$sort=true){
    // Get all departures from (a set of) timing point code(s), possibly sorted by departure time
    $departures = array();
    foreach($data as $stop){
        if(gettype($stop) == 'array'){
            foreach($stop['Passes'] as $departure){
                if($departure['JourneyStopType'] != 'LAST' && $departure['TripStopStatus'] != 'PASSED'){
                    processTime($departure['ExpectedArrivalTime']);
                    processTime($departure['TargetArrivalTime']);
                    processTime($departure['ExpectedDepartureTime']);
                    processTime($departure['TargetDepartureTime']);
                    array_push($departures, $departure);
                }
            }
        }
    }

    if($sort){
        sortArraysByField($departures, 'ExpectedDepartureTime');
    }

    return $departures;
}

$error = null;
try {
    // Nijenborgh in both directions, so two numbers
    $data = fetchTimingPoint('10004130,10004140');

    if (empty($data))
        throw new Exception('API returned empty response.');
    
    $departures = getDepartures($data);
} catch (Exception $e) {
    $error = $e;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bus departures at Nijenborgh</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php if (!empty($error)): ?>
<div class="error">
    <h1>Cannot load bus departure data!</h1>
    <p>An error was encountered while loading bus departure data. Here's more information:</p>
    <p><?= $error->getMessage() ?></p>
</div>
<?php else: ?>
<div class="busstop-slide"> 
    <h1>Bus departures</h1>
    <table class="departure-table">
        <thead>
            <tr>
                <th class="line">Line</th>
                <th class="destination">Destination</th>
                <th class="departure">Departure</th>
            </tr>
        </thead>
        <tbody>
        <?php $rendered_count = 0; ?>
        <?php foreach ($departures as $departure): ?>
            <?php if($rendered_count >= MAX_ENTRIES) { break; } ?>
            <?php if($departure['LinePlanningNumber'] == null) { continue; } ?>

            <?php $minutes = calcMinutes($departure['ExpectedDepartureTime']); ?>
            <?php // $delay = calcDelay($departure['ExpectedDepartureTime'],$departure['TargetDepartureTime']); // unused, for now ?>
            <tr <?php if ($departure['TripStopStatus'] == "CANCELLED"): ?>class="cancelled"<?php endif ?>>
                <td class="line <?= $departure['LinePlanningNumber'] ?>">
                    <?= $departure['LinePublicNumber'] ?>
                </td>
                <td class="destination">
                    <?= $departure['DestinationName50'] ?>
                </td>
                <td class="departure">
                    <?php if ($departure['TripStopStatus'] == "UNKNOWN"): ?>
                        <?= (new DateTime($departure['TargetDepartureTime']))->format('H:i') ?>
                    <?php elseif ($departure['TripStopStatus'] == "CANCELLED"): ?>
                        <?= (new DateTime($departure['TargetDepartureTime']))->format('H:i') ?>
                        cancelled
                    <?php elseif ($minutes == 0): // for PLANNED and DRIVING statusses departing now-ish ?>
                        <svg xmlns="http://www.w3.org/2000/svg" version="1.1" shape-rendering="geometricPrecision" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 15240 6534"
                        xmlns:xlink="http://www.w3.org/1999/xlink">
                            <path d="M14603 472l-2175 0 0 2087 2340 841 0 -2763c0,-90 -75,-165 -165,-165zm-11088 4407c-458,0 -828,370 -828,828 0,457 370,827 828,827 457,0 827,-370 827,-827 0,-458 -370,-828 -827,-828zm8210 0c-457,0 -828,370 -828,828 0,457 371,827 828,827 458,0 828,-370 828,-827 0,-458 -370,-828 -828,-828zm231 -4407l-2576 0 0 2003 2576 0 0 -2003zm-3048 0l-2576 0 0 2003 2576 0 0 -2003zm-3048 0l-2576 0 0 2003 2576 0 0 -2003zm-3048 0l-2175 0c-90,0 -165,75 -165,165l0 1838 2340 0 0 -2003zm11791 -472c-4655,0 -9311,0 -13966,0 -351,0 -637,286 -637,637l0 4416c0,350 286,637 637,637l1645 0c9,-674 559,-1216 1233,-1216 674,0 1223,542 1232,1216l5746 0c9,-674 558,-1216 1232,-1216 674,0 1224,542 1233,1216l1645 0c351,0 637,-287 637,-637l0 -4416c0,-351 -286,-637 -637,-637z"/>
                        </svg>
                    <?php elseif ($minutes < 60): // for PLANNED and DRIVING statusses departing within an hour from now ?>
                        <?= $minutes ?> min
                    <?php else: // for PLANNED and DRIVING statusses ?>
                        <?= (new DateTime($departure['TargetDepartureTime']))->format('H:i') ?>
                    <?php endif ?>
                </td>
            </tr>

            <?php $rendered_count ++; ?>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>
</body>
</html>
