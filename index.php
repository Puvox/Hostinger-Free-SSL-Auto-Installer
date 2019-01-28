<?php
header("Cache-Control: max-age=0, must-revalidate");
//ini_set('session.cache_limiter','public');
session_cache_limiter(true);


function cron_exec($server, $port, $user, $key_or_pass )
{
	set_include_path(__DIR__ . '/phpseclib1.0.11');
	include("Net/SSH2.php");

	if(strlen($key_or_pass)>70)
	{
		include("Crypt/RSA.php");
		$key = new Crypt_RSA();
		$key->loadKey($key_or_pass);
		$key_or_pass= $key;
	}
	$ssh = new Net_SSH2($server, $port);   // Domain or IP
	if (!$ssh->login($user, $key_or_pass))  exit('Login Failed');
	
	
	$ssh->setTimeout(60);	//ini_set("default_socket_timeout", 60);
	return $ssh;

}





// =============================================== //


if ( !empty($_GET['generate']))
{
	session_start();
	vx($_SESSION['xmldata']);

		
	$port		= 65002; 
	$user_mail	= (string) $_SESSION['xmldata']['user_mail']; 
	$ssh_key	= (string) $_SESSION['xmldata']['ssh_key']; 
	$sites		= (array) $_SESSION['xmldata']['domain'];  


	$which_site = $_GET['generate'];

	foreach($sites as $e)
	{
		$domain_data = explode("|",$e);
		$domain		= $domain_data[0];
		$ip			= $domain_data[1];
		$username	= $domain_data[2];
		
		if ($which_site != $domain) 
			continue;

		echo '<h1>'.$domain .'</h1>';

		// removed  from in front of ".... php composer.phar " , because hosting is blocking that domain. So, we have to manually include the "composer.phar"
		//   php -r "copy(\'https://itask.software/tools2/composer-installer.txt\', \'composer-setup.php\');" && php composer-setup.php &&  php -r "unlink(\'composer-setup.php\');"  && 
		$www		= substr_count($domain , '.') >1 ? '' : ':www.'.$domain ;
		$www_root	= substr_count($domain , '.') >1 ? '' : '../public_html';
		
		$extras 	='';
		$extras_root='';
		if(array_key_exists(3, $domain_data) )
		{
			$subdomains	= explode(',', $domain_data[3]);
			$extras 	= ':'.implode('.'.$domain .':', $subdomains).'.'.$domain ;
			$extras_root= ':../public_html/'.implode(':../public_html/', $subdomains);
		}
		
		$domain_root = '../public_html/';

		$command =  
		'site='.$domain.';    sites_all='. $domain . $www . $extras .';     myemail="'.$user_mail.'";              TargetFolder=acme-client;     ( [ -d "$TargetFolder" ] || git clone https://github.com/kelunik/acme-client )  &&    cd $TargetFolder     &&    ( [ -f "composer-setup.php" ]    ||    php -r "copy(\'https://itask.software/tools/hostinger-ssl-autoinstaller/composer/installer\', \'composer-setup.php\');" )        &&       ( [ -f "composer.phar" ]  || ( php composer-setup.php &&  php -r "unlink(\'composer-setup.php\');" ) )   &&   ( [ -d "vendor" ] ||  php composer.phar install --no-dev )      &&   php bin/acme setup --server letsencrypt --email $myemail           &&   php bin/acme issue --domains $sites_all --path '.$domain_root . $www_root . $extras_root.' --server letsencrypt      &&    php -r "mail(\''.$user_mail.'\', \'SSL generated for : '.$domain.'\',  file_get_contents(\'./data/certs/acme-v01.api.letsencrypt.org.directory/$site/cert.pem\') . \'***********************************************\' . file_get_contents(\'./data/certs/acme-v01.api.letsencrypt.org.directory/$site/key.pem\') );"';

		$ssh= cron_exec($ip, $port, $username, $ssh_key);
		$res= $ssh->exec($command); 	
		
		echo  $res  ; //(stripos($res, 'Successfully issued') ===false ? 'Success;' : 'Fail: '. (isset($_GET['show']) ? $res : '') );
		echo '<br/>';
	}


}




if(!empty($_FILES) && !empty($_FILES['fileToUpload']) )
{
	ini_set('post_max_size', '100K');
	ini_set('upload_max_filesize', '100K');

	//include_once($file);
	$tmp_file=$_FILES['fileToUpload']['tmp_name']; 
	//$myXMLData = file_get_contents($tmp_file);
	//$xml=simplexml_load_string($myXMLData) or die("Error: Cannot create object");
	$xml=simplexml_load_file($tmp_file) or die("Error: Cannot read XML file");

	$array = [];
	$array['port']		= 65002; 
	$array['user_mail']	= (string) $xml->send_ssl_to[0]; 
	$array['ssh_key']	= (string) $xml->ssh_key[0]; 
	$array['sites']		= (array) $xml->domain;

	session_start();
	$_SESSION['xmldata'] = $array;
	session_write_close();

	echo '<div class="all">';
	echo 'Click desired domain to generate SSL for it: <br/>';

	foreach($array['sites'] as $e){
		$domain_data = explode("|",$e);
		$domain		= $domain_data[0];
		?>
			<div class="">
				<a href="?generate=<?php echo $domain;?>" target="_blank"><?php echo $domain;?></div>
			</div>
		<?php
	}
	echo '</div>';
	exit;
}






?><!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Free SSL auto-installer for Hostinger</title>
	<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css" />
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.2.1/css/bootstrap.min.css" />

	<style>
	body { font-family: sans-serif;  background-color: #eeeeee;}
	.warning { color:red; font-weight:bold; }
	.warning2 { color:pink; font-style:italic; background:black; font-size: 16px; padding:3px; }
	.scan-site { display:flex; flex-direction: column;  justify-content: center; align-items: center; }
	#page .heading{text-align:center;}
	.example_textarea{resize:both;  width: 100%; height: 500px; font-size: 0.8em; background: #e7e7e7;}
	/* -------------------- upload form area ------------------- */
	.file-upload {  background-color: #ffffff;  width: 600px;  margin: 0 auto;  padding: 20px;}
	.file-upload-btn {  width: 100%;  margin: 0;  color: #fff;  background: #1FB264;  border: none;  padding: 10px;  border-radius: 4px;  border-bottom: 4px solid #15824B;  transition: all .2s ease;  outline: none;  text-transform: uppercase;  font-weight: 700;}
	.file-upload-btn:hover {  background: #1AA059;  color: #ffffff;  transition: all .2s ease;  cursor: pointer;}
	.file-upload-btn:active {  border: 0;  transition: all .2s ease;}
	.file-uploading {  display: none;  text-align: center;}
	.file-upload-input {  position: absolute;  left: 0;  top:0;  margin: 0;  padding: 0;  width: 100%;  height: 100%;  outline: none;  opacity: 0;  cursor: pointer;}
	.image-upload-wrap {  margin-top: 20px;  border: 4px dashed #1FB264;  position: relative;}
	.image-dropping,  .image-upload-wrap:hover {  background-color: #1FB264;  border: 4px dashed #ffffff;}  
	#upload_form #submitbuttn {	display:none; text-align:center;}
	.OnDragging{	background:#bdfff3;}
	/* --------------------------------------------------------- */
	</style>

	<!-- <script src="./assets/script-public.js?<?php echo "rand_".rand(1,22222222222);?>"></script> -->

</head>
<body class="">

<div id="page" class="container">


<div class="index">
  <div class="heading">
    <h1> Setup <b>HTTPS</b> for your site with <code>Let's encrypt</code> free SLL</h1>
   
    </div>

  </div>

	<div class="file-upload">
		<form id="upload_form" action="" method="POST" enctype="multipart/form-data" target="_blank">

			<div class="image-upload-wrap">
				<input name="fileToUpload" class="file-upload-input" type='file' onchange="readURL(this);" accept="*/*" />
				<div class="drag-text">
				<h3>Drag & Drop <br/>or <br/> Select <code>.txt</code> file</h3>
				</div>
			</div>
			<script>
				$("#upload_form").on('dragover', function(e) { $(this).addClass("OnDragging"); }  )
				$("#upload_form").on('dragleave', function(e) { $(this).removeClass("OnDragging"); }  )

				function readURL(input) {
					if (input.files && input.files[0]) {
						$('.image-upload-wrap').hide();
						//$('#submitbuttn').show(); 
						$('.file-uploading').show(); 
						$("#upload_form").submit();   //doesnt have go BACK after that, if form is not "_BLANK" target
					}
				}
			</script>
			<div class="file-uploading">
				<div class="image-title-wrap">
				Uploading...
				</div>
			</div>
			<div id="submitbuttn" >
				<input type="submit" value="START UPLOAD!">
			</div>
			<!-- <button class="file-upload-btn" type="button" onclick="$('.file-upload-input').trigger( 'click' )">Upload .zip package</button> -->
		</form>
	</div>

	<div class="">
		<h1>First-time Instructions</h1>
		<ul>
			<li>Generate a pair of Public & Private SSH keys with PUTTY and save somewhere.</li>
			<li>Enter your hostinger account and go to <b>SSH</b> page for your target website(s) and enable "SSH ACCESS" for each of them.</li>
			<li>Create a text file on your desktop, and fill it with your data, and upload that file.
				<br/>
				<div class="example_code">
					<textarea class="example_textarea"><?xml version='1.0' encoding='UTF-8'?><data>
<!--          ########################## your email ##########################        -->
<send_ssl_to>yourmail+ssl@gmail.com</send_ssl_to>
<!--          ########### target domain with SSH IP & username ###############          -->
<domain>site1.com|31.220.20.165|u111111111</domain>
<domain>site2.com|31.220.20.165|u222222222|subdomain1,subdomain2</domain>
<!--          ########################## putty key ###########################          -->
<ssh_key>PuTTY-User-Key-File-2: ssh-rsa
Encryption: none
Comment: rsa-key-20180724
Public-Lines: 4
JFCSFSDfw93raC1yc2EAAAABJQAAAQEA26qWqWTPqO8Dw/SYGKvijCeCDrmOXiBP
+I1DP84Ew9Y4F/o+9VXSOdVBj9KH5wtnqNZX8BDE0jBqE3e67WHJH64hmFjIWVha
GMys7zcOtqH9HjP389s0L44FSU/ojnPch04VyVDtWppU7gre7jFS/leFFylo9rbY
EXPfzkcrzdDEFLKJhnbhfjkSDGW89346yreHDSo3deX2d8Q==
Private-Lines: 8
CXiT7EhPOXrFNYz4ArAB6UOXKcNmUPDgXNgx1jGjAQU48TsXDCdcsfewf3567u64
8PG9sGQGxF3Ez0LCN5AludxVf/16x4zVWorZln5Ysc7yLvHBgSgADwwUdbQRgE5R
W1CJiCO1n1fm/DvEqI+DQwjjHFeefyHX3sRx7MIhgAPo2DL4sYwrOX05vXlTVqvT
8PG9sGQGxF3Ez0LCN5AludxVf/16x4zVWorZln5Ysc7yLvHBgSgADwwUdbQRgE5R
CXiT7EhPOXrFNYz4ArAB6UOXKcNmUPDgXNgx1jGjAQU48TsXDCdcsfewf3567u64
URT+rf7bjro24tT5hDilAMT/okqiia9tFkhqz8UmhsDN4hfludF7vZdla747azwf
sf28dh0389rfu/jw9e4gifjr4sdf34grgfreg
Private-MAC: 83533438r96ydfschosdcniwe4rf7e9144trf</ssh_key>
</data></textarea>
				</div>
			</li>
		</ul>
	</div>
	
	<script>
	function downloadExample()
	{
		var content		= $("#exmaple_xml").html();
		var filename	= "example_xml";
		
		if(!contentType) contentType = 'application/octet-stream';
			var a = document.createElement('a');
			var blob = new Blob([content], {'type':contentType});
			a.href = window.URL.createObjectURL(blob);
			a.download = filename;
			a.click();
	}

	</script>
	<div id="exmaple_xml" style="display:none;">
		<div>aa</div>
	</div>


	<div class="mt-5">
		(Fork on <a href="https://github.com/tazotodua/All-Vulnerability-Scanners" target="_blank"><img src="https://i.imgur.com/lV6xBOI.png" width="100" /></a> )
	</div>

  </div>
</div>




</div>
</body>
</html>

