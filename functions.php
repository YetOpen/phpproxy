<?php

function base64_url_encode($input){
	// = at the end is just padding to make the length of the str divisible by 4
	return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function base64_url_decode($input){
	return base64_decode(str_pad(strtr($input, '-_', '+/'), strlen($input) % 4, '=', STR_PAD_RIGHT));
}

function data_rot($data, $pass, $reverse = false){
	
	$data_len = strlen($data);
	$pass_len = strlen($pass);
	
	if($pass_len == 0) trigger_error("fnc:data_rot password must not be empty!", E_USER_ERROR);
	
	$result = str_repeat(' ', $data_len);

	for($i=0; $i<$data_len; $i++){
		$asc = ord($data[$i])+(ord($pass[$i%$pass_len]) * ($reverse ? -1 : 1));
		$result[$i] = chr($asc);
	}
	
	return $result;
}

function encrypt_url($url){
	
	global $config;
	
	if($config['unique_urls'] === 2){
		$url = data_rot($url, USER_IP_LONG);
	}
	
	return base64_url_encode($url);
}

function decrypt_url($url){
	
	global $config;
	
	$url = base64_url_decode($url);
	
	if($config['unique_urls'] === 2){
		$url = data_rot($url, USER_IP_LONG, true);
	}
	
	return $url;
}

function add_http($url){
	if(!preg_match('#^https?://#i', $url)){
		$url = 'http://' . $url;
	}
	
	return $url;
}

function time_ms(){
	return round(microtime(true) * 1000);
}

function proxify_url($url){
	$url = htmlspecialchars_decode($url);
	$url = rel2abs($url, URL); // URL is the base
	return SCRIPT_BASE.'?q='.encrypt_url($url);
}

function contains($needle, $haystack){

	if(is_array($needle)){
		
		foreach($needle as $n){
			if(contains($n, $haystack)){
				return true;
			}
		}
		
		return false;
	
	} else if(is_array($haystack)){
		
		foreach($haystack as $h){
			if(contains($needle, $h)){
				return true;
			}
		}
		
		return false;
	}
	
	return strpos($haystack, $needle) !== false;
}


function str_contains($str, $arr){
	if(!is_array($arr)) return false;
	
	foreach($arr as $item){
		if(strpos($str, $item) !== false) return true;
	}
	
	return false;
}


// default cookie format: COOKIE_PREFIX+domain__cname=cvalue;
function decode_http_cookie(){

	// 2 fucking days spent figuring this out... suhosin.cookie.max_name_length
	$http_cookie = $_SERVER['HTTP_COOKIE'];
	$cookie_pairs = array();
	
	if(preg_match_all('@'.COOKIE_PREFIX.'(.+?)__(.+?)=([^;]+)@', $http_cookie, $matches, PREG_SET_ORDER)){
	
		foreach($matches as $match){
		
			$domain = $match[1];
			$domain = str_replace("_", ".", $domain);
			
			$name = $match[2];
			$value = $match[3];
			
			// does that cookie belong to that domain
			if(strpos(URL_HOST, $domain) !== false){
				$cookie_pairs[] = "{$name}={$value}";
			}
		}
	}
	
	return "Cookie: ".implode("; ", $cookie_pairs);
}

function replace_placeholders($str, $callback = null){

	global $tpl_vars;

	preg_match_all('@{(.+?)}@s', $str, $matches, PREG_SET_ORDER);
	
	foreach($matches as $match){
	
		$var_val = $tpl_vars[$match[1]];
		
		if(function_exists($callback)){
			$var_val = @call_user_func($callback, $var_val);
		}
		
		$str = str_replace($match[0], $var_val, $str);
	}
	
	return $str;
}


function log_url($url){
	// log it!
	$date = date("Y-m-d H:i:s T");

	$filename = date("m.d.y").'.log';

	$format = "{ip} {date} {url} {status}";

	$file = fopen('log.txt', 'a');//'./logs/'.$filename, 'a');
	fwrite($file, $_SERVER['REMOTE_ADDR'].' ['.$date.'] "'.$url."\"\n");
	fclose($file);
}

function rel2abs($rel, $base)
{
	if (strpos($rel, "//") === 0) {
		return "http:" . $rel;
	}

	/* return if  already absolute URL */
	if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
	/* queries and  anchors */
	if ($rel[0] == '#' || $rel[0] == '?') return $base . $rel;
	/* parse base URL  and convert to local variables:
	$scheme, $host,  $path */
	extract(parse_url($base));
	/* remove  non-directory element from path */
	$path = preg_replace('#/[^/]*$#', '', $path);
	/* destroy path if  relative url points to root */
	if ($rel[0] == '/') $path = '';
	/* dirty absolute  URL */
	$abs = "$host$path/$rel";
	/* replace '//' or  '/./' or '/foo/../' with '/' */
	$re = array(
		'#(/\.?/)#',
		'#/(?!\.\.)[^/]+/\.\./#'
	);
	for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {
	}

	/* absolute URL is  ready! */
	return $scheme . '://' . $abs;
}


?>