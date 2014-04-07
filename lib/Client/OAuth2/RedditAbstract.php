<?php

abstract class POA_Client_OAuth2_RedditAbstract extends POA_Client_OAuth2_BaseAbstract implements POA_Client_ApplicationInterface {
	
	const APPLICATION_NAME = "Reddit";
	
	const AUTHORIZATION_ENDPOINT = "https://ssl.reddit.com/api/v1/authorize";
	const ACCESS_TOKEN_ENDPOINT = "https://ssl.reddit.com/api/v1/access_token";
	const DEFAULT_API_BASE = "https://oauth.reddit.com";
	const CLIENT_AUTH_METHOD = self::AUTH_TYPE_AUTHORIZATION_BASIC;
	const ACCESS_TOKEN_TYPE = self::ACCESS_TOKEN_BEARER;
	
	public function getUserId() {
		$response = $this->fetch("/api/v1/me.json");
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
		$response = $this->fetch('/api/v1/me.json');
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
			&& isset($result['name'])
		) {
			return $result['name'];
		}
		return null;
	}
	
	public function validAccessTokenTest() {
		return $this->fetch('/api/v1/me.json');
	}
	
}
