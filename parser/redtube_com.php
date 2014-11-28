<?php

class RedTube_com extends ParserTemplate {

	function parse($output, $url, $type){
	
		if(preg_match('@mp4_url=(.*?)&@', $output, $matches)){
			$vid_url = rawurldecode($matches[1]);
			
			$output = preg_replace('@<div id="redtube_flv_player"(.*?)>.*?<noscript>.*?<\/noscript>.*?<\/div>@s', 
			'<div id="redtube_flv_player"$1>'.vid_player($vid_url, 610, 490).'</div>', $output);
		}
		
		return $output;
	}

}

?>