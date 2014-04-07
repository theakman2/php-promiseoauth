<?php

abstract class POA_Client_OAuth1_TwitterAbstract extends POA_Client_OAuth1_BaseAbstract implements POA_Client_ApplicationInterface {
	
	const APPLICATION_NAME = "Twitter";
	
	const AUTHORIZATION_ENDPOINT = "https://api.twitter.com/oauth/authorize";
	const ACCESS_TOKEN_ENDPOINT = "https://api.twitter.com/oauth/access_token";
	const DEFAULT_API_BASE = "https://api.twitter.com/1.1";
	const REQUEST_TOKEN_ENDPOINT = "https://api.twitter.com/oauth/request_token";
	
	public function getUserId() {
		$response = $this->fetch('/account/verify_credentials.json');
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
			&& isset($result['id_str'])
		) {
			return $result['id_str'];
		}
		return null;
	}
	
	public function getName() {
		$response = $this->fetch('/account/verify_credentials.json');
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
		) {
			if (isset($result['name'])) {
				return $result['name'];
			} elseif (isset($result['screen_name'])) {
				return $result['screen_name'];
			}
		}
		return "";
	}
	
	public function validAccessTokenTest() {
		return $this->fetch('/account/verify_credentials.json');
	}
	
}
