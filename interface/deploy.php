<?php
	// Anti-XSS
	header("Content-type: text/plain");
	//if(empty($_POST['payload'])) die('wat');
	
	//fetch headers
	$headers = []; 
	foreach ($_SERVER as $name => $value) 
	{ 
		if (substr($name, 0, 5) == 'HTTP_') 
		{ 
			$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
		} 
	}
	 
	//check auth
	$secret = 'f8b5fd97df670h5sdfh4dfs35df6g3';
	$hubSignature = $headers['X-Hub-Signature'];
	list($alg, $hash) = explode('=', $hubSignature, 2);
	$payload = file_get_contents('php://input');
	$payloadHash = hash_hmac($alg, $payload, $secret);
	if ($hash !== $payloadHash) die('no');

	//sync
	$out = shell_exec('./deploy.sh');
	echo $out;
?>
