<?php

class XHamster_com extends ParserTemplate {

	private function get_video($html){

		$file = false;

		if(preg_match("@mp4File=(.*?)\"@s", $html, $matches) == 1){
			$file = $matches[1];
			$file = rawurldecode($file);
		} else if(preg_match("@srv=([^&]+)@s", $html, $matches) == 1 && preg_match("@file=([^&]+)@s", $html, $matches2) == 1){
		
			$srv = rawurldecode($matches[1]);
			$file = rawurldecode($matches2[1]);
			
			$file = "{$srv}/key={$file}";
		}
		
		return $file;
	}

	function parse($output, $url, $type){
	
		$vid = $this->get_video($output);
		
		if($vid){
			$output = preg_replace('@<div id=\'player\'(.*?)<\/object>.*?</div>@s', '<div id="player">'.vid_player($vid, 638, 505).'</div>', $output);
		}
		
		return $output;
	}

}

?>