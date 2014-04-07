<?php

namespace FMHTTP;

class Request extends Base {
	public $url;
	public $method = 'GET';
	public $postData = null;
	public $timeout = null;
	
	public function __construct($url = null) {
		if ($url)
			$this->url = $url;
	}
	public function setJSON() {
		$this->setHeader('Content-Type', 'application/json');
	}
	public function &send() {
		if (!$this->url)
			return false;
		$opts['http']['method'] = $this->method;
		
		if ($this->method=='POST') {
			$this->body = $this->processedRequestBody();
			
			// $this->headers['Content-Length'] = strlen($this->body);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url);
		
		$headers = array();
		foreach ($this->headers as $key => $value) {
			$headers[] = "$key: $value";
		}
		if (!isset($this->headers['Expect']))
			$headers[] = "Expect:";
		
		if (count($headers) > 0)
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		if ($this->timeout!=null)
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		
		$response = new FMHTTPResponse();
		$response->request = $this;
		
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		
		// Getting Request Headers
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($response, 'curlSetHeader'));
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($response, 'curlSetBody'));
		
		if ($this->method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
		} else if ($this->method == 'PUT') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
		}
		
		$curlStatus = curl_exec($ch);
		$this->rawHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		curl_close($ch);
		
		return $response;
	}
}

// forgive me for the ugliness below. not my fault.

if (!function_exists('getallheaders')) {
	function getallheaders() {
		$headers = '';
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}
