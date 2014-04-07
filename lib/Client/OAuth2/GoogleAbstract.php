<?php

abstract class POA_Client_OAuth2_GoogleAbstract extends POA_Client_OAuth2_BaseAbstract implements POA_Client_ApplicationInterface {
	
	const APPLICATION_NAME = "Google";
	
	const AUTHORIZATION_ENDPOINT = "https://accounts.google.com/o/oauth2/auth";
	const ACCESS_TOKEN_ENDPOINT = "https://accounts.google.com/o/oauth2/token";
	const DEFAULT_API_BASE = "https://www.googleapis.com/oauth2/v2";
	
	protected $_dropQueryParams = array(
		'code',
		'state',
		'authuser',
		'session_state',
		'prompt',
	);
	
	protected function __construct() {
		if (!isset($this->_extraAuthParams['scope'])) {
			throw new Exception('
				$this->_extraAuthParams[\'scope\'] not set.
			');
		}
		parent::__construct();
	}
	
	public function getUserId() {
		$response = $this->fetch("/userinfo");
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
			&& isset($result['id'])
		) {
			return $result['id'];
		}
		return null;
	}
	
	public function getName() {
		$response = $this->fetch('/userinfo');
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
		) {
			if (isset($result['name']) && $result['name']) {
				return $result['name'];
			} elseif (isset($result['given_name']) && $result['given_name']) {
				return $result['given_name'];
			}
		}
		return null;
	}
	
	public function validAccessTokenTest() {
		return $this->fetch('/userinfo');
	}
	
}
