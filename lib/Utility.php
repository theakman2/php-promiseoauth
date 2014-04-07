<?php

/**
 * A container for miscellaneous useful functions.
 */
class POA_Utility {
	/**
	 * HTTP Methods
	 */
	const HTTP_METHOD_GET		= 'GET';
	const HTTP_METHOD_POST		= 'POST';
	const HTTP_METHOD_PUT		= 'PUT';
	const HTTP_METHOD_DELETE	= 'DELETE';
	const HTTP_METHOD_HEAD		= 'HEAD';
	
	/**
	 * HTTP Form content types
	 */
	const HTTP_FORM_CONTENT_TYPE_APPLICATION	= 0;
	const HTTP_FORM_CONTENT_TYPE_MULTIPART		= 1;
	
	/**
	 * Execute a request (with curl)
	 *
	 * @param string $url URL
	 * @param mixed  $parameters Array of parameters
	 * @param string $httpMethod HTTP Method
	 * @param array  $httpHeaders HTTP Headers
	 * @param int	$formContentType HTTP form content type to use
	 * @return array
	 */
	public static function executeRequest(
		$url,
		$parameters = array(),
		$httpMethod = self::HTTP_METHOD_GET,
		array $httpHeaders = null,
		$formContentType = self::HTTP_FORM_CONTENT_TYPE_MULTIPART,
		POA_Response $ret = null
	) {
		$curlOpts = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CUSTOMREQUEST  => $httpMethod
		);

		switch($httpMethod) {
			case self::HTTP_METHOD_POST:
				$curlOpts[CURLOPT_POST] = true;
				/* No break */
			case self::HTTP_METHOD_PUT:

				/**
				 * Passing an array to CURLOPT_POSTFIELDS will encode the data as multipart/form-data,
				 * while passing a URL-encoded string will encode the data as application/x-www-form-urlencoded.
				 * http://php.net/manual/en/function.curl-setopt.php
				 */
				if(is_array($parameters) && self::HTTP_FORM_CONTENT_TYPE_APPLICATION === $formContentType) {
					$parameters = POA_Utility::httpBuildQuery($parameters);
				}
				$curlOpts[CURLOPT_POSTFIELDS] = $parameters;
				break;
			case self::HTTP_METHOD_HEAD:
				$curlOpts[CURLOPT_NOBODY] = true;
				/* No break */
			case self::HTTP_METHOD_DELETE:
			case self::HTTP_METHOD_GET:
				if (is_array($parameters)) {
					$url .= '?' . POA_Utility::httpBuildQuery($parameters);
				} elseif ($parameters) {
					$url .= '?' . $parameters;
				}
				break;
			default:
				break;
		}

		$curlOpts[CURLOPT_URL] = $url;

		if (is_array($httpHeaders)) {
			$header = array();
			foreach($httpHeaders as $key => $parsedUrlValue) {
				$header[] = "$key: $parsedUrlValue";
			}
			$curlOpts[CURLOPT_HTTPHEADER] = $header;
		}

		$ch = curl_init();
		curl_setopt_array($ch, $curlOpts);
		// https handling
		// bypass ssl verification
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		
		$result = curl_exec($ch);
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		if ($curlError = curl_error($ch)) {
			throw new Exception($curlError);
		}
		curl_close($ch);
		
		if (!$ret) {
			$ret = new POA_Response();
		}
		
		$ret->setTime(new DateTime());
		$ret->setCode($httpCode);
		$ret->setContentType($contentType);
		
		if ($result) {
			$jsonDecoded = json_decode($result,true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$ret->setResult($jsonDecoded);
			} else {
				parse_str($result,$data);
				$ret->setResult($data);
			}
		} else {
			$ret->setResult($result);
		}
	
		return $ret;
	}
	
	/**
	 * Like PHP's native http_build_query(), but encodes according to RFC 3986 instead of
	 * RFC 1738.
	 * 
	 * @param array $data
	 * @return string
	 */
	public static function httpBuildQuery($data) {
		$parts = array();
		
		foreach($data as $key => $value) {
			$parts[] = rawurlencode($key)."=".rawurlencode($value);
		}
		
		return implode("&",$parts);
	}
	
	/**
	 * Given a $url, strip all query string parameters whose keys are in $dropQueryParams
	 * @param string $url
	 * @param array $dropQueryParams An array of url-encoded keys to remove.
	 * @return string
	 */
	public static function dropQueryParams($url,$dropQueryParams = array()) {
		if (!(
			$dropQueryParams
			&& (strpos($url,"?") !== false)
			&& ($parts = preg_split('%[#?]%',$url))
			&& (isset($parts[1]))
			&& $parts[1]
		)) {
			return $url;
		}
		
		$newQueryParts = array();
		
		$dropQueryParams = array_flip($dropQueryParams);
		
		$queryParts = explode("&",$parts[1]);
		foreach($queryParts as $queryPart) {
			$subParts = explode("=",$queryPart,2);
			if (!isset($dropQueryParams[$subParts[0]])) {
				$newQueryParts[] = $queryPart;
			}
		}
		
		return $parts[0]
			.($newQueryParts ? "?".implode("&",$newQueryParts) : "")
			.(isset($parts[2]) ? "#".$parts[2] : "");
	}
	
	/**
	 * Returns the Current URL, stripping it of known parameters that should
	 * not persist.
	 *
	 * @param array optional $dropQueryParams Query string parameters that should be stripped
	 *
	 * @return string The current URL
	 */
	public static function getCurrentUrl(array $dropQueryParams = array()) {
		$protocol = self::getHttpProtocol() . '://';
		$host = $_SERVER['HTTP_HOST'];
		$currentUrl = $protocol.$host.$_SERVER['REQUEST_URI'];
		$parts = parse_url($currentUrl);
	
		// use port if non default
		$port =
		isset($parts['port']) &&
		(($protocol === 'http://' && $parts['port'] !== 80) ||
				($protocol === 'https://' && $parts['port'] !== 443))
				? ':' . $parts['port'] : '';
	
		$query = isset($parts['query']) ? "?".$parts['query'] : "";

		// rebuild
		$url = $protocol . $parts['host'] . $port . $parts['path'] . $query;

		if ($dropQueryParams) {
			$url = self::dropQueryParams($url,$dropQueryParams);
		}

		return $url;
	}
	
	public static function getHttpProtocol() {
		/*apache + variants specific way of checking for https*/
		if (
			isset($_SERVER['HTTPS'])
			&& (
				($_SERVER['HTTPS'] === 'on')
				|| ($_SERVER['HTTPS'] == 1)
			)
		) {
			return 'https';
		}
		
		/*nginx way of checking for https*/
		if (
			isset($_SERVER['SERVER_PORT'])
			&& ($_SERVER['SERVER_PORT'] === '443')
		) {
			return 'https';
		}
		
		return 'http';
	}
}
