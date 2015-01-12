<?php

namespace FMHTTP;
use DateTime;

class Cookie {
	
	public $name = null;
	public $value = null;
	public $domain = null;
	public $path = null;
	public $expire = 0;
	public $httponly = false;
	public $secure = false;
	
	public $new = false;

	function setLine($line) {
		$itemparts = explode('=', trim($line));
		$this->name = urldecode(trim($itemparts[0]));
		$this->value = urldecode(trim($itemparts[1]));
	}
	function getLine() {
		$line = $this->name . '=' . urlencode($this->value);
		if ($this->domain) {
			$line .= '; Domain=' . $this->domain;
		}
		if ($this->path) {
			$line .= '; Path=' . $this->path; 
		}
		if ($this->expire !== null) {
			if (is_a($this->expire, 'DateTime')) {
				$line .= '; Expires=' . $this->expire->format(DateTime::COOKIE);
			} else {
				$line .= '; Expires=' . date(DateTime::COOKIE, $this->expire);
			}
		}
		if ($this->secure) {
			$line .= '; Secure';
		}
		if ($this->secure) {
			$line .= '; HttpOnly';
		}
		return $line;
	}
	
	function __construct($line = null) {
		if ($line) {
			$this->setLine($line);
		}
	}
	
	function __toString() {
		return $this->value;
	}
	
}
