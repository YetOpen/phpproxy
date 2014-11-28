<?php

class DailyMotion_com extends ParserTemplate {

	function parse($output, $url, $type){
	
	
		if(preg_match("@video_url%22%3A%22(.*?)%22%2C%22@is", $output, $matches)){
			$url = rawurldecode($matches[1]);
			$url = rawurldecode($url);
			
			$output = preg_replace('#\<div\sclass\=\"dmpi_video_playerv4(.*?)>.*?\<\/div\>#s', 
			'<div class="dmpi_video_playerv4${1}>'.vid_player($url, 620, 348).'</div>', $output, 1);
		}
		
		return $output;
	}
}

?>