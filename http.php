<?php

class Http {

	// response info from request
	private $status;
	private $content_type;
	private $headers = array();
	
	// output data immediately after receiving it
	private $text = false;
	
	// curl stuff
	private $options;
	private $error;
	
	// will remain empty if we decide to output response immediately after reading
	private $data;
	
	/*
	http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
	*/
	// list of headers we care about to forward back to user
	
	private $forward = array('content-type', 'content-length', 'accept-ranges', 
		'content-range', 'content-disposition', 'location');
		
	private $mime_types = array(
		'text/html' => 'html',
		'text/plain' => 'html',
		'text/css' => 'css',
		'text/javascript' => 'js',
		'application/x-javascript' => 'js',
		'application/javascript' => 'js'
	);

	private function send_no_cache()
	{
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}
	
	private function headers_write($ch, $headers){
	
		$len = strlen($headers);
		$parts = explode(": ", $headers, 2);

		// must be an actual name:value header
		if(count($parts) == 2){
			
			$name = strtolower($parts[0]);
			$value = rtrim($parts[1]);
			
			// is it one of the headers we care about to forward back to user?
			if(in_array($name, $this->forward)){
			
				// proxify url that the url is trying to redirect to
				if($name == 'location'){
					$value = proxify_url($value);
				}
				
				$this->headers[$name] = $value;
			}
			
			// should we save output to string for parsing later or stream immediately?
			if($name == 'content-type'){

				// text/html; charset=utf-8
				$sc_pos = strpos($value, ";");
				$type = substr($value, 0, $sc_pos ? $sc_pos : 1024);
				
				if(isset($this->mime_types[$type])){
					$this->text = true;
				}
				
				$this->content_type = $type;
			
			} else if($name == 'set-cookie'){
			
				$this->forward_cookie($value);
			}
			
		} else if($len > 2){
		
			// must be status
			$this->status = $headers;
			
			// send immediately
			header($this->status);
			
			// no cache
			$this->send_no_cache();
			
		} else {

			// end of headers
			foreach($this->headers as $name => $value){
				header("{$name}: {$value}");
			}
			
			if(!isset($this->mime_types[$this->content_type]) && !isset($this->headers['content-disposition'])){
			
			
				//header('Content-Disposition: filename="playaa.mp4"');
			
			}
		}
		
		return $len;
	}
	
	// convert Set-Cookie: header value into proxy cookie
	private function forward_cookie($header){
		$nv_pairs = explode(";", $header);
		
		// cookie attributes we care about
		$name = '';
		$value = '';
		$expires = '';
		$domain = '';
		
		foreach($nv_pairs as $index => $pair){
			$pair = ltrim($pair);
			$parts = explode("=", $pair, 2);
			
			// first pair will always be cookie_name=value
			if($index == 0){
				$name = $parts[0];
				$value = $parts[1];
			} else if($parts[0] == 'expires'){
				$expires = $parts[1];
			} else if($parts[0] == 'domain'){
				$domain = $parts[1][0] == '.' ? substr($parts[1], 1) : $parts[1];
			}
		}

		$expires = empty($expires) ? 0 : strtotime($expires);
		$domain = empty($domain) ? URL_HOST : $domain;
		
		$cookie_name = COOKIE_PREFIX.str_replace(".", "_", $domain).'__'.$name;
		
		//var_dump("Set-Cookie before: ".$header);
		//var_dump("Set-Cookie after: ".$cookie_name."=".$value);
		
		setcookie($cookie_name, $value, time() + 60*60*60);
	}
	
	private function body_write($ch, $str)
	{
		$len = strlen($str);
	
		if($this->text){
			$this->data .= $str;
		} else {
			echo $str;
		}
		
		return $len;
	}
	
	function __construct()
	{
		$this->options = array(
			CURLOPT_CONNECTTIMEOUT 	=> 8,
			CURLOPT_TIMEOUT 		=> 0,
			
			// don't return anything - we have other functions for that
			CURLOPT_RETURNTRANSFER	=> false,
			CURLOPT_HEADER			=> false,
			
			// don't bother with ssl
			CURLOPT_SSL_VERIFYPEER	=> false,
			CURLOPT_SSL_VERIFYHOST	=> false,
			
			// let curl take care of redirects
			CURLOPT_FOLLOWLOCATION	=> false,
			CURLOPT_MAXREDIRS		=> 5,
			CURLOPT_AUTOREFERER		=> false
		);
		
		$this->options[CURLOPT_HEADERFUNCTION] = array($this, 'headers_write');
		$this->options[CURLOPT_WRITEFUNCTION] = array($this, 'body_write');
		
		
		global $config;
		
		if(isset($config['ip_addr'])){
			$this->options[CURLOPT_INTERFACE] = $config['ip_addr'];
		}
		
		if(isset($config['user_agent'])){
			$this->options[CURLOPT_USERAGENT] = $config['user_agent'];
		}
		
		// let's emulate the browser
		$headers = array(
				'Accept-Language: en-US,en;q=0.5',
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
        );
		
		$this->set_headers($headers);
	}
	
	function execute($url){
		$this->options[CURLOPT_URL] = $url;

		$ch = curl_init();
		curl_setopt_array($ch, $this->options);
		$re = curl_exec($ch);

		if(!$re){
			$this->error = sprintf('(%d) %s', curl_errno($ch), curl_error($ch));
		}
		
		return $re;
	}
	
	// will return either html, css, or js
	function get_simple_content_type(){
		$ct = $this->content_type;
		return isset($this->mime_types[$ct]) ? $this->mime_types[$ct] : false;
	}
	
	function set_headers($headers){
		$this->options[CURLOPT_HTTPHEADER] = $headers;
	}
	
	function error(){
		return $this->error;
	}
	
	function get_output(){
		return $this->data ? $this->data : false;
	}
	
	function set_post($post){
		if(is_array($post)){
			$post = http_build_query($post);
		}
		
		$this->options[CURLOPT_POST] = true;
		$this->options[CURLOPT_POSTFIELDS] = $post;
	}
}

?>