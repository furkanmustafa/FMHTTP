<?php

namespace FMHTTP;

class Request extends Message {
	public $url;
	public $path = null;
	public $uri = null;
	public $host = null;
	public $scheme = null;
	public $query = null;
	public $fragment = null;
	public $port = 80;
	
	public $files = null;
	public $data = null;
	public $method = 'GET';
	public $postData = null;
	public $timeout = null;
	
	public $ignoreSSLErrors = false;
	
	public $httpUser = false;
	public $httpPass = false;
	
	static $CurrentRequest = null;
	
	static $RequestWriter = null;
	
	function __construct($url = null) {
		if ($url)
			$this->setUrl($url);
	}
	
	function setURL($url) {
		$parts = parse_url($url);
		$this->url = $url;
		$this->path = $parts['path'];
		$this->host = $parts['host'];
		$this->scheme = $parts['scheme'];
		$this->query = $parts['query'];
		$this->uri = $this->path;
		if ($this->query)
			$this->uri .= '?' . $this->query;
		$this->fragment = $parts['fragment'];
		
		if (isset($parts['port'])) {
			$this->port = (int)$parts['port'];
		} else {
			$this->port = $this->scheme == 'https' ? 443 : 80;
		}
		$this->httpUser = isset($parts['user']) ? $parts['user'] : false;
		$this->httpPass = isset($parts['pass']) ? $parts['pass'] : false;
	}
	function setJSON() {
		$this->setHeader('Content-Type', 'application/json');
	}
	function &send() {
		if (!$this->url)
			return false;
		
		if (self::$RequestWriter) { // how do you think this should work?
			return self::$RequestWriter->sendRequest($this);
		}
		
		$opts['http']['method'] = $this->method;
		
		$this->method = strtoupper($this->method);
		
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
		
		if ($this->httpUser && $this->httpPass) {
			$headers[] = 'Authorization: Basic '. base64_encode($this->httpUser . ':' . $this->httpPass);
		}
		
		
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
		
		if ($this->ignoreSSLErrors) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
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
	
	static function InitCurrent($serverParameters = null, $inputStreamOrPayload = null) {
		if ($serverParameters === null)
			$serverParameters = $_SERVER;
		
		self::$CurrentRequest = new Request();
		if (php_sapi_name() === 'cli') {
			self::$CurrentRequest->setBody(stream_get_contents(STDIN));
			self::$CurrentRequest->method = 'CLI';
		} else if (isset($_SERVER['REQUEST_METHOD'])) {
			$port = (int)$_SERVER['SERVER_PORT'];
			$host = $_SERVER['HTTP_HOST'];
			$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? true : false;
			
			self::$CurrentRequest->uri = $_SERVER['REQUEST_URI'];
			self::$CurrentRequest->method = $_SERVER['REQUEST_METHOD'];
			self::$CurrentRequest->headers = self::__getAllHeaders();
			self::$CurrentRequest->setUrl(
				($https ? 'https' : 'http') . '://' . $host . 
				(($https && $port == 443) || (!$https && $port == 80) ? '' : ':' . $port) .
				self::$CurrentRequest->uri
			);
		}
		// TODO : Make raw multipart form data parser for uploaded files
		if (!$_FILES) {
			self::$CurrentRequest->setBody(file_get_contents('php://input'));
		} else {
			self::$CurrentRequest->files = $_FILES;
		}
		return self::$CurrentRequest;
	}
	static function Current() {
		if (!self::$CurrentRequest) {
			self::InitCurrent();
		}
		return self::$CurrentRequest;
	}
	
	static private function __getAllHeaders($serverParameters = null) {
		if ($serverParameters === null)
			$serverParameters = $_SERVER;
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
	
	function writeTo(&$buffer) {
		$stringBuffer = "HTTP/{$this->version} {$this->method} {$this->uri}\r\n";
		// Host
		$host = $this->getHeader('Host') ?: $this->host;
		$stringBuffer .= 'Host: '. $host . "\r\n";
		// Other Headers
		foreach ($this->headers as $name => $value) if (strtolower($name) != 'host')
			$stringBuffer .= $name . ': '. $value . "\r\n";
		
		$stringBuffer .= "\r\n";
		
		$bodyStr = $this->processedRequestBody();
		if (strlen($bodyStr))
			$stringBuffer .= $bodyStr;
		
		if (is_object($buffer) && is_callable([ $buffer, 'write' ])) {
			return $buffer->write($stringBuffer) == strlen($stringBuffer);
		} else if (is_resource($buffer)) { // stream or socket or file
			for ($written = 0; $written < strlen($stringBuffer); $written += $fwrite) {
			    $fwrite = fwrite($buffer, substr($stringBuffer, $written));
			    if ($fwrite === false) {
			        return false; // $written;
			    }
			}
			return true;
		} else if (is_string($buffer)) {
			$buffer .= $stringBuffer;
			return true;
		}
	}
}
