<?php

abstract class POA_Client_OAuth2_FoursquareAbstract extends POA_Client_OAuth2_BaseAbstract implements POA_Client_ApplicationInterface {
	
	const APPLICATION_NAME = "Foursquare";
	
	const AUTHORIZATION_ENDPOINT = "https://foursquare.com/oauth2/authenticate";
	const ACCESS_TOKEN_ENDPOINT = "https://foursquare.com/oauth2/access_token";
	const ACCESS_TOKEN_REQUEST_METHOD = POA_Utility::HTTP_METHOD_GET;
	const DEFAULT_API_BASE = "https://api.foursquare.com/v2";
	const ACCESS_TOKEN_PARAM_NAME = "oauth_token";
	
	public function getUserId() {
		$response = $this->fetch("/users/self");
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
			&& isset($result['response'])
			&& isset($result['response']['user'])
			&& isset($result['response']['user']['id'])
		) {
			return $result['response']['user']['id'];
		}
		return null;
	}
	
	public function getName() {
		$response = $this->fetch('/users/self');
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
		) {
			if (
				isset($result['response'])
				&& isset($result['response']['user'])
			) {
				$data = $result['response']['user'];
				if (isset($data['firstName'])) {
					$name = $data['firstName'];
					if (isset($data['lastName'])) {
						$name .= " ".$data['lastName'];
					}
					return $name;
				}
				unset($data);
			}
		}
		return null;
	}
	
	public function validAccessTokenTest() {
		return $this->fetch('/users/self');
	}
	
}
