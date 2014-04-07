<?php

abstract class POA_Client_BaseAbstract {
	
	abstract public function getAuthenticationUrl();
	abstract public function destroyState();
	abstract public function hasValidAccessToken();
	
	/**
	 * The OAuth consumer key of your registered app
	 */
	const CLIENT_ID = null;
	
	/**
	 * The corresponding consumer secret
	 */
	const CLIENT_SECRET = null;
	
	/**
	 * The URL users are redirected to after authorization.
	 *
	 * @var string
	 */
	const REDIRECT_URL = null;
	
	const AUTHORIZATION_ENDPOINT = null;
	
	/**
	 * The endpoint where request tokens are exchanged for access tokens.
	 *
	 * @var string
	 */
	const ACCESS_TOKEN_ENDPOINT = null;
	
	const ACCESS_TOKEN_REQUEST_METHOD = POA_Utility::HTTP_METHOD_POST;
	
	const DEFAULT_API_BASE = null;
	
	private static $_clients = array();
	
	/**
	 * An associative array of additional parameters to append to the query string when
	 * requesting an authentication URL. Keys and values should not be URL encoded.
	 * 
	 * This can be used to request additional scopes for OAuth2 applications, e.g:
	 * 
	 * protected $_extraAuthParams = array(
	 * 	'scope' => 'publish_actions,email',
	 * );
	 *  
	 * @var array
	 */
	protected $_extraAuthParams = array();
	
	protected $_dropQueryParams = array(
		'oauth_token',
		'oauth_token_secret',
		'oauth_verifier',
		'code',
		'state',
		'signed_request',
	);
	
	protected $_observers = array();
	
	final public static function get() {
		$className = get_called_class();
		
		if (!isset(self::$_clients[$className])) {
			$client = new $className();
			
			if (!($client instanceof POA_Client_ApplicationInterface)) {
				throw new Exception(
					"Registered clients must implement the POA_Client_ApplicationInterface interface."
				);
			}
						
			self::$_clients[$className] = $client;
		}
		
		return self::$_clients[$className];
	}
	
	/**
	 * Get the name of the root application class for this POA_Client_BaseAbstract.
	 * The root application class is the most ancestral class that implements the
	 * POA_Client_ApplicationInterface interface.
	 * @param object|string the item to get the root application class for
	 * @return string|NULL
	 */
	final public static function getRootApplicationClassFor($class) {
		static $cache = array();
		
		if (is_object($class)) {
			$class = get_class($class);
		}
		
		if (array_key_exists($class,$cache)) {
			return $cache[$class];
		}
		
		if (is_subclass_of($class,"POA_Client_BaseAbstract")) {
			$className = $class;
			$interfaces = class_implements($className);
			while($className) {
				$parentClass = get_parent_class($className);
				$parentInterfaces = class_implements($parentClass);
				
				if (isset($interfaces['POA_Client_ApplicationInterface'])) {
					if (!isset($parentInterfaces['POA_Client_ApplicationInterface'])) {
						return $cache[$class] = $className;
					}
				}
				
				$className = $parentClass;
				$interfaces = $parentInterfaces;
			}
		}
		
		return $cache[$class] = null;
	}
	
	protected function __construct() {
		$className = get_class($this);
		
		if (!static::CLIENT_ID) {
			throw new Exception("$className - Client ID must be set.");
		}
		
		if (!static::CLIENT_SECRET) {
			throw new Exception("$className - Client secret must be set.");
		}
		
		if (!static::AUTHORIZATION_ENDPOINT) {
			throw new Exception("$className - Authorization endpoint must be set.");
		}
		
		if (!static::ACCESS_TOKEN_ENDPOINT) {
			throw new Exception("$className - Access token endpoint must be set.");
		}
		
		if (!static::DEFAULT_API_BASE) {
			throw new Exception("$className - API base must be set.");
		}
		
		if (!static::REDIRECT_URL) {
			throw new Exception("$className - Redirect URL must be set.");
		}
	}
	
	protected function dispatch($event,$arg1=null,$arg2=null,$arg3=null,$arg4=null,$arg5=null,$arg6=null) {
		foreach($this->_observers as $observer) {
			$observer->poaEventNotification($event,$arg1,$arg2,$arg3,$arg4,$arg5,$arg6);
		}
	}
	
	public function addObserver($className) {
		if (!isset($this->_observers[$className])) {
			$interfaces = class_implements($className);
			if (isset($interfaces["POA_Observer_ObserverInterface"])) {
				$this->_observers[$className] = new $className($this);
				$this->_observers[$className]->poaInit();
				return true;
			} else {
				throw new Exception("
					'$className' doesn't implement POA_Observer_ObserverInterface.
				");
			}
		}
		return false;
	}
	
	public function removeObserver($className) {
		if (isset($this->_observers[$className])) {
			unset($this->_observers[$className]);
			return true;
		}
		return false;
	}
	
	public function getObserver($className) {
		if (isset($this->_observers[$className])) {
			return $this->_observers[$className];
		}
		return null;
	}
	
	public function isRequestingUnauthorizedRequestToken() {
		return false;
	}
	
	public function getDropQueryParams() {
		return $this->_dropQueryParams;
	}
	
	public function dropQueryParamsStripped($url) {
		return POA_Utility::dropQueryParams($url,$this->_dropQueryParams);
	}
	
	public function getRootApplicationClass() {
		return self::getRootApplicationClassFor($this);
	}
	
}
