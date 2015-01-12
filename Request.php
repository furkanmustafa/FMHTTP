<?php

namespace FMHTTP;

class Request extends Message {
	protected $url;
	public $path = null;
	public $uri = null;
	public $host = null;
	public $scheme = 'http';
	public $query = null;
	public $queryParameters = [];
	public $fragment = null;
	public $port = 80;
	
	public $files = null;
	public $cookies = null;
	public $data = null;
	public $method = 'GET';
	public $postData = null;
	public $timeout = null;
	
	public $server = null;
	
	public $ignoreSSLErrors = false;
	
	public $httpUser = false;
	public $httpPass = false;
	
	public $response = null;
	
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
		if (strpos($parts['host'], ':') === false)
			$this->host = $parts['host'];
		else
			$this->host = substr($parts['host'], 0, strpos($parts['host'], ':'));
		
		$this->scheme = $parts['scheme'];
		$this->query = isset($parts['query']) ? $parts['query'] : null;
		$this->queryParameters = Utils::ParseQueryString($this->query);
		
		$this->uri = $parts['path']; // $this->path;
		if ($this->query)
			$this->uri .= '?' . $this->query;
		$this->fragment = isset($parts['fragment']) ? $parts['fragment'] : null;
		
		if (isset($parts['port'])) {
			$this->port = (int)$parts['port'];
		} else {
			$this->port = $this->scheme == 'https' ? 443 : 80;
		}
		$this->httpUser = isset($parts['user']) ? $parts['user'] : false;
		$this->httpPass = isset($parts['pass']) ? $parts['pass'] : false;
	}
	function getURL() {
		$url = $this->scheme . '://' . $this->host;
		$url .= (($this->scheme == 'https' && $this->port == 443) || ($this->scheme != 'https' && $this->port == 80) ? '' : ':' . $this->port);
		$url .= $this->uri;
		if ($this->fragment) {
			$url .= '#' . $this->fragment;
		}
		return $url;
	}
	function setJSON() {
		$this->setHeader('Content-Type', 'application/json');
	}
	function &send() {
		if (!$this->getUrl())
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
		curl_setopt($ch, CURLOPT_URL, $this->getUrl());
		
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
		$response->setStatus(502, 'Bad Gateway');
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
		if (!$curlStatus) {
			$errorNo = curl_errno($ch);
			$errorStr = curl_error($ch);
			$response->setStatus(502, 'CURL: ['.$errorNo.'] '.$errorStr);
		} else {
			$this->rawHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);
			$response->process();
		}
		curl_close($ch);
		
		return $response;
	}
	
	function setBody($bodyString) {
		$this->body = $bodyString;
		
		// .. process depending on content-type
		
		// do this later:
		// Content-Type	multipart/form-data; boundary=----WebKitFormBoundaryp6D5nfMyUxAWWBwr
		
		// TODO :

		// Content-Type:multipart/form-data; boundary=----WebKitFormBoundaryQ2OQ5pkrPgwDnIsj

		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="version"
		//
		// 1.6
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="apiSelection"
		//
		// test
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="path"
		//
		//
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="key"
		//
		// aen7baixae4Pai6oP6Je0aPhieroahiR
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="secret"
		//
		// etu7aKuviazie5uteech2ke1baux5rai
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="method"
		//
		// get
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="getparams"
		//
		//
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="headers"
		//
		//
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="postparams"
		//
		//
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="files"; filename=""
		// Content-Type: application/octet-stream
		//
		//
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj
		// Content-Disposition: form-data; name="website"
		//
		//
		// ------WebKitFormBoundaryQ2OQ5pkrPgwDnIsj--
		
		if (count($_POST) || !isset($this->headers['Content-Type'])) {
			$this->data = $_POST;
		} else if ($this->getHeader('Content-Type') === 'application/json') {
			$this->data = json_decode($this->body, true);
		}
	}
	
	function setServer($serverParameters) {
		$this->server = $serverParameters;
		
		if (isset($serverParameters['REQUEST_METHOD']) && isset($serverParameters['REQUEST_URI'])) {
			$port = (int)$serverParameters['SERVER_PORT'];
			$host = $serverParameters['HTTP_HOST'];
			if (strpos($host, ':')===false) {
				$host = $host . (($https && $port == 443) || (!$https && $port == 80) ? '' : ':' . $port);
			}
			$https = isset($serverParameters['HTTPS']) && $serverParameters['HTTPS'] == 'on' ? true : false;
			
			$this->uri = $serverParameters['REQUEST_URI'];
			$this->method = $serverParameters['REQUEST_METHOD'];
			$this->headers = self::__getAllHeaders($serverParameters);
			$this->setUrl(
				($https ? 'https' : 'http') . '://' . $host . $this->uri
			);
		}
		else if (isset($_SERVER['argv'])) {
			$this->setContext('cli', true);
		}
	}
	
	function getQuery($path) {
		$value = self::GetKeyPath($this->queryParameters, $path, false);
		return $value;
	}
	
	function getCookie($name) {
		return $this->cookies->getCookie($name);
	}
	function setCookie($name, $value, $options = []) {
		$this->cookies->setCookie($name, $value, $options);
		$this->setHeader('Cookie', $this->cookies->getRequestHeader());
	}
	
	static function MakeWith($serverParameters = null, $inputStreamOrPayload = null, $files = null) {
		$request = new Request();
		
		if ($serverParameters === null)
			$serverParameters = $_SERVER;
		
		if ($inputStreamOrPayload === null)
			$inputStreamOrPayload = STDIN;
		
		if ($files === null && $_FILES)
			$files = $_FILES;
		
		// REQUEST INFO
		$request->setServer($serverParameters);
		
		// STDIN / BODY
		if ($inputStreamOrPayload !== null) {
			if (is_resource($inputStreamOrPayload)) {
				$stat = fstat($inputStreamOrPayload);
				if ($stat['size'] != 0) {
					$request->setBody(stream_get_contents($inputStreamOrPayload));
				}
			} else if ($inputStreamOrPayload) {
				$request->setBody($inputStreamOrPayload);
			}
		}
		
		// FILES
		if ($files !== null) {
			$request->files = $files;
		}
		
		// COOKIES
		if ($request->getHeader('Cookie')) {
			$request->cookies = new CookieStore($request->getHeader('Cookie'));
		} else {
			$request->cookies = new CookieStore();
		}
		
		return $request;
	}
	static function InitCurrent($serverParameters = null, $inputStreamOrPayload = null, $files = null) {
		self::$CurrentRequest = self::MakeWith($serverParameters, $inputStreamOrPayload, $files);
	}
	static function Current() {
		// if (!self::$CurrentRequest) {
		// 	if (php_sapi_name() === 'cli') {
		// 		self::InitCurrent($_SERVER, STDIN);
		// 	} else {
		// 		self::InitCurrent($_SERVER, file_get_contents('php://input'), $_FILES);
		// 	}
		// }
		return self::$CurrentRequest;
	}
	
	static private function __getAllHeaders($serverParameters = null) {
		if ($serverParameters === null)
			$serverParameters = $_SERVER;
		
		// if (function_exists('getallheaders'))
		// 	return getallheaders();
		//
		$headers = [];
		foreach ($serverParameters as $name => $value) {
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
