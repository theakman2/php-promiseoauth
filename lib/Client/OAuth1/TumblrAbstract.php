<?php

abstract class POA_Client_OAuth1_TumblrAbstract extends POA_Client_OAuth1_BaseAbstract implements POA_Client_ApplicationInterface {
	
	const APPLICATION_NAME = "Tumblr";
	
	const AUTHORIZATION_ENDPOINT = "http://www.tumblr.com/oauth/authorize";
	const ACCESS_TOKEN_ENDPOINT = "http://www.tumblr.com/oauth/access_token";
	const DEFAULT_API_BASE = "http://api.tumblr.com/v2";
	const REQUEST_TOKEN_ENDPOINT = "http://www.tumblr.com/oauth/request_token";
	
	/**
	 * WARNING: the Tumblr API doesn't return user IDs.
	 * 
	 * This function simply returns the user's name, but users can
	 * easily change their name. Do not rely on the return for
	 * this function to remain constant for an individual user!
	 */
	public function getUserId() {
		return $this->getName();
	}
	
	public function getName() {
		$response = $this->fetch('/user/info');
		if (
			$response->isSuccess()
			&& ($result = $response->getResult())
			&& isset($result['response'])
			&& isset($result['response']['user'])
			&& isset($result['response']['user']['name'])
		) {
			return $result['response']['user']['name'];
		}
		return null;
	}
	
	public function validAccessTokenTest() {
		return $this->fetch('/user/info');
	}
	
}
