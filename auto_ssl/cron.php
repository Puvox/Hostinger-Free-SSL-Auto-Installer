<?php
$email = 'yourmail@gmail.com';				// your mail where you will get certificates
$domain = 'yourdomain.com';					// your domain name, on which you are creating SSL's
$path_to_public_html = __DIR__.'/../';		// path your domain public root


// =================================== you dont belong to below ==========================================//
$domains = [$domain , 'www.'.$domain];
$path_to_lescript_library = __DIR__.'/lescript-master/Lescript.php';
$cert_storate_path = __DIR__ .'/certificate/storage';
$path_to_keys = $cert_storate_path."/$domain";
if(!defined("PHP_VERSION_ID") || PHP_VERSION_ID < 50300 || !extension_loaded('openssl') || !extension_loaded('curl')) {
    die("You need at least PHP 5.3.0 with OpenSSL and curl extension\n");
}
require $path_to_lescript_library;

// you can use any logger according to Psr\Log\LoggerInterface
class Logger { function __call($name, $arguments) { echo (date('Y-m-d H:i:s')." [$name] ${arguments[0]}\n<br/>"); }}
$logger = new Logger();

function rmdir_recursive($path){
	if(!empty($path) && is_dir($path) ){
		$dir  = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS); //upper dirs not included,otherwise DISASTER HAPPENS :)
		$files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $f) {if (is_file($f)) {unlink($f);} else {$empty_dirs[] = $f;} } if (!empty($empty_dirs)) {foreach ($empty_dirs as $eachDir) {rmdir($eachDir);}} rmdir($path);
		return true;
	}
	return true;
	//include_once(ABSPATH.'/wp-admin/includes/class-wp-filesystem-base.php');
	//\WP_Filesystem_Base::rmdir($fullPath, true);
}


try {

    $le = new Analogic\ACME\Lescript($cert_storate_path, $path_to_public_html, $logger);
    # or without $logger
    $le->contact = array('mailto:test@test.com'); // optional
    $le->initAccount();
    $le->signDomains($domains);
	mail($email, 'SSL files for : '.$domain,  file_get_contents($path_to_keys. '/cert.pem'). PHP_EOL . PHP_EOL . file_get_contents($path_to_keys. '/private.pem'), $headers ='From: ssl_issued@'.$domain . "\r\n" .  'Reply-To: ssl_issued@'.$domain . "\r\n" .  'X-Mailer: PHP/' . phpversion() );
	echo '<span style="font-size:2em; color:green;>SUCCESSFULLY MAILED (check your spambox)</span>';
} catch (\Exception $e) {
    $logger->error($e->getMessage());
    $logger->error($e->getTraceAsString());
}
finally{
	// remove traces after processing
	if (is_dir($path_to_keys)) rmdir_recursive($path_to_keys);
	if (is_dir($cert_storate_path)) rmdir_recursive($cert_storate_path);
}