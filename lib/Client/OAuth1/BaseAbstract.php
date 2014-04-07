<?php

/**
 * Heavily modified by theakman2 (A Kazim).
 * Original header follows.
 */

/**
 * A Twitter library in PHP.
 *
 * @package codebird
 * @version 2.3.3
 * @author J.M. <me@mynetx.net>
 * @copyright 2010-2013 J.M. <me@mynetx.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
abstract class POA_Client_OAuth1_BaseAbstract extends POA_Client_BaseAbstract {
	
	const EVENT_BEFORE_GET_AUTHENTICATION_URL					= "EVENT_OAUTH1_BEFORE_GET_AUTHENTICATION_URL";
	const EVENT_SUCCESSFUL_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE	= "EVENT_OAUTH1_SUCCESSFUL_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE";
	const EVENT_FAILED_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE		= "EVENT_OAUTH1_FAILED_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE";
	const EVENT_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE		= "EVENT_OAUTH1_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE";
	const EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE			= "EVENT_OAUTH1_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE";
	const EVENT_BEFORE_FETCH									= "EVENT_OAUTH1_BEFORE_FETCH";
	const EVENT_AFTER_FETCH										= "EVENT_OAUTH1_AFTER_FETCH";
	const EVENT_VALID_ACCESS_TOKEN								= "EVENT_OAUTH1_VALID_ACCESS_TOKEN";
	const EVENT_INVALID_ACCESS_TOKEN							= "EVENT_OAUTH1_INVALID_ACCESS_TOKEN";
	
	const REQUEST_TOKEN_ENDPOINT = null;

	/**
	 * The Request or access token. Used to sign requests
	 */
	protected $_oauthToken = null;

	/**
	 * The corresponding request or access token secret
	 */
	protected $_oauthTokenSecret = null;

	protected $_oauthVerifier = null;
	
	/**
	 * TRUE if the client is currently fetching an unauthorized request token, FALSE
	 * otherwise.
	 * @var bool
	 */
	protected $_isRequestingUnauthorizedRequestToken = false;
	
	protected function __construct() {
		parent::__construct();
		$className = get_class($this);
		if (!static::REQUEST_TOKEN_ENDPOINT) {
			throw new Exception("$className - Request token endpoint must be set.");
		}
	}
	
	public function isRequestingUnauthorizedRequestToken() {
		return $this->_isRequestingUnauthorizedRequestToken;
	}
	
	public function getOauthToken() {
		return $this->_oauthToken;
	}
	
	public function setOauthToken($token) {
		$this->_oauthToken = $token;
	}
	
	public function getOauthTokenSecret() {
		return $this->_oauthTokenSecret;
	}
	
	public function setOauthTokenSecret($secret) {
		$this->_oauthTokenSecret = $secret;
	}
	
	public function getOauthVerifier() {
		return $this->_oauthVerifier;
	}
	
	public function setOauthVerifier($verifier) {
		$this->_oauthVerifier = $verifier;
	}
	
	public function getAuthenticationUrl() 	{
		$this->dispatch(self::EVENT_BEFORE_GET_AUTHENTICATION_URL);
		if ($this->_oauthToken) {
			$defaultParameters = array(
				'oauth_token'	=> $this->_oauthToken,
			); 
			$parameters = $defaultParameters + $this->_extraAuthParams;
			return static::AUTHORIZATION_ENDPOINT."?".POA_Utility::httpBuildQuery($parameters);
		}
   		return "";
	}
	
	public function requestUnauthorizedRequestToken() {
		$oauthToken = $this->_oauthToken;
		$oauthVerifier = $this->_oauthVerifier;
		$oauthSecret = $this->_oauthTokenSecret;
		$this->_oauthToken = null;
		$this->_oauthTokenSecret = null;
		$this->_oauthVerifier = null;
		
		$this->_isRequestingUnauthorizedRequestToken = true;
		$response = $this->fetch(
			static::REQUEST_TOKEN_ENDPOINT,
			array('oauth_callback' => static::REDIRECT_URL),
			POA_Utility::HTTP_METHOD_POST
		);
		$this->_isRequestingUnauthorizedRequestToken = false;
		
		$this->_oauthToken = $oauthToken;
		$this->_oauthTokenSecret = $oauthSecret;
		$this->_oauthVerifier = $oauthVerifier;

		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
			&& isset($result['oauth_token'])
			&& isset($result['oauth_token_secret'])
		) {
			$this->dispatch(
				self::EVENT_SUCCESSFUL_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE,
				$response,
				$result['oauth_token'],
				$result['oauth_token_secret']
			);
			return $result['oauth_token'];
		} else {
			$this->dispatch(
				self::EVENT_FAILED_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE,
				$response
			);
		}
		return null;
	}
	
	public function fetchAccessToken() {
		$response = $this->fetch(
			static::ACCESS_TOKEN_ENDPOINT,
			array(),
			static::ACCESS_TOKEN_REQUEST_METHOD
		);
		
		if (
			($response->isSuccess())
			&& ($result = $response->getResult())
			&& isset($result['oauth_token'])
			&& isset($result['oauth_token_secret'])
		) {
			$this->dispatch(
				self::EVENT_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE,
				$response,
				$result['oauth_token'],
				$result['oauth_token_secret']
			);
			return $result['oauth_token'];
		} else {
			$this->dispatch(
				self::EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE,
				$response
			);
		}
		
		return null;
	}
	
	public function destroyState() {
		$this->_oauthToken = null;
		$this->_oauthTokenSecret = null;
		$this->_oauthVerifier = null;
	}
	
	/**
	 * Signing helpers
	 */

	/**
	 * Gets the base64-encoded SHA1 hash for the given data
	 *
	 * @param string $data The data to calculate the hash from
	 *
	 * @return string The hash
	 */
	protected function _sha1($data) {
		if (static::CLIENT_SECRET == null) {
			throw new Exception('To generate a hash, the consumer secret must be set.');
		}
		if (!function_exists('hash_hmac')) {
			throw new Exception('To generate a hash, the PHP hash extension must be available.');
		}
		return base64_encode(hash_hmac('sha1', $data, static::CLIENT_SECRET . '&'
			. ($this->_oauthTokenSecret != null ? $this->_oauthTokenSecret : ''), true));
	}

	/**
	 * Generates a (hopefully) unique random string
	 *
	 * @param int optional $length The length of the string to generate
	 *
	 * @return string The random string
	 */
	protected function _nonce($length = 8) {
		if ($length < 1) {
			throw new Exception('Invalid nonce length.');
		}
		return substr(md5(microtime(true)), 0, $length);
	}

	/**
	 * Generates an OAuth signature
	 *
	 * @param string		  $httpMethod Usually either 'GET' or 'POST' or 'DELETE'
	 * @param string		  $method	 The API method to call
	 * @param array  optional $params	 The API call parameters, associative
	 *
	 * @return string Authorization HTTP header
	 */
	protected function _sign($httpMethod, $url, $params = array()) {
		if (static::CLIENT_ID == null) {
			throw new Exception('To generate a signature, the consumer key must be set.');
		}
		$signParams = array(
			'consumer_key' => static::CLIENT_ID,
			'version' => '1.0',
			'timestamp' => time(),
			'nonce' => $this->_nonce(),
			'signature_method' => 'HMAC-SHA1',
		);
		$signBaseParams = array();
		foreach ($signParams as $key => $value) {
			$signBaseParams['oauth_' . $key] = rawurlencode($value);
		}
		if ($this->_oauthToken) {
			$signBaseParams['oauth_token'] = rawurlencode($this->_oauthToken);
		}
		if ($this->_oauthVerifier) {
		  $signBaseParams['oauth_verifier'] = rawurlencode($this->_oauthVerifier);
		}
		$signBaseParams += $params;
		$oauthParams = $signBaseParams;
		foreach ($params as $key => $value) {
			$signBaseParams[$key] = rawurlencode($value);
		}
		ksort($signBaseParams);
		$signBaseString = '';
		foreach ($signBaseParams as $key => $value) {
			$signBaseString .= $key . '=' . $value . '&';
		}
		$signBaseString = substr($signBaseString, 0, -1);
		$signature = $this->_sha1($httpMethod . '&' . rawurlencode($url) . '&' . rawurlencode($signBaseString));

		$params = array_merge($oauthParams, array(
			'oauth_signature' => $signature
		));
		ksort($params);
		$authorization = 'OAuth ';
		foreach ($params as $key => $value) {
			$authorization .= $key . '="' . rawurlencode($value) . '", ';
		}
		return array("Authorization"=>substr($authorization, 0, -2));
	}

	public function fetch($protectedResourceUrl, $parameters = array(), $httpMethod = POA_Utility::HTTP_METHOD_GET, array $httpHeaders = array(), $formContentType = POA_Utility::HTTP_FORM_CONTENT_TYPE_MULTIPART) {
		$response = new POA_Response();
		
		$this->dispatch(
			self::EVENT_BEFORE_FETCH,
			$response,
			$protectedResourceUrl,
			$parameters,
			$httpMethod,
			$httpHeaders,
			$formContentType
		);
		
		if ($response->getCode()) {
			return $response;
		}
		
		if ((static::DEFAULT_API_BASE !== null) && (strpos($protectedResourceUrl,"/") === 0)) {
			$protectedResourceUrl = static::DEFAULT_API_BASE.$protectedResourceUrl;
		}
		
		$httpHeaders = $this->_sign($httpMethod,$protectedResourceUrl,$parameters) + $httpHeaders;
		
		POA_Utility::executeRequest(
			$protectedResourceUrl,
			$parameters,
			$httpMethod,
			$httpHeaders,
			$formContentType,
			$response
		);
		
		$this->dispatch(
			self::EVENT_AFTER_FETCH,
			$response,
			$protectedResourceUrl,
			$parameters,
			$httpMethod,
			$httpHeaders,
			$formContentType
		);
		
		return $response;
	}
	
	public function hasValidAccessToken() {
		$response = $this->validAccessTokenTest();
		if ($response->isSuccess()) {
			$this->dispatch(self::EVENT_VALID_ACCESS_TOKEN,$response);
			return true;
		}
		$this->dispatch(self::EVENT_INVALID_ACCESS_TOKEN,$response);
		return false;
	}

}