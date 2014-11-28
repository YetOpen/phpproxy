<?php

// report all errors but don't display them publicly
//error_reporting(E_ALL);
//ini_set('display_errors', 0);

if(!function_exists("curl_init")){
	die("cURL extension was not found on this server.");
}

require("config.php");

require("functions.php");
require("http.php");
require("parse.php");

require("parser/ParserTemplate.php");


// suhosin has a limit of 512 max chars in $_GET
parse_str($_SERVER['QUERY_STRING'], $_GET);

define('USER_IP', $_SERVER['REMOTE_ADDR']);
define('USER_IP_LONG', sprintf("%u", ip2long(USER_IP)));

/* constants to be used everywhere */
define('VERSION', '0.9');
define('SCRIPT_BASE', (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);

// these below will be defined later

//define('URL', '');
//define('URL_HOST', '');

define('COOKIE_PREFIX', 'dc_');

/* variables sent to each view template */
$tpl_vars = array(
	'script_base' => SCRIPT_BASE,
	'version' => VERSION
);

function throw_error($type, $message){

	global $tpl_vars, $config;
	
	$tpl_vars['error_type'] = $type;
	$tpl_vars['error_msg'] = $message;
	
	// handle this error somewhere else maybe?
	if(!empty($config['error_redirect'])){
		$url = $config['error_redirect'];
		$url = replace_placeholders($url, 'rawurlencode');
		
		header("HTTP/1.1 302 Found");
		header("Location: {$url}");
		exit;
	}
	
	echo tpl_include('home');
	exit;
}


function tpl_include($name){

	global $tpl_vars, $static_tpl;
	
	// declare variables in this scope to be used in our templates
	extract($tpl_vars);
	
	// this is where the views will be stored
	$file_path = 'templates/'.$name.'.tpl.php';
	
	ob_start();
	
	if(file_exists($file_path)){
		include($file_path);
	} else {
		die("Failed to load template: {$name}");
	}
	
	$contents = ob_get_contents();
	@ob_end_clean();
	
	return $contents;
}

// url is being sent from a form raw - let's encode it and send it back to this script
if(isset($_POST['url'])){

	$url = $_POST['url'];
	$url = add_http($url);
	
	$q = encrypt_url($url);
	
	header("HTTP/1.1 302 Found");
	header('Location: '.SCRIPT_BASE.'?q='.$q);
	exit;
	
} else if(isset($_GET['q'])){

	// url sent encoded - let's decode it and make it work
	$url = decrypt_url($_GET['q']);
	
	log_url($url);
	
	define('URL', $url);
	define('URL_HOST', parse_url($url, PHP_URL_HOST));
	
	$tpl_vars['url'] = $url;
	
} else {
	
	// you are at home page!
	echo tpl_include('home');
	exit;
}

// banned ip trying to use our script?
if(@in_array(USER_IP, $config['blocked_ips'])){
	throw_error("blocked_ip", "Requests from this IP address have been blocked for some reason");
}

// are we trying to access a site that's been blocked?
if(str_contains(URL_HOST, @$config['blocked_domains'])){
	throw_error("blocked_domain", "This domain has been blocked");
}



$possible_headers = array(
	'HTTP_ACCEPT' => 'Accept',
	'HTTP_ACCEPT_CHARSET' => 'Accept-Charset',
	'HTTP_ACCEPT_LANGUAGE' => 'Accept-Language'
);

$send_headers = array();

foreach($possible_headers as $key => $value){
	if(isset($_SERVER[$key])){
		$send_headers[] = $value.': '.$_SERVER[$key];
	}
}

// pass on any cookies sent to our proxy script from previous sessions
if($config['enable_cookies']){
	$send_headers[] = decode_http_cookie();
}

$http = new Http();

// send additional headers from our user to our http object to be sent to each url we visit
$http->set_headers($send_headers);

// do we wish to send some post data?
if($_SERVER['REQUEST_METHOD'] == 'POST'){
	$http->set_post($_POST);
}

// ready to execute!!!
$http->execute(URL);

// get output if available
$output = $http->get_output();

// if has output - we need to parse it
if($output && function_exists('parse')){

	// what kind of page was returned
	$content_type = $http->get_simple_content_type();

	$output = parse($url, $output, $content_type);
	
} else if($http->error()){

	throw_error("curl_error", $http->error());
}


if($content_type == 'html'){

	$url_form = tpl_include('url_form');
	
	// does the html page contain <body> tag, if so insert our form right after <body> tag starts
	$output = preg_replace('@<body.*?>@is', '$0'.PHP_EOL.$url_form, $output, 1, $count);
	
	// <body> tag was not found, just put the form at the top of the page
	if($count == 0){
		$output = $url_form.$output;
	}
}

echo $output;

?>