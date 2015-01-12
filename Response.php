<?php

namespace FMHTTP;

class Response extends Message {
	public $statusCode           = 200;
	public $statusMessage        = 'OK';
	public $request              = null;

	public $data                 = null;
	public $errorData            = null;
	public $cookies = null;

	private $rawHeadersFinished  = false;

	static $CurrentResponse      = null;

	static $ResponseWriter       = null;

	function __construct() {
		$this->cookies = new CookieStore;
	}

	static function To(Request $request) {
		$response = new static();
		$response->request = $request;
		$request->response = $response; // BEWARE: CROSS REFERENCING HERE
		return $response;
	}

	function getAnyData() {
		return $this->statusCode >= 400 ? $this->errorData : $this->data;
	}

	function curlSetHeader(&$ch, $header) {
		if (preg_match('/^HTTP\/([0-9\.]+) ([0-9]+) (.+)$/', trim($header, " \t\r\n"), $m)) {
			$this->version = (double)$m[1];
			$this->statusCode = (int)$m[2];
			$this->statusMessage = trim($m[3]);
			return strlen($header);
		}
		$this->setHeader($header);
		return strlen($header);
	}
	function curlSetBody(&$ch, $body) {
		if (!$this->rawHeadersFinished) { // CURLOPT_HEADER gives raw headers here
			if (trim($body, " \t\r\n")=='') {
				if (preg_match('/^HTTP\/([0-9\.]+) 100 (.+)$/', $this->rawHeaders)) {
					$this->rawHeaders = '';
				} else {
					$this->rawHeadersFinished = true;
					$this->body = null;
				}
			} else {
				$this->rawHeaders .= $body;
			}
		} else {
			if ($this->body === null)
				$this->body = $body;
			else
				$this->body .= $body;
		}
		return strlen($body);
	}

	function process() {
		$dataTarget = $this->statusCode >= 400 ? 'errorData' : 'data';
		
		if ($this->getHeader('Content-Type') === null ) {
			$this->$dataTarget = $this->body;
		}
		else if ($this->getHeader('Content-Type') === 'application/json') {
			$this->$dataTarget = json_decode($this->body, true);
		}
		// add more formats can be easily processed into data.
	}

	function getCookie($name) {
		return $this->cookies->getCookie($name);
	}
	function setCookie($name, $value, $options = []) {
		$this->cookies->setCookie($name, $value, $options);
	}
	
	function __tostring() {
		return $this->body;
	}

	function setAsCurrent() {
		self::$CurrentResponse = $this;
	}
	static function Current() {
		if (!self::$CurrentResponse) {
			self::$CurrentResponse = new static();
		}
		return self::$CurrentResponse;
	}

	function send() {
		if (self::$ResponseWriter) {
			return self::$ResponseWriter->sendResponse($this);
		}
		// Use default php output
		while (ob_get_level() > 0)
			ob_end_clean();
		
		if ($this->statusCode) {
			header("HTTP/{$this->version} {$this->statusCode} {$this->statusMessage}");
		}
		$bodyStr = $this->body;
		$headers = $this->headers;
		$this->cookies->writeToPHP();
		
		$headers['Content-Length'] = strlen($bodyStr);
		
		foreach ($headers as $name => $value) {
			if (is_array($value)) {
				foreach ($value as $subval) {
					header($name . ': '. $subval);
				}
			} else {
				header($name . ': '. $value);
			}
		}
		
		echo $bodyStr;
	}

	function payload() {
		$stringBuffer = "HTTP/{$this->version} {$this->statusCode} {$this->statusMessage}\r\n";
	
		$bodyStr = $this->body;
	
		$headers = $this->headers;
		// if (!$this->getHeader('Content-Length'))
		// 	$headers['Content-Length'] = strlen($bodyStr);

		foreach ($headers as $name => $value)
			$stringBuffer .= $name . ': '. $value . "\r\n";
		$stringBuffer .= "\r\n";
		if (strlen($bodyStr))
			$stringBuffer .= $bodyStr;

		return $stringBuffer;
	}
	function writeToStream($resource) {
		$stringBuffer = $this->payload();
		
		for ($written = 0; $written < strlen($stringBuffer); $written += $fwrite) {
		    $fwrite = fwrite($buffer, substr($stringBuffer, $written));
		    if ($fwrite === false) {
		        return false; // $written;
		    }
		}
		return true;
	}
	function writeTo(&$buffer) {
		$stringBuffer = $this->payload();
		
		if (is_object($buffer) && is_callable([ $buffer, 'write' ])) {
			return $buffer->write($stringBuffer) == strlen($stringBuffer);
		} else if (is_resource($buffer)) { // stream or socket or file
			$this->writeToStream($buffer);
		} else if (is_string($buffer)) {
			$buffer .= $stringBuffer;
			return true;
		}
	}
	
	function setStatus($code, $message) {
		$this->statusCode = $code;
		$this->statusMessage = $message;
	}
	
}
