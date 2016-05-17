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

// process client request - make sure spinData is valid. This is a future obfuscated item.
// the idea is to obfuscate everything in one parameter.
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
 * the validation stub. This can be expanded on for telematics and analytics when errors crop up.
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
 * The second major piece. The trivial validation is complete. Now access the Database to verify the user, the password and
 * the bank account. If any of these fail, the method exits. Otherwise the database is updated and the final outputs are set for
 * placement in the reply
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

    // obtaining the data so it can be updated. 
    $row = $result->fetch_assoc();
    if ($row===null) {
        $newData['invalid'] = true;
        array_push($newData['error'],"Query Error fetch(): " . mysqli_error($con)  .  "  Query " . $sql);
        return $newData;
    }

    // this is the only place the validation can be checked in this implementation. Ideally, an OAuth token would be used for
    // the session an this would be moved to another area.
    if (!passwordChecksOut($newData,$row['password_hashed'],$showDebug)) {
        $newData['invalid'] = true;
        array_push($newData['error'],'password incorrect');
        return $newData;
    }

    // keep track of the last spin date. This is a guess to simplify some coding. The actual column in the DB updates the last
    // update time whenever the row is updated, so this only provides an estimate for the 'firstSpinDate' field.
    $row['lastSpinDate'] = $spinDate;

    $updates = '';
    $firstSpinDate = $row['firstSpinDate'];
    if ($firstSpinDate === null) { // if this is the first time, update the firstSpinDate, otherwise skip.
        $firstSpinDate = $spinDate;
        $updates = $updates . "firstSpinDate='$firstSpinDate',";
    }

    // getting ready to refresh the DB with the updated values.    
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

    // make sure all the data is part of the optional firstSpin a and the mandatory items.
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

    // all this is prep for the reply. Since we have the DB data, might as well set it up here.
    $newData['name'] = $row['given_name'] . " " . $row['surname'];
    $newData['lifetimeSpins'] = $lifetime_spins;
    $newData['lifetimeAverageReturn'] = (($total_coins_won>=$total_coins_bet)?number_format($total_coins_won/$total_coins_bet,3):0);
    $newData['creditBalance'] = $current_coin_balance;
    return $newData;
}

/**
 * This is the password checker. Here is where a more robust salted hash mechanism would be developed.
 * @param $newData
 * @param $hashedPwd
 * @param $showDebug
 * @return bool
 */
function passwordChecksOut($newData,$hashedPwd,$showDebug) {
    return ($newData['hash']==$hashedPwd);
}

/**
 * SQL has requirements on how text is used in queries and how it is stored. This method (when active) 
 * will ensure that HTML does not cause issues.
 * @param $str
 * @return mixed
 */
function makeDataSqlHappy($str) {
    //return mysqli_real_escape_string(trim(strip_tags(addslashes($str))));
    return $str;
}

/**
 * responsible to unpack the spinData item and parse it to be
 * sure all the parts are with it. To make forms show ALL the issues at once,
 * the reply has an array of errors. 
 * looking for
 *      hash,
 *      won,
 *      bet,
 *      id.
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

    // split up the single (multiplexing parameter) into 8 elements. stitch them together.
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

/**
 * This method makes sure the status_message is NOT an array of strings
 */
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
 * Generate the final output.
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






