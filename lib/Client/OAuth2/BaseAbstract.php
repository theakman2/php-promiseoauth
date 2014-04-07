<?php
/**
 * Note : Code is released under the GNU LGPL
 *
 * Please do not change the header of this file
 *
 * This library is free software; you can redistribute it and/or modify it under the terms of the GNU
 * Lesser General Public License as published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU Lesser General Public License for more details.
 */

/**
 * Heavily modified by theakman2 (A Kazim).
 * Original header follows.
 */

/**
 * Light PHP wrapper for the OAuth 2.0 protocol.
 *
 * This client is based on the OAuth2 specification draft v2.15
 * http://tools.ietf.org/html/draft-ietf-oauth-v2-15
 *
 * @author      Pierrick Charron <pierrick@webstart.fr>
 * @author      Anis Berejeb <anis.berejeb@gmail.com>
 * @version     1.2-dev
 */

abstract class POA_Client_OAuth2_BaseAbstract extends POA_Client_BaseAbstract {
	
	const EVENT_BEFORE_GET_AUTHENTICATION_URL					= "EVENT_OAUTH2_BEFORE_GET_AUTHENTICATION_URL";
	const EVENT_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE		= "EVENT_OAUTH2_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE";
	const EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE			= "EVENT_OAUTH2_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE";
	const EVENT_BEFORE_FETCH									= "EVENT_OAUTH2_BEFORE_FETCH";
	const EVENT_AFTER_FETCH										= "EVENT_OAUTH2_AFTER_FETCH";	
	const EVENT_VALID_ACCESS_TOKEN								= "EVENT_OAUTH2_VALID_ACCESS_TOKEN";
	const EVENT_INVALID_ACCESS_TOKEN							= "EVENT_OAUTH2_INVALID_ACCESS_TOKEN";
	
	/**
	 * Different AUTH method
	 */
	const AUTH_TYPE_URI					= 0;
	const AUTH_TYPE_AUTHORIZATION_BASIC	= 1;
	const AUTH_TYPE_FORM				= 2;

	/**
	 * Different Access token type
	 **/
	const ACCESS_TOKEN_URI		= 0;
	const ACCESS_TOKEN_BEARER	= 1;
	const ACCESS_TOKEN_OAUTH	= 2;
	const ACCESS_TOKEN_MAC		= 3;

	/**
	 * Different Grant types
	 **/
	const GRANT_TYPE_AUTH_CODE			= 'authorization_code';
	const GRANT_TYPE_PASSWORD			= 'password';
	const GRANT_TYPE_CLIENT_CREDENTIALS	= 'client_credentials';
	const GRANT_TYPE_REFRESH_TOKEN		= 'refresh_token';

	/**
	 * Client Authentication method
	 *
	 * @var int
	 */
	const CLIENT_AUTH_METHOD = self::AUTH_TYPE_URI;

	/**
	 * Access Token Type
	 *
	 * @var int
	 */
	const ACCESS_TOKEN_TYPE = self::ACCESS_TOKEN_URI;
	
	/**
	 * Access Token crypt algorithm
	 *
	 * @var string
	 */
	const ACCESS_TOKEN_ALGORITHM = null;
	
	const DEFAULT_GRANT_TYPE = self::GRANT_TYPE_AUTH_CODE;
	
	/**
	 * Access Token Parameter name
	 *
	 * @var string
	 */
	const ACCESS_TOKEN_PARAM_NAME = "access_token";
	
	/**
	 * Access Token
	 *
	 * @var string
	 */
	protected $_accessToken = null;

	/**
	 * Access Token Secret
	 *
	 * @var string
	 */
	protected $_accessTokenSecret = null;
	
	protected $_code = null;
	
	public function addExtraAuthenticationParameters(array $params) {
		$this->_extraAuthParams = $params + $this->_extraAuthParams;
	}
	
	public function setCode($code) {
		$this->_code = $code;
	}
	
	/**
	 * getAuthenticationUrl
	 *
	 * @return string URL used for authentication
	 */
	public function getAuthenticationUrl() {
		$this->dispatch(self::EVENT_BEFORE_GET_AUTHENTICATION_URL);
		
		$defaultParameters = array(
			'response_type' => 'code',
			'client_id'	 => static::CLIENT_ID,
			'redirect_uri'  => static::REDIRECT_URL,
		);
		
		$parameters = $defaultParameters + $this->_extraAuthParams;
		
		return static::AUTHORIZATION_ENDPOINT . '?' . POA_Utility::httpBuildQuery($parameters);
	}

	/**
	 * @param int $grantType Grant Type.
	 * @return string|null Access token if successful or null otherwise.
	 */
	public function fetchAccessToken($grantType = null) {
		if ($grantType === null) {
			$grantType = static::DEFAULT_GRANT_TYPE;
		}
		
		$parameters = array();
		switch($grantType) {
			case self::GRANT_TYPE_AUTH_CODE:
				$parameters = array(
					'code' => $this->_code,
					'redirect_uri' => static::REDIRECT_URL,
				);
			case self::GRANT_TYPE_PASSWORD:
				// IMPLEMENT
				break;
			case self::GRANT_TYPE_CLIENT_CREDENTIALS:
				// IMPLEMENT				
				break;
			case self::GRANT_TYPE_REFRESH_TOKEN:
				// IMPLEMENT				
				break;
			default:
				break;
		}
		
		$parameters['grant_type'] = $grantType;
		$httpHeaders = array();
		switch (static::CLIENT_AUTH_METHOD) {
			case self::AUTH_TYPE_URI:
			case self::AUTH_TYPE_FORM:
				$parameters['client_id'] = static::CLIENT_ID;
				$parameters['client_secret'] = static::CLIENT_SECRET;
				break;
			case self::AUTH_TYPE_AUTHORIZATION_BASIC:
				$parameters['client_id'] = static::CLIENT_ID;
				$httpHeaders['Authorization'] = 'Basic ' . base64_encode(static::CLIENT_ID .  ':' . static::CLIENT_SECRET);
				break;
			default:
				throw new Exception('Unknown client auth type.');
				break;
		}

		$response = POA_Utility::executeRequest(
			static::ACCESS_TOKEN_ENDPOINT,
			$parameters,
			static::ACCESS_TOKEN_REQUEST_METHOD,
			$httpHeaders,
			POA_Utility::HTTP_FORM_CONTENT_TYPE_APPLICATION
		);
		
		if (
			($response->isSuccess())
			&& ($result = $response->getResult())
			&& isset($result['access_token'])
		) {
			$this->dispatch(
				self::EVENT_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE,
				$response,
				$result['access_token']
			);
			return $result['access_token'];
		}
		
		$this->dispatch(
			self::EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE,
			$response
		);
		
		return null;
	}

	/**
	 * @return string
	 */
	public function getAccessToken() {
		return $this->_accessToken;
	}
	
	/**
	 * setToken
	 *
	 * @param string $token Set the access token
	 * @return void
	 */
	public function setAccessToken($token) {
		$this->_accessToken = $token;
	}

	/**
	 * Fetch a protected ressource
	 *
	 * @param string $protectedResourceUrl Protected resource URL
	 * @param array  $parameters Array of parameters
	 * @param string $httpMethod HTTP Method to use (POST, PUT, GET, HEAD, DELETE)
	 * @param array  $httpHeaders HTTP headers
	 * @param int	$formContentType HTTP form content type to use
	 * @return array
	 */
	public function fetch(
		$protectedResourceUrl,
		$parameters = array(),
		$httpMethod = POA_Utility::HTTP_METHOD_GET,
		array $httpHeaders = array(),
		$formContentType = POA_Utility::HTTP_FORM_CONTENT_TYPE_MULTIPART
	) {
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
		
		if ($this->_accessToken) {
			switch (static::ACCESS_TOKEN_TYPE) {
				case self::ACCESS_TOKEN_URI:
					if (is_array($parameters)) {
						$parameters[static::ACCESS_TOKEN_PARAM_NAME] = $this->_accessToken;
					} else {
						throw new Exception(
							'You need to give parameters as array if you want to give the token within the URI.'
						);
					}
					break;
				case self::ACCESS_TOKEN_BEARER:
					$httpHeaders['Authorization'] = 'Bearer ' . $this->_accessToken;
					break;
				case self::ACCESS_TOKEN_OAUTH:
					$httpHeaders['Authorization'] = 'OAuth ' . $this->_accessToken;
					break;
				case self::ACCESS_TOKEN_MAC:
					$httpHeaders['Authorization'] = 'MAC ' . $this->generateMACSignature($protectedResourceUrl, $parameters, $httpMethod);
					break;
				default:
					throw new Exception('Unknown access token type.');
					break;
			}
		}
		
		if ((static::DEFAULT_API_BASE !== null) && (strpos($protectedResourceUrl,"/") === 0)) {
			$protectedResourceUrl = static::DEFAULT_API_BASE.$protectedResourceUrl;
		}
		
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
	
	public function destroyState() {
		$this->_accessToken =  null;
	}
	
	/**
	 * Generate the MAC signature
	 *
	 * @param string $url Called URL
	 * @param array  $parameters Parameters
	 * @param string $httpMethod Http Method
	 * @return string
	 */
	protected function generateMACSignature($url, $parameters, $httpMethod) {
		$timestamp = time();
		$nonce = uniqid();
		$parsedUrl = parse_url($url);
		if (!isset($parsedUrl['port'])) {
			$parsedUrl['port'] = ($parsedUrl['scheme'] == 'https') ? 443 : 80;
		}
		if ($httpMethod == POA_Utility::HTTP_METHOD_GET) {
			if (is_array($parameters)) {
				$parsedUrl['path'] .= '?' . POA_Utility::httpBuildQuery($parameters);
			} elseif ($parameters) {
				$parsedUrl['path'] .= '?' . $parameters;
			}
		}

		$signature = base64_encode(hash_hmac(static::ACCESS_TOKEN_ALGORITHM,
					$timestamp . "\n"
					. $nonce . "\n"
					. $httpMethod . "\n"
					. $parsedUrl['path'] . "\n"
					. $parsedUrl['host'] . "\n"
					. $parsedUrl['port'] . "\n\n"
					, $this->_accessTokenSecret, true));

		return 'id="' . $this->_accessToken . '", ts="' . $timestamp . '", nonce="' . $nonce . '", mac="' . $signature . '"';
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
