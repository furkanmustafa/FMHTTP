<?php

namespace FMHTTP;

class Request extends Base {
	public $url;
	public $uri = null;
	public $files = null;
	public $data = null;
	public $method = 'GET';
	public $postData = null;
	public $timeout = null;
	
	static $Request = null;
	
	function __construct($url = null) {
		if ($url)
			$this->url = $url;
	}
	function setJSON() {
		$this->setHeader('Content-Type', 'application/json');
	}
	function &send() {
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
		
		$response = new Response();
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
		} else if (!in_array($this->method, array('GET', 'POST'))) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
			if ($this->body)
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
		}
		
		$curlStatus = curl_exec($ch);
		$this->rawHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);
		curl_close($ch);
		
		$response->process();
		
		return $response;
	}
	
	function setBody($bodyString) {
		$this->body = $bodyString;
		
		// .. process depending on content-type
		
		// do this later:
		// Content-Type	multipart/form-data; boundary=----WebKitFormBoundaryp6D5nfMyUxAWWBwr
		
		if (count($_POST) || !isset($this->headers['Content-Type'])) {
			$this->data = $_POST;
		} else if ($this->headers['Content-Type']==='application/json') {
			$this->data = json_decode($this->body, true);
		}
	}
	
	static function InitCurrent() {
		if (self::$Request)
			return self::$Request;
		
		self::$Request = new Request();
		if (php_sapi_name() === 'cli') {
			self::$Request->setBody(stream_get_contents(STDIN));
			self::$Request->method = 'CLI';
		} else if (isset($_SERVER['REQUEST_METHOD'])) {
			self::$Request->uri = $_SERVER['REQUEST_URI'];
			self::$Request->method = $_SERVER['REQUEST_METHOD'];
			self::$Request->headers = self::__getAllHeaders();
			if (!$_FILES) {
				self::$Request->setBody(file_get_contents('php://input'));
			} else {
				self::$Request->files = $_FILES;
			}
		}
		return self::$Request;
	}
	
	static private function __getAllHeaders() {
		if (function_exists('getallheaders'))
			return getallheaders();
		
		$headers = '';
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}

Request::InitCurrent();
