<?php

namespace FMHTTP;

class CookieStore {
	protected $store = [];
	
	private static function _ParseCookies($headerLine) {
		$items = explode(';', $headerLine);
		$store = array();
		foreach ($items as $line) {
			$itemparts = explode('=', trim($line));
			if (count($itemparts) < 2) continue;
	
			$value = urldecode(trim($itemparts[1]));
			if ($value=='' || $value=='deleted') continue;
			
			$store[] = new Cookie($line);
		}
		return $store;
	}
	
	function __construct($payload = null) {
		if ($payload)
			$this->store = self::_ParseCookies($payload);
	}
	function getResponseHeaders() {
		$headers = [];
		foreach ($this->store as $cookie) {
			if (!$cookie->new) continue;
			$headers[] = 'Set-Cookie: ' . $cookie->getLine();
		}
		return $headers;
	}
	function getRequestHeader() {
		$values = [];
		foreach ($this->store as $cookie) {
			if ($cookie->value = '' || $cookie->value == 'deleted') continue;
			$values[] = $cookie->name . '=' . urlencode($cookie->value);
		}
		return implode('; ', $values);
	}
	
	function addCookie($cookie) {
		$this->store[] = $cookie;
	}
	function deleteCookie($name, $options = []) {
		$matchingCookies = [];
		$wasDeleted = null;
		foreach ($this->store as $idx => $cookie) {
			if (strtolower($cookie->name) != strtolower($name)) continue;
			$matchingCookies[] = $cookie;
			if ($cookie->value === '' || $cookie->value === 'deleted') {
				if ($cookie->new) {
					$wasDeleted = true;
				} else if ($wasDeleted === null) {
					$wasDeleted = true;
				}
				continue;
			}
			if ($cookie->new && ($cookie->value === '' || $cookie->value === 'deleted')) {
				// it's deleted
				$wasDeleted = true;
				continue;
			}
			if ($cookie->new) {
				$wasDeleted = false;
				continue;
			}
			$wasDeleted = false;
		}
		if (!$wasDeleted) {
			$deletionCookie = new Cookie($name, 'deleted', $options);
		}
		$this->addCookie($deletionCookie);
	}
	function getCookie($name, $onlyNew = false) {
		$foundCookie = null;
		foreach ($this->store as $idx => $cookie) {
			if (strtolower($cookie->name) != strtolower($name)) continue;
			if ($onlyNew && !$cookie->new) continue;
			if ($cookie->value === '' || $cookie->value === 'deleted') continue;
			if ($foundCookie && $foundCookie->new && !$cookie->new) continue; // return the newer one
			// return the latest one anyway
			$foundCookie = $cookie;
		}
		return $foundCookie;
	}
	function setCookie($name, $value, $options = []) {
		$exitstingCookie = $this->getCookie($name, true);
		if (!$exitstingCookie) {
			$cookie = new Cookie();
			$cookie->new = true;
			$cookie->name = $name;
		} else {
			$cookie = $exitstingCookie;
		}
		$cookie->value = $value;
		foreach ($options as $option => $optionValue) {
			$cookie->{$option} = $optionValue;
		}
		if (!$exitstingCookie)
			$this->addCookie($cookie);
	}
	
	function writeToPHP() {
		foreach ($this->store as $cookie) {
			if (!$cookie->new) continue;
			setcookie($cookie->name, $cookie->value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httponly);
		}
	}
}
