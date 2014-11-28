<?php

class Xvideos_com extends ParserTemplate {

	private function get_video($html){
	
		if(preg_match('@flv_url=([^&]+)@', $html, $matches)){
			return rawurldecode($matches[1]);
		}
	
		return false;
	}
	
	function parse($output, $url, $type){
	
		// annoying on all pages
		$output = preg_replace("@<script>thumbcastDisplayRandomThumb\\('(.*?)'\\)@s", "$1", $output);
		
		$vid = $this->get_video($output);
		
		if($vid){
			$output = preg_replace('@<div id="player.*?<\\/div>@s', '<div id="player">'.vid_player($vid, 588, 476).'</div>', $output, 1);
		}
		
		return $output;
	}

}

class YouTube_com extends ParserTemplate {

	function parse($output, $url, $type){
	
		return $output;
	}

}

?>