<?php

/**

	Furkan Mustafa, HTTP Wrapper Classes

*/

namespace FMHTTP;

class Message {
	public $headers = array();
	public $rawHeaders;
	public $body = null;
	public $mimeType = null;
	public $mimeTypeOptions = null;
	public $version = 1.1;
	
	protected $context = [];
	
	function setHeader($name, $value = null) {
		if ($value==null && preg_match('/^([^:]+): (.+)$/', trim($name, " \t\r\n"), $m)) {
			$name = $m[1];
			$value = $m[2];
		}
		$name = trim($name, " \t\r\n");
		$value = trim($value, " \t\r\n");
		if (strlen($name) > 0)
			$this->headers[$name] = $value;
		if ($name == 'Content-Type') {
			$this->mimeType = $value;
			if (preg_match('/^([^;]+)(?:;(.+))?$/', $value, $match)) {
				$this->mimeType = trim($match[1]);
				if (isset($match[2])) {
					$_otherParams = explode(';', trim($match[2]));
					$this->mimeTypeOptions = array();
				
					foreach ($_otherParams as $_op) {
						$split = explode('=', trim($_op));
						if (count($split) == 2) {
							$this->mimeTypeOptions[trim($split[0])] = trim($split[1]);
						} else if ($split[0]) {
							$this->mimeTypeOptions[$split[0]] = true;
						}
					}
				}
			}
		}
	}
	function addHeader($name, $value = null) {
		if ($value==null && preg_match('/^([^:]+): (.+)$/', trim($name, " \t\r\n"), $m)) {
			$name = $m[1];
			$value = $m[2];
		}
		$name = trim($name, " \t\r\n");
		$value = trim($value, " \t\r\n");
		//
		$this->headers[$name][] = $value;
	}
	function getHeader($name) {
		if (isset($this->headers[$name]))
			return $this->headers[$name];
		
		foreach ($this->headers as $_name => $_value) {
			if (strtolower($_name) != strtolower($name)) continue;
			return $_value;
		}
		return null;
	}
	function unsetHeader($name) {
		if (isset($this->headers[$name])) {
			unset($this->headers[$name]);
			return;
		}
		
		foreach ($this->headers as $_name => $_value) {
			if (strtolower($_name) != strtolower($name)) continue;
			unset($this->headers[$_name]);
		}
		return;
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
	function getBody() {
		return $this->processedRequestBody();
	}
	
	function __get($var) {
		$methodName = 'get'.ucfirst($var);
		if (method_exists($this, $methodName)) {
			return $this->$methodName();
		}
		
		if (property_exists($this, $var))
			return $this->$var;
		
		throw new \Exception('No such property ' . get_called_class() . '->' . $var);
	}
	function __set($var, $val) {
		$methodName = 'set'.ucfirst($var);
		if (method_exists($this, $methodName)) {
			return $this->$methodName($val);
		}
		if (property_exists($this, $var)) {
			$this->$var = $val;
			return;
		}
		throw new \Exception('No such property ' . get_called_class() . '->' . $var);
	}
	function __call($methodname, $args) {
		if (preg_match('/^(set|get)([A-Z]{1}.+)$/', $methodname, $match)) {
			$varname = strtolower(substr($match[2], 0, 1)) . substr($match[2], 1);
			if ($match[1] === 'get') {
				return $this->__get($varname);
			} else {
				return $this->__set($varname, $args[0]);
			}
		}
		throw new \Exception('No such method ' . get_called_class() . '->' . $methodname);
	}
	
	function getContext($path) {
		$value = self::GetKeyPath($this->context, $path, false);
		return $value;
	}
	function setContext($path, $value) {
		$place = &self::GetKeyPath($this->context, $path, true);
		$place = $value;
	}
	protected static function &GetKeyPath(&$arr, $keyPath, $create = false) {
		$keyPathParts = !is_array($keyPath) ? explode('.', $keyPath) : $keyPath;
		$subjectKey = array_shift($keyPathParts);
		$array_append = false;
		if (preg_match('/^([^\[]+)\[([^\]]*)\]$/', $subjectKey, $match)) {
			if (isset($match[2]) && strlen(trim($match[2]))>0) {
				array_unshift($keyPathParts, $match[2]);
			} else if ($create) {
				// .. this is for setting/appending
				$array_append = true;
			}
			$subjectKey = $match[1];
		}
		if (!isset($arr[$subjectKey])) {
			if (!$create) {
				$rv = false;
				return $rv;
			}
			$arr[$subjectKey] = null;
			if ($array_append) {
				$arr[$subjectKey] = array();
				array_unshift($keyPathParts, '0');	// first element of array
			}
			if (count($keyPathParts)==0) {
				return $arr[$subjectKey];
			}
			return self::GetKeyPath($arr[$subjectKey], $keyPathParts, true);
		}
		if ($array_append) {
			$count = count($arr[$subjectKey]);
			$arr[$subjectKey][$count] = null;
			$item = &$arr[$subjectKey][$count];
		} else {
			$item = &$arr[$subjectKey];
		}
		if (count($keyPathParts)==0) 
			return $item;
		return self::GetKeyPath($item, $keyPathParts, $create);
	}
}
