<?php
/**
 * Steve Muchow
 * slot-machine.php
 * A simple client-facing web page to send and receive RESTful data for a slot machine spin.
 */
require_once 'spin-entry.php';
//require_once 'mysql.php'; // hide away database details. In the future

$test = accumulateData();
if ($test->isValid) {
    postTheSpinResults($test);
}
else {
    echo "skipping the test";
}

if (isset($_POST['submit'])) {
    $spindata = accumulateData();
    postTheSpinResults($spinData);
}

function accumulateData() {
    $spinData = new SpinEntry();
    $spinData->isValid = true;
    return $spinData;
}

/**
 * @param $theSpin
 * TODO move the database
 */
function postTheSpinResults($theSpin) {
    $url = "http://localhost/testform/slot-machine-spin-results.php";
    //$spin = array("spindata"=>$theSpin->getParameters());
    $theSpin->testRandomize(); // simulate a spin.
    $spin = array("spindata"=>$theSpin->getEncryptedParameters('yodelme122'));
    $spindata = http_build_query($spin);
    echo "$url?$spindata";
    $server = curl_init($url);
    curl_setopt($server,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($server,CURLOPT_POST,true);
    curl_setopt($server,CURLOPT_POSTFIELDS,$spindata);
    $response = curl_exec($server);
    curl_close($server);
    echo "<br />\nResponse: $response<br />\n";
    $result = json_decode($response);
    if ($response!=null) {
        if (!empty($result->data)) {
            if ($result->data!=null) {
                echo "Name: " . $result->data->name . " (id:" . $result->data->id . ")<br />" .
                    "lifetime spins: " . $result->data->lifetimeSpins . "  average return: " . $result->data->lifetimeAverageReturn . "<br />" .
                    "balance: " . $result->data->creditBalance;
                ;
            }
        }
        else if (!empty($result->status_message)) {
            echo $result->status_message;
        }
    }
    else {
        echo "<br>no response from the slot-machine-spin-results server";
    }
}

/**
 * @param $array
 * @param $showDebug
 * @return string
 */
function dumpArray($array,$showDebug) {
    if (empty($showDebug)) {
        return '';
    }
    if ($array==null) {
        return '';
    }
    $str = "";
    foreach ($array as $value) {
         $str = $str . "Value: $value<br />\n";
    }
    echo $str;
    return $str;
}