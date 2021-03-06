<?php

abstract class POA_Client_OAuth2_FacebookAbstract extends POA_Client_OAuth2_BaseAbstract implements POA_Client_ApplicationInterface {
	
	const APPLICATION_NAME = "Facebook";
	
	const AUTHORIZATION_ENDPOINT = "https://graph.facebook.com/oauth/authorize";
	const ACCESS_TOKEN_ENDPOINT = "https://graph.facebook.com/oauth/access_token";
	const DEFAULT_API_BASE = "https://graph.facebook.com";
	
	public function getUserId() {
		$response = $this->fetch("/me");
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
		$response = $this->fetch('/me');
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
		) {
			if (
				isset($result['first_name']) &&
				$result['first_name'] &&
				isset($result['last_name']) &&
				$result['last_name']
			) {
				return $result['first_name']." ".$result['last_name'];
			} elseif (
				isset($result['name']) &&
				$result['name']
			) {
				return $result['name'];
			}
		}
		return null;
	}
	
	public function validAccessTokenTest() {
		return $this->fetch("/me");
	}
	
}
