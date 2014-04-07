<?php

/**

	Furkan Mustafa, HTTP Wrapper Classes

*/

namespace FMHTTP;

class Base {
	public $headers = array();
	public $rawHeaders;
	public $body = null;
	public $mimeType = null;
	public $version = 1.1;
	
	function setHeader($name, $value = null) {
		if ($value==null && preg_match('/^([^:]+): (.+)$/', trim($name, " \t\r\n"), $m)) {
			$name = $m[1];
			$value = $m[2];
		}
		$name = trim($name, " \t\r\n");
		$value = trim($value, " \t\r\n");
		if (strlen($name) > 0)
			$this->headers[$name] = $value;
		if ($name == 'Content-Type')
			$this->mimeType = $value;
	}
	function getHeader($name) {
		if (isset($this->headers[$name]))
			return $this->headers[$name];
		return null;
	}
	
	public static function ParseQueryString($query, $orig = false) {
		$items = $orig ? $orig : array();
		$_items = explode('&', $query);
		foreach ($_items as $item) {
			if (strpos($item, '=')===false) {
				$items[urldecode($item)] = true;
			}
			else {
				$itemp = explode('=', $item);
				$key = urldecode($itemp[0]);
				if (substr($key, -2)=='[]')
					$items[substr($key, 0, -2)][] = urldecode($itemp[1]);
				else
					$items[$key] = urldecode($itemp[1]);
			}
		}
		return $items;
	}
	public static function BuildQueryString($items) {
		$queryItems = array();
		foreach ($items as $name => $val) {
			if ($val===false) {
				continue;
			} elseif ($val===true) {
				$queryItems[] = urlencode($name);
			} elseif (is_array($val)) {
				foreach ($val as $_val) {
					$queryItems[] = urlencode($name).'[]='.urlencode($_val);
				}
			} else {
				$queryItems[] = urlencode($name).'='.urlencode($val);
			}
		}
		return implode('&', $queryItems);
	}
	public static function AppendQueryString($url, $queryValues) {
		$_qMarkPos = strpos($url, '?');
		$existingQuery = $_qMarkPos === false ? false : substr($url, $_qMarkPos + 1);
		$baseURL = $_qMarkPos !== false ? substr($url, 0, $_qMarkPos) : $url;
		$items = array();
		if ($existingQuery)
			$items = self::ParseQueryString($existingQuery);
		$queryValues = is_array($queryValues) ? self::BuildQueryString($queryValues) : $queryValues;
		$items = self::ParseQueryString($queryValues, $items);
		
		return $baseURL . '?' . self::BuildQueryString($items);
	}
	public function processedRequestBody() {
		if ($this->body)
			return $this->body;
		
		if (is_array($this->postData)) {
			if (!$this->getHeader('Content-Type')) {
				$this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
				$this->body = http_build_query($this->postData);
			} else if ($this->getHeader('Content-Type')==='application/json') {
				$this->body = json_encode($this->postData);
			}
		}
		else if (is_object($this->postData) && method_exists($this->postData, 'serializedData')) {
			$this->setHeader('Content-Type', 'application/json');
			$this->body = json_encode($this->postData->serializedData());
		}
		else {
			$this->body = (string)$this->postData;
		}
		return $this->body;
	}
	
}
