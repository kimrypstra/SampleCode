<?php

// This is the web service that Unitrans uses. Initially I wasn't sure if I would use Google Translate or Microsoft Translate. I ended up going with Google, but I left the Microsoft stuff in just in case I changed my mind. 

function translateWithGoogle($text, $from, $to) {
	try {	
		$request = curl_init();
		$key = "";
		// Add your key here...
		$url = "https://www.googleapis.com/language/translate/v2";
	//$from = "en";
	//$to = "es";
		$params = "key=".urlencode($key)."&q=".urlencode($text)."&source=".urlencode($from)."&target=".urlencode($to);

		$url .= "?".$params;

		curl_setopt($request, CURLOPT_URL, $url);
		curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($request);

		$curlErrno = curl_errno($request);
		if ($curlErrno) {
			$curlError = curl_error($request);
			throw new Exception($curlError);
		}

		curl_close($request);

		$obj = json_decode($response);
		//$xmlObj = simplexml_load_string($response);

		return $obj;

	} catch (Exception $e) {
		echo "Exception: " . $e->getMessage() . PHP_EOL;
		$error = new ssga('UA-83306480-2', 'http://api.disordersoftware.com');
		$error->set_event('Errors',$e, '','');
		$error->send();
		$error->reset();
		return $e->getMessage();
	}
}

function translateWithMicrosoft($text, $from, $to, $v){

	//$languages = updateLanguageList();
	
	$token = checkToken();

try {	
	$request = curl_init();
	$authHeader = array("Authorization: Bearer ". $token);
	
	$url = "http://api.microsofttranslator.com/V2/Http.svc/Translate";
	//$from = "en";
	//$to = "es";
	$params = "text=".urlencode($text)."&to=".urlencode($to)."&from=".urlencode($from)."&contentType=text/plain";
	
	$url .= "?".$params;
	
	curl_setopt($request, CURLOPT_URL, $url);
	curl_setopt($request, CURLOPT_HTTPHEADER, $authHeader);
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
	
	$response = curl_exec($request);
	
	$curlErrno = curl_errno($request);
	if ($curlErrno) {
		$curlError = curl_error($request);
		$error = new ssga('UA-83306480-2', 'http://api.disordersoftware.com');
		$error->set_event('Errors',$curlError, '','');
		$error->send();
		$error->reset();
		throw new Exception($curlError);
	}
	
	curl_close($request);
	
	$obj = json_decode($response);
	$xmlObj = simplexml_load_string($response);
	
	return $xmlObj;
	
} catch (Exception $e) {
	echo "Exception: " . $e->getMessage() . PHP_EOL;
	$error = new ssga('UA-83306480-2', 'http://api.disordersoftware.com');
	$error->set_event('Errors',$e, '','');
	$error->send();
	$error->reset();
}
	
}

function getToken() {
	require_once('../../../mysqli_connect_unitrans.php');
	
	// Set up the cURL request
	$paramArray = array(
		"client_id" => "",
		"client_secret" => "",
		// Add your credentials here...
		"scope" => "http://api.microsofttranslator.com",
		"grant_type" => "client_credentials"
	);
	
	$paramArray = http_build_query($paramArray);
    $request = curl_init();
	curl_setopt_array(
		$request,
		array(
			CURLOPT_URL => "https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/",
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $paramArray,
			CURLOPT_RETURNTRANSFER => true
		)
	);
	
	// Send the cURL Request and deal with the response
	$response = curl_exec($request);
	curl_close($request);
	$jsonResponse = json_decode($response);
	$token = $jsonResponse->access_token;
	
	// Put the token into the database
	$newRecordQueryString = "INSERT INTO tokens (token, epoch, new_requested) VALUES (?, ?, ?)";
	$stmt = mysqli_prepare($dbc, $newRecordQueryString);
	$false = 'F';
	mysqli_stmt_bind_param($stmt, 'sis', $token, time(), $false);
	mysqli_stmt_execute($stmt);
	$affectedRows = mysqli_stmt_affected_rows($stmt);
	if ($affectedRows == 1) {
		return $token;
	} else {
		return mysqli_error($dbc);
	}

	return $token;
		
}

function checkToken() {
	require_once('../../../mysqli_connect_unitrans.php');
	
	$query = "SELECT * FROM tokens ORDER BY epoch DESC LIMIT 1";
    
    $response = @mysqli_query($dbc, $query);

    if ($response) {
        while ($row = mysqli_fetch_array($response)){
            $infoArray = array("token" => $row[token],
                                 "epoch" => $row[epoch]
                                 );
        }
    } else {
        $infoArray = "No dice.";
    }
	
    $currentTime = time();
	$tokenEpoch = $infoArray["epoch"];
	$expiryTime = $tokenEpoch + 540;
	if ($currentTime < $expiryTime) {
		$result = $infoArray["token"];
		$check = "Token is current. Expiry time: $expiryTime, token epoch: $tokenEpoch, current time: $currentTime";
	} else {
		$result = "No token";
		$check = "Token is not current - Token epoch: $tokenEpoch, expiry time: $expiryTime, current time: $currentTime";
		$error = new ssga('UA-83306480-2', 'http://api.disordersoftware.com');
		$error->set_event('Errors','Token Not Current', '','');
		$error->send();
		$error->reset();
	}

    return $infoArray["token"];

}

if(isset($_GET["action"])){
	switch($_GET["v"]){
		case "g":
		$value = translateWithGoogle($_GET["text"], $_GET["from"], $_GET["to"]);
		break;
		case "m":
		$value = translateWithMicrosoft($_GET["text"], $_GET["from"], $_GET["to"], $_GET["v"]);
		break;
	}

    
}

exit(json_encode($value));

?>