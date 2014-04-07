<?php
class POA_Observer_ResponseCacher implements POA_Observer_ObserverInterface {
	
	const CACHE_STORE_VAR = "_enableResponseCaching";
	
	/**
	 * The POA_Client_BaseAbstract instance that this Observer listens to.
	 */
	protected $_client = null;
	
	/**
	 * Internal variable to keep track of whether API calls should be cached. If any value
	 * in this array is FALSE, the next api call shouldn't be cached. Values sometimes
	 * need to be FALSE even when self::ENABLE_CACHING is true (e.g. when fetching an
	 * access token).
	 *
	 * @var array
	 */
	protected $_enableResponseCaching = array(
		'validAccessToken' => false,
	);
	
	/**
	 * @var POA_PersistentStorage_BaseAbstract The POA_PersistentStorage_BaseAbstract that handles
	 * persistently storing responses from API calls to an OAuth provider.
	 */
	protected $_persistentStorage = null;
	
	/**
	 * @var string If API calls should be cached, this is populated
	 * with the current cache key that we're working with.
	 */
	protected $_cacheKey = null;
	
	protected function cachingEnabled() {
		foreach($this->_enableResponseCaching as $value) {
			if (!$value) {
				return false;
			}
		}
		if ($this->_client->isRequestingUnauthorizedRequestToken()) {
			return false;
		}
		return true;
	}
	
	public function __construct(POA_Client_BaseAbstract $client) {
		$this->_client = $client;
		
		/**
		 * Populate the response cacher with an POA_PersistentStorage_BaseAbstract implementation.
		*/
		if ($this->_persistentStorage === null) {
			$prefix = get_class($this->_client);
			$this->_persistentStorage = new POA_PersistentStorage_Session("{$prefix}ResponseCacher");
		}
		
		$this->_enableResponseCaching['validAccessToken'] = (bool)$this->_persistentStorage->get(static::CACHE_STORE_VAR,false);
	}
	
	public function poaInit() {
		
	}
	
	public function poaEventNotification($event,$arg1,$arg2,$arg3,$arg4,$arg5,$arg6) {
		switch($event) {
			case POA_Client_OAuth1_BaseAbstract::EVENT_VALID_ACCESS_TOKEN:
			case POA_Client_OAuth2_BaseAbstract::EVENT_VALID_ACCESS_TOKEN:
				$this->_persistentStorage->set(static::CACHE_STORE_VAR,1,15);
				$this->_enableResponseCaching['validAccessToken'] = true;
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_BEFORE_FETCH:
			case POA_Client_OAuth2_BaseAbstract::EVENT_BEFORE_FETCH:
				if (
					$this->cachingEnabled()
					&& ($arg4 === POA_Utility::HTTP_METHOD_GET)
				) {
					$this->_cacheKey = "CACHE_".serialize(func_get_args());
					$cachedResponse = $this->_persistentStorage->get($this->_cacheKey,false);
					if ($cachedResponse !== false) {
						$arg1->copyFrom($cachedResponse);
					}
				}
				
				break;
				
				
			case POA_Client_OAuth1_BaseAbstract::EVENT_AFTER_FETCH:
			case POA_Client_OAuth2_BaseAbstract::EVENT_AFTER_FETCH:
				if (
					$this->cachingEnabled()
					&& $this->_cacheKey
					&& ($arg4 === POA_Utility::HTTP_METHOD_GET) // only store GETs (reads)
					&& ($arg1->isSuccess()) // only store successful responses
				) {
					$this->_persistentStorage->set($this->_cacheKey,$arg1,10);
				}
				
				$this->_cacheKey = null;
				
				break;
				
			
			case POA_Client_OAuth1_BaseAbstract::EVENT_INVALID_ACCESS_TOKEN:
			case POA_Client_OAuth2_BaseAbstract::EVENT_INVALID_ACCESS_TOKEN:
			case POA_Client_OAuth1_BaseAbstract::EVENT_FAILED_UNAUTHORIZED_REQUEST_TOKEN_RESPONSE:
			case POA_Client_OAuth1_BaseAbstract::EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE:
			case POA_Client_OAuth2_BaseAbstract::EVENT_FAILED_REQUEST_TO_ACCESS_TOKEN_EXCHANGE:
				$this->_persistentStorage->removeAll();
				$this->_enableResponseCaching['validAccessToken'] = false;
				
				break;
		}
	}
}
