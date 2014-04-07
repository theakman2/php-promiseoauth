<?php

/**
 * Generic Observer. Handles persistent storage of oauth tokens, generating
 * CSRF tokens, etc.
 */
class POA_Observer_Generic implements POA_Observer_ObserverInterface {
	
	/**
	 * The POA_Client_BaseAbstract instance that this Observer listens to.
	 */
	protected $_client = null;
	
	/**
	 * Should verbose information be logged via PHP's error_log() function?
	 * @var bool
	 */
	protected $_enableLogging = false;

	
	/**
	 * @var string Holds the URL that the user was on before they were
	 * redirected to the external service to authorise the application.
	 * This POAObserver handles automatically redirecting the user to this
	 * URL if they authorise this application.
	 */
	protected $_backUrl = null;
	
	/**
	 * @var bool Is this request a genuine user authorization response?
	 *
	 * This is only true if relevant GET parameters, such as 'oauth_verifier',
	 * 'code' and 'state', are set and match the previously stored CSRF token.
	 */
	protected $_isGenuineAuthResponse = false;
	
	/**
	 * @var string The CSRF token state this POAObserver instance is currently
	 * working with.
	 */
	protected $_csrfTokenState = null;
	
	/**
	 * @var POA_PersistentStorage_BaseAbstract The POA_PersistentStorage_BaseAbstract that handles
	 * persistently storing the oauth tokens, back URLs and CSRF tokens this POAObserver
	 * instance is working with.
	 */
	protected $_persistentStorage = null;
	
	protected function log($msg) {
		if ($this->_enableLogging) {
			error_log($msg);
		}
	}
	
	/**
	 * Redirect the browser to a specified URL with the specified HTTP status code.
	 * @param string $url The URL to redirect to.
	 * @param integer $code The HTTP status code to redirect with.
	 */
	protected function redirectTo($url,$code=301) {
		if ($url) {
			header("Location: $url", true, $code);
		}
		die();
	}
	
	protected function oauth1GenuineAuthGranted() {
		$this->_isGenuineAuthResponse = true;
			
		$backUrl = $this->getBackUrl();
		$this->clearBackUrl();
			
		$this->_client->setOauthToken($_GET['oauth_token']);
		$this->_client->setOauthVerifier($_GET['oauth_verifier']);
		$this->_persistentStorage->set('oauth_token',$_GET['oauth_token']);
		$this->_persistentStorage->set('oauth_verifier',$_GET['oauth_verifier']);
		$this->_client->fetchAccessToken();
		
		$this->redirectTo($backUrl);
	}
	
	protected function oauth1Init() {
		if (isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])) {
			if ($_GET['oauth_token'] == $this->_persistentStorage->get("oauth_token")) {
				$this->oauth1GenuineAuthGranted();
			}
		}
	}
	
	protected function oauth2GenuineAuthGranted() {
		$this->_isGenuineAuthResponse = true;
		$this->clearCsrfTokenState();
		
		$backUrl = $this->getBackUrl();
		$this->clearBackUrl();
			
		$this->_client->setCode($_GET['code']);
		$this->_client->fetchAccessToken();
			
		$this->redirectTo($backUrl);
	}
	
	protected function oauth2GenuineAuthDenied() {
		$this->_isGenuineAuthResponse = true;
		$this->clearCsrfTokenState();
			
		$backUrl = $this->getBackUrl();
		$this->clearBackUrl();

		$this->redirectTo($backUrl);
	}
	
	protected function oauth2Init() {
		if (isset($_GET['code']) && isset($_GET['state'])) {
			if ($_GET['state'] && ($_GET['state'] == $this->getCsrfTokenState())) {
				$this->oauth2GenuineAuthGranted();
			}
		} elseif (isset($_GET['error']) && isset($_GET['state'])) {
			if ($_GET['state'] && ($_GET['state'] == $this->getCsrfTokenState())) {
				$this->oauth2GenuineAuthDenied();
			}
		}
	}
	
	public function __construct(POA_Client_BaseAbstract $client) {
		$this->_client = $client;
		
		/**
		 * Populate the persistent storage with an POA_PersistentStorage_BaseAbstract implementation.
		 */
		if ($this->_persistentStorage === null) {
			$this->_persistentStorage = new POA_PersistentStorage_Session(get_class($this->_client));
		}
	}
	
	public function poaInit() {
		if (is_a($this->_client,"POA_Client_OAuth1_BaseAbstract")) {
			$this->oauth1Init();
		} elseif (is_a($this->_client,"POA_Client_OAuth2_BaseAbstract")) {
			$this->oauth2Init();
		}
	}
	
	/**
	 * Called whenever the POA_Client_BaseAbstract stored in $this->_client dispatches an event.
	 */
	public function poaEventNotification($event,$arg1,$arg2,$arg3,$arg4,$arg5,$arg6) {
		switch($event) {
			/**
			 * =====================
			 * OAUTH 1 NOTIFICATIONS
			 * =====================
			 */				
			case POA_Client_OAuth1_BaseAbstract::EVENT_INVALID_ACCESS_TOKEN:
				$this->destroySession();
				$this->log($event);
				
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_BEFORE_FETCH:
				if (
					!$this->_client->getOauthToken()
					&& !$this->_client->getOauthTokenSecret()
					&& !$this->_client->isRequestingUnauthorizedRequestToken()
				) {
					$this->_client->setOauthToken(
						$this->_persistentStorage->get("oauth_token")
					);
					$this->_client->setOauthTokenSecret(
						$this->_persistentStorage->get("oauth_token_secret")
					);
				}
				
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_BEFORE_GET_AUTHENTICATION_URL:				
				if (
					!$this->_isGenuineAuthResponse
					&& !$this->getBackUrl()
				) {
					$this->establishBackUrl();
				}
				
				$this->_client->requestUnauthorizedRequestToken();
				
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_SUCCESSFUL_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE:
				$this->_client->setOauthToken($arg2);
				$this->_client->setOauthTokenSecret($arg3);
				$this->_persistentStorage->set('oauth_token',$arg2);
				$this->_persistentStorage->set('oauth_token_secret',$arg3);
				
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_FAILED_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE:
				$this->destroySession();
				$this->log($event);
				
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE:
				$this->_client->setOauthToken($arg2);
				$this->_client->setOauthTokenSecret($arg3);
				$this->_persistentStorage->set('oauth_token',$arg2);
				$this->_persistentStorage->set('oauth_token_secret',$arg3);
				$this->_persistentStorage->remove("oauth_verifier");
				$this->_client->setOauthVerifier(null);
				$this->_client->hasValidAccessToken();
				
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE:
				$this->destroySession();
				$this->log($event);
				
				break;
				
			
			/**
			 * =====================
			 * OAUTH 2 NOTIFICATIONS
			 * =====================
			 */			
			
			case POA_Client_OAuth2_BaseAbstract::EVENT_INVALID_ACCESS_TOKEN:
				$this->destroySession();
				$this->log($event);
				
				break;
				
				
			case POA_Client_OAuth2_BaseAbstract::EVENT_BEFORE_FETCH:
				if (!$this->_client->getAccessToken()) {
					$this->_client->setAccessToken(
						$this->_persistentStorage->get("access_token")
					);
				}
				
				break;
								
				
			case POA_Client_OAuth2_BaseAbstract::EVENT_BEFORE_GET_AUTHENTICATION_URL:				
				$this->establishCsrfTokenState();
				
				if (
					!$this->_isGenuineAuthResponse
					&& !$this->getBackUrl()
				) {
					$this->establishBackUrl();
				}
				
				$this->_client->addExtraAuthenticationParameters(array(
					'state' => $this->_csrfTokenState,
				));
				
				break;
				
				
			case POA_Client_OAuth2_BaseAbstract::EVENT_SUCCESSFUL_REQUEST_TO_ACCESS_TOKEN_EXCHANGE:
				$this->_persistentStorage->set("access_token",$arg2);
				$this->_client->setAccessToken($arg2);
				$this->_client->hasValidAccessToken();
				
				break;
				
				
			case POA_Client_OAuth2_BaseAbstract::EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE:
				$this->destroySession();
				$this->log($event);
				
				break;
				
				
			default:
				
				break;
		}
	}
	
	protected function clearBackUrl() {
		$this->_backUrl = null;
		$this->_persistentStorage->remove("backUrl");
	}
	
	protected function getBackUrl() {
		if ($this->_backUrl !== null) {
			return $this->_backUrl;
		}
		$backUrl = $this->_persistentStorage->get("backUrl",null);
		if ($backUrl !== null) {
			return $this->_backUrl = $backUrl;
		}
		return null;
	}
	
	public function setBackUrl($url) {
		$this->_backUrl = $url;
		$this->_persistentStorage->set("backUrl",$url);
	}
	
	/**
	 * Persistently store the current URL so we can redirect the user back there once
	 * they return to the site after (hopefully) authorising the application.
	 *
	 * @return void
	 */
	public function establishBackUrl() {
		$backUrl = POA_Utility::getCurrentUrl($this->_client->getDropQueryParams());
		$this->setBackUrl($backUrl);
	}

	/**
	 * Completely destroy the session that this POAObserver instance is working with.
	 * This completely erases all stored tokens.
	 *
	 * @return void
	 */
	public function destroySession() {
		$this->_client->destroyState();
		$this->clearCsrfTokenState();
		$this->clearBackUrl();
		$this->_persistentStorage->removeAll();
	}
	
	/**
	 * =============================
	 * OAuth 2 related functionality
	 * =============================
	 */
	
	/**
	 * Lays down a CSRF state token for this process.
	 *
	 * @return void
	 */
	protected function establishCsrfTokenState() {
		if ($this->_csrfTokenState === null) {
			$this->_csrfTokenState = md5(uniqid(mt_rand(), true));
			$this->_persistentStorage->set('csrfTokenState', $this->_csrfTokenState);
		}
	}
	
	/**
	 * Get the CSRF state token for this process - from persistent
	 * storage if necessary.
	 *
	 * @return string The CSRF state token
	 */
	protected function getCsrfTokenState() {
		if ($this->_csrfTokenState !== null) return $this->_csrfTokenState;
	
		if (($state = $this->_persistentStorage->get("csrfTokenState")) !== null) {
			$this->_csrfTokenState = $state;
		}
	
		return $this->_csrfTokenState;
	}
	
	/**
	 * Remove the current CSRF token state.
	 */
	protected function clearCsrfTokenState() {
		$this->_csrfTokenState = null;
		$this->_persistentStorage->remove("csrfTokenState");
	}
	
	/**
	 * =============================
	 * OAuth 1 related functionality
	 * =============================
	 */
	
}
