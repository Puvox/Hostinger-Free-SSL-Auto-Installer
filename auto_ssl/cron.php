<?php
$email	='your_email@gmail.com';


// ###################### core ########################
if(!defined("PHP_VERSION_ID") || PHP_VERSION_ID < 50300 || !extension_loaded('openssl') || !extension_loaded('curl')) {
    die("You need at least PHP 5.3.0 with OpenSSL and curl extension\n");
}
require ($path_to_lescript_library = __DIR__.'/lescript-master/Lescript.php');

class Logger { function __call($name, $arguments) { echo (date('Y-m-d H:i:s')." [$name] ${arguments[0]}\n<br/>"); }}  // you can use any logger according to Psr\Log\LoggerInterface
$logger = new Logger();

function rmdir_recursive($path){
	if(!empty($path) && is_dir($path) ){
		$dir  = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS); //upper dirs not included,otherwise DISASTER HAPPENS :)
		$files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $f) {if (is_file($f)) {unlink($f);} else {$empty_dirs[] = $f;} } if (!empty($empty_dirs)) {foreach ($empty_dirs as $eachDir) {rmdir($eachDir);}} rmdir($path);
		return true;
	}
	return true;
}


 

// ###################### custom ########################
try { 
	//#### check if 85 days passed ####
	$file= __DIR__.'/tmp_last_time';
	$period = 85*86400;
	if (!file_exists($file)) file_put_contents($file, 0 );
	$last_time = file_get_contents($file);
	if ( time() - $period  < $last_time && !(isset($_GET['force'])) 
		exit("too early");
	else
		file_put_contents($file, time() );
	// ################################


	//if (isset($argv[1])) parse_str($argv[1], $array);
	$domain	=str_replace('www.', '', $_SERVER['HTTP_HOST']);
	$domains = [$domain, 'www.'.$domain];
	$path_to_public_html = $_SERVER["DOCUMENT_ROOT"];	
	$cert_storate_path = __DIR__ .'/certificate/storage';
	$path_to_keys = $cert_storate_path."/$domain";

	// ###############################################
    $le = new Analogic\ACME\Lescript($cert_storate_path, $path_to_public_html, $logger);
    # or without $logger
    $le->contact = ["mailto:$email"]; // optional
    $le->initAccount();
    $le->signDomains($domains);

	// needed: fullchain.pem (contains joint of "cert.pem & chain.pem") + private.pem is needed
	mail($email, 'SSL files for : '.$domain,  "Certificate (FullChain):". PHP_EOL . PHP_EOL . file_get_contents($path_to_keys. '/fullchain.pem'). PHP_EOL . PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .	"Private Key:". PHP_EOL . PHP_EOL . PHP_EOL . file_get_contents($path_to_keys. '/private.pem'), $headers ='From: ssl_issued@'.$domain . "\r\n" .  'Reply-To: ssl_issued@'.$domain . "\r\n" .  'X-Mailer: PHP/' . phpversion() );
	echo '<span style="font-size:2em; color:green;>KEYS SUCCESSFULLY MAILED (check your spambox)</span>'; 
} catch (\Exception $e) {
    $logger->error($message = $e->getMessage());
    //$logger->error($e->getTraceAsString());  reveals server paths too...
	mail($email, 'SSL files couldnt be created.',  "Reason:".$message , $headers ='From: ssl_issued@'.$domain . "\r\n" .  'Reply-To: ssl_issued@'.$domain . "\r\n" .  'X-Mailer: PHP/' . phpversion() );
	echo '<span style="font-size:2em; color:green;>KEYS WERE NOT CREATED. $message</span>'; 
}
finally{
	// remove traces after processing
	if (is_dir($path_to_keys)) rmdir_recursive($path_to_keys);
	if (is_dir($cert_storate_path)) rmdir_recursive($cert_storate_path);
}
