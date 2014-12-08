<?php

namespace FMHTTP;

class Response extends Message {
	public $statusCode = null;
	public $statusMessage = null;
	public $request = null;
	
	public $data = null;
	public $errorData = null;
	
	private $rawHeadersFinished = false;
    
    static $CurrentResponse = null;
	
	static $ResponseWriter = null;
	
	public $context = [];
	
	function getAnyData() {
		return $this->statusCode >= 400 ? $this->errorData : $this->data;
	}
	
	function curlSetHeader(&$ch, $header) {
		if (!$this->statusCode && preg_match('/^HTTP\/([0-9\.]+) ([0-9]+) (.+)$/', trim($header, " \t\r\n"), $m)) {
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
		
		if (!isset($this->headers['Content-Type'])) {
			$this->$dataTarget = $this->body;
		}
		else if ($this->headers['Content-Type']==='application/json') {
			$this->$dataTarget = json_decode($this->body, true);
		}
		// add more formats can be easily processed into data.
	}
	
	function __tostring() {
		return $this->body;
	}
	
	static function Current() {
		if (!self::$CurrentResponse) {
			self::$CurrentResponse = new Response;
		}
		return self::$CurrentResponse;
	}
	
	function send() {
		if (self::$ResponseWriter) {
			return self::$ResponseWriter->sendResponse($this);
		}
		// Use default php output
		if ($this->statusCode) {
			header("HTTP/{$this->version} {$this->statusCode} {$this->statusMessage}");
		}
		$bodyStr = $this->processedRequestBody();
		$this->headers['Content-Length'] = strlen($bodyStr);
		foreach ($this->headers as $name => $value)
			header($name . ': '. $value);
		
		echo $bodyStr;
	}
	
	function writeTo(&$buffer) {
		$stringBuffer = "HTTP/{$this->version} {$this->statusCode} {$this->statusMessage}\r\n";
		foreach ($this->headers as $name => $value)
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
