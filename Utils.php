<?php

namespace FMHTTP;

class Utils {
	
	static function ParseQueryString($query, $orig = false) {
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
	static function BuildQueryString($items) {
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
	static function AppendQueryString($url, $queryValues) {
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

}
