<?php
	// Anti-XSS
	header("Content-type: text/plain");
	if(!isset($_POST['payload'])) die();
	
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
	list($algo, $hash) = explode('=', $hubSignature, 2);
	$payload = file_get_contents('php://input');
	$payloadHash = hash_hmac($algo, $payload, $secret);
	if ($hash !== $payloadHash) die();

	//sync
	$out = shell_exec('cd .. && 
	git reset --hard HEAD && 
	git pull https://3cae39f6cc90ba28d1acf9ccc44ad8622882d213@github.com/nakiner/tbot.git master');
	echo $out;
?>