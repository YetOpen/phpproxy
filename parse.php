<?php

// otherwise preg_replace_callback couldn't handle very large strings -- increase the default by 100
ini_set('pcre.backtrack_limit', 1000000 * 100);

function css_url($matches){
	$url = trim($matches[1]);
	
	if(stripos($url, 'data:') === 0){
		return $matches[0];
	}
	
	return 'url(\''.proxify_url($url).'\')';
}

function proxify_css($str){
	$str = preg_replace_callback('@url\s*\((?:\'|"|)(.*?)(?:\'|"|)\)@im', 'css_url', $str);
	
	return $str;
}

function html_href($matches){
	return 'href="'.proxify_url($matches[1]).'"';
}

function html_src($matches){

	if(stripos(trim($matches[1]), 'data:') === 0){
		return $matches[0];
	}
	
	return 'src="'.proxify_url($matches[1]).'"';
}

function html_action($matches){

	$new_action = proxify_url($matches[1]);
	$result = str_replace($matches[1], $new_action, $matches[0]);
	
	// change form method to POST!!!
	$result = str_replace("<form", '<form method="POST"', $result);
	return $result;
}


function proxify_html($str){
	
	$str = proxify_css($str);

	// html
	$str = preg_replace_callback('@href=["|\'](.+?)["|\']@im', 'html_href', $str);
	$str = preg_replace_callback('@src=["|\'](.+?)["|\']@i', 'html_src', $str);
	$str = preg_replace_callback('@<form[^>]*action=["|\'](.+?)["|\'][^>]*>@i', 'html_action', $str);
	
	$str = preg_replace_callback('@<meta\s*http-equiv="refresh"\s*content="[^;]*;\s*url=(.*?)"@i', function($matches){
		return str_replace($matches[1], proxify_url($matches[1]), $matches[0]);
	}, $str);
	
	return $str;
}


// video player to be used
define('PLAYER_URL', '//www.php-proxy.com/assets/flowplayer-latest.swf');

function vid_player($url, $width, $height){

	$video_url = proxify_url($url); // proxify!
	$video_url = rawurlencode($video_url); // encode before embedding it into player's parameters
	
	$html = '<object id="flowplayer" width="'.$width.'" height="'.$height.'" data="'.PLAYER_URL.'" type="application/x-shockwave-flash">
 	 
       	<param name="allowfullscreen" value="true" />
		<param name="wmode" value="transparent" />
        <param name="flashvars" value=\'config={"clip":"'.$video_url.'", "plugins": {"controls": {"autoHide" : false} }}\' />
		
    </object>';
	
	return $html;
}






function replace_title($input, $replace = ''){
	return preg_replace('@<title>.*?<\/title>@s', '<title>'.$replace.'</title>', $input);
}

function remove_script($input){
	$result = preg_replace("@<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>@is", '', $input);
	$result = preg_replace("@<\s*script\s*>(.*?)<\s*/\s*script\s*>@is", '', $result);
	
	return $result;
}

function chop_sub($host){

	$parts = explode(".", $host);
	
	if(count($parts) > 2){
		unset($parts[0]);
		return implode(".", $parts);
	}
	
	return false;
}


// will only be called on html, js, css, and other text-like pages
function parse($url, $output, $type){

	// let's measure the time it takes to proxify and regex replace some file
	$start = time_ms();



	// check something.www.youtube.com then www.youtube.com then youtube.com...
	$h = URL_HOST;
	
	do {

		$class_name = str_replace(".", "_", $h);
		$file = $class_name.'.php';
		
		if(file_exists('parser/'.$file)){
		
			// load that parser!
			include('parser/'.$file);
			
			if(class_exists($class_name, true)){
		
				
				$parser = new $class_name;
				
				$output = $parser->parse($output, $url, $type);
			

				
			}
			
			break;
		} else {
			$h = chop_sub($h);
		}
	
	} while ($h);
	


	
	// remove all iframe
	$output = preg_replace('@<iframe.*?>.*?<\/iframe>@s', '', $output);
	

	global $config;

	if($type == 'html'){
	
		if($config['remove_script']){
			$output = remove_script($output);
		}
		
		$output = proxify_html($output);
	}

	if($config['replace_title']){
		$output = replace_title($output, $config['replace_title']);
	}
	
	if($type == 'css'){
		$output = proxify_css($output);
	}

	$time = time_ms() - $start;
	$output .= '<!-- parsed in '.$time.' milliseconds using proxy! -->';
	
	return $output;
}



?>