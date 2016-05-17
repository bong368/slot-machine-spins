<?php
/**
 * slot-machine-spin-results.php
 * The Web Service that validates the client's spinData, parses it,
 * adds it to the database and then returns a response back to the user
 * Steve Muchow
 */

header("Content-Type:application/json");

// persistent connection to database.
$con = 0;
if ($con==0) {
    $con = mysqli_connect('p:localhost','root','samwise27908','spintestDB') or die("DB Connection Failed");
}

// process client request - make sure spinData is valid. This is a encrypted item.
//
if (!empty($_POST['spindata'])) {
    $spinData = $_POST['spindata'];
    $parsedData = validateSpinData($spinData,false);
    if (!$parsedData['invalid']) {
        $parsedData = addSpinToDatabase($parsedData,false);
    }

    if ($parsedData['invalid']) {
        respondAsJson(200, $parsedData['error'], null);
    }
    else {
        $responseObject = prepareResponse($parsedData);
        respondAsJson(200, "OK" , $responseObject);
    }
}
else if (!empty($_GET['spindata'])) { // used for direct testing.
    $spinData = $_GET['spindata'];
    echo "parameters: $spinData\n";
    $parsedData = validateSpinData($spinData,true);
    if (!$parsedData['invalid']) {
        $parsedData = addSpinToDatabase($parsedData,true);
    }

    if ($parsedData['invalid']) {
        respondAsJson(200, $parsedData['error'], null);
    }
    else {
        $responseObject = prepareResponse($parsedData);
        respondAsJson(200, "OK" , $responseObject);
    }
}
else {
    // this is not what we are looking for.
    respondAsJson(400,"Invalid Request" , dumpArray($_GET,true));
}

/**
 * @param $spinData
 * @param $showDebug
 * @return mixed
 */
function validateSpinData($spinData,$showDebug) {
    $data = decryptSpinData($spinData,$showDebug);
    if ($data['invalid']) {
        // TODO more info - debug stuff
    }
    return $data;
}

/**
 * @param $newData
 * @param $showDebug
 * @return mixed
 */
function addSpinToDatabase($newData,$showDebug) {
    global $con; // talk to the master connetion. TODO change this to a class sometime.

    // assume all data is valid - verify password FIRST.
    $spinDate = date ("Y-m-d H:i:s");
    $bet = $newData['bet'];
    $won = $newData['won'];
    $id = $newData['id'];
    $sql =  "SELECT * FROM users WHERE player_id='$id'";

    // since we are manipulating a few items, go ahead and read the db for the user.
    // TODO - maybe make a cache for the database entry and just update that until the session is finished (a lot more work)
    $result = mysqli_query($con,$sql);
    if ($result->num_rows===0) {
        $newData['invalid'] = true;
        array_push($newData['error'],"Query Error select(): " . mysqli_error($con) .  "  Query " . $sql);
        return $newData;
    }

    $row = $result->fetch_assoc();
    if ($row===null) {
        $newData['invalid'] = true;
        array_push($newData['error'],"Query Error fetch(): " . mysqli_error($con)  .  "  Query " . $sql);
        return $newData;
    }

    if (!passwordChecksOut($newData,$row['password_hashed'],$showDebug)) {
        $newData['invalid'] = true;
        array_push($newData['error'],'password incorrect');
        return $newData;
    }

    $row['lastSpinDate'] = $spinDate;

    $updates = '';
    $firstSpinDate = $row['firstSpinDate'];
    if ($firstSpinDate === null) {
        $firstSpinDate = $spinDate;
        $updates = $updates . "firstSpinDate='$firstSpinDate',";
    }
    $lifetime_spins = $row['lifetime_spins'] + 1;
    $total_coins_won = $row['total_coins_won']+$won;
    $total_coins_bet = $row['total_coins_bet']+$bet;
    $raw_balance = $row['current_coin_balance'];
    $current_coin_balance = $raw_balance-$bet+$won;

    // if the client side is correctly trapping for the credit balance, this code will not be needed, but we need it
    // here for testing.
    if ($raw_balance<=0) {
        $newData['invalid'] = true;
        array_push($newData['error'],"Can't gamble without money");
        return $newData;
    }

    $updates = $updates .
        "lifetime_spins=$lifetime_spins," .
        "total_coins_won=$total_coins_won," .
        "total_coins_bet=$total_coins_bet," .
        "current_coin_balance=$current_coin_balance";

    $sql =  "UPDATE users ".
            "SET $updates" .
            " WHERE player_id=$id ";
    if (mysqli_query($con,$sql)===FALSE) {
        $newData['invalid'] = true;
        array_push($newData['error'],"Query Error update(): " . mysqli_error($con)  .  "  Query " . $sql);
        return $newData;
    }

    $newData['name'] = $row['given_name'] . " " . $row['surname'];
    $newData['lifetimeSpins'] = $lifetime_spins;
    $newData['lifetimeAverageReturn'] = (($total_coins_won>=$total_coins_bet)?number_format($total_coins_won/$total_coins_bet,3):0);
    $newData['creditBalance'] = $current_coin_balance;
    return $newData;
}

/**
 * @param $newData
 * @param $hashedPwd
 * @param $showDebug
 * @return bool
 */
function passwordChecksOut($newData,$hashedPwd,$showDebug) {
    return ($newData['hash']==$hashedPwd);
}

/**
 * @param $str
 * @return mixed
 */
function makeDataSqlHappy($str) {
    //return mysqli_real_escape_string(trim(strip_tags(addslashes($str))));
    return $str;
}

/**
 * responsible to unpack the spinData item and parse it to be
 * sure all the parts are with it.
 * looking for
 *      passwordHash,
 *      coinsWon,
 *      coinsBet,
 *      playerId.
 * @param $spinData
 * @param $showDebug
 * @return mixed
 */
function decryptSpinData($spinData,$showDebug) {
    //$spinData = mysqli_real_escape_string(trim(strip_tags(addslashes($spinData))));
    $array = preg_split ('/,/',$spinData);
    if (count($array)!=8) {
        $newData['invalid'] = true;
        $newData['error'] = 'improper number of elements';
        //dumpArray($newData);
        return $newData;
    }

    $newData = array();
    $x = 0;
    while ($x < 8) {
        $newData[$array[$x]] = makeDataSqlHappy($array[$x+1]);
        $x += 2;
    }

    $newData['invalid'] = false;
    $newData['error'] = array();
    $newData = verifyPassword($newData);
    $newData = verifyPlayerId($newData);
    $newData = verifyCoinsWon($newData);
    $newData = verifyCoinsBet($newData);

    dumpArray($newData,$showDebug);
    return $newData;
}

/**
 * @param $newData
 * @return mixed
 */
function verifyPassword($newData) {
    if (empty($newData['hash'])) {
        $newData['invalid'] = true;
        array_push($newData['error'],'password is missing');
    }
    else if (!preg_match("/^[0-9a-zA-Z ]*$/",$newData['hash'])) { // should be hexidecimal with hashing turned on.
        $newData['invalid'] = true;
        array_push($newData['error'],"password can only be digits, spaces, and alphabetic characters.");
    }
    return $newData;
}

/**
 * @param $newData
 * @return mixed
 */
function verifyPlayerId($newData) {
    if (empty($newData['id'])) {
        $newData['invalid'] = true;
        array_push($newData['error'],'playerId is missing');
    }
    else if (!preg_match("/^[0-9]+$/",$newData['id'])) {
        $newData['invalid'] = true;
        array_push($newData['error'],"playerId can only be digits");
    }
    return $newData;
}

/**
 * @param $newData
 * @return mixed
 */
function verifyCoinsWon($newData) {
    if (empty($newData['won'])) {
        $newData['won'] = 0;
    }
    else if (!preg_match("/^[0-9]+$/",$newData['won'])) {
        $newData['invalid'] = true;
        array_push($newData['error'],"coinsWon can only be digits");
    }
    return $newData;
}

/**
 * @param $newData
 * @return mixed
 */
function verifyCoinsBet($newData) {
    if (empty($newData['bet'])) {
        $newData['invalid'] = true;
        array_push($newData['error'],'coinsBet is missing');
    }
    else if (!preg_match("/^[0-9]+$/",$newData['bet'])) {
        $newData['invalid'] = true;
        array_push($newData['error'],"coinsBet can only be digits");
    }
    return $newData;
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
    $str = "";
    foreach ($array as $key => $value) {
        if ($key!='error') {
            $str = $str . "Key: $key; Value: $value<br />\n";
        }
        else {
            $str = $str . dumpArray($value, $showDebug);
        }
    }
    echo $str;
    return $str;
}

/**
 * simplified response call.
 * @param $status
 * @param $status_message
 * @param $data
 */
function respondAsJson($status, $status_message, $data) {
    $status_message = flattenArray($status_message);
    header("HTTP/1.1/ $status $status_message");
    $response['status'] = $status;
    $response['status_message'] = $status_message;
    $response['data'] = $data;
    $json_response = json_encode($response);
    echo $json_response;
}

function flattenArray($data) {
    if (is_array($data)) {
        $str = '';
        foreach ($data as $value) {
            $str = $str . $value . ",";
        }
        return $str;
    }
    return $data;
}

/**
 * @param $parsedData
 * @return array
 */
function prepareResponse($parsedData) {
    $data = array();
    $data['id'] = $parsedData['id'];
    $data['name'] = $parsedData['name'];
    $data['lifetimeSpins'] = $parsedData['lifetimeSpins'];
    $data['lifetimeAverageReturn'] = $parsedData['lifetimeAverageReturn'];
    $data['creditBalance'] =$parsedData['creditBalance'];
    return $data;
}






