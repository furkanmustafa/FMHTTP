<?php

namespace FMHTTP;

class Response extends Base {
	public $statusCode = null;
	public $statusMessage = null;
	public $request = null;
	
	// public $parser = null;
	public $parsedData = null;
	
	private $rawHeadersFinished = false;
	
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
	
	function __tostring() {
		return $this->body;
	}
	
	function send() {
		foreach ($this->headers as $name => $value)
			header($name . ': '. $value);
		$bodyStr = $this->processedRequestBody();
		header('Content-Length: ' . $bodyStr);
		echo $bodyStr;
	}
}
