<?php

/**
 * A POA_PersistentStorage_BaseAbstract implementation which stores data in the session.
 */
class POA_PersistentStorage_Session extends POA_PersistentStorage_BaseAbstract {
	
	protected $_prefix = "";
	
	public function __construct($prefix = "") {
		if ($prefix) {
			$this->_prefix = $prefix;
		}
		if (!session_id()) {
			session_start();
		}
	}
	
	public function get($key,$default=null) {
		if (array_key_exists($this->_prefix,$_SESSION)) {
			if (array_key_exists($key,$_SESSION[$this->_prefix])) {
				if (
					$_SESSION[$this->_prefix][$key][0]
					&& $_SESSION[$this->_prefix][$key][0] <= time()
				) {
					unset($_SESSION[$this->_prefix][$key]);
				} else {
					return $_SESSION[$this->_prefix][$key][1];
				}
			}
		}
		return $default;
	}
	
	public function set($key,$value,$expiry=60) {
		if (!array_key_exists($this->_prefix,$_SESSION)) {
			$_SESSION[$this->_prefix] = array();
		}
		if ($expiry > 0) {
			$expiryTime = time() + $expiry*60;
		} else {
			$expiryTime = 0;
		}
		$_SESSION[$this->_prefix][$key] = array($expiryTime,$value);
	}
	
	public function remove($key) {
		if (array_key_exists($this->_prefix,$_SESSION)) {
			if (array_key_exists($key,$_SESSION[$this->_prefix])) {
				unset($_SESSION[$this->_prefix][$key]);
			}
		}
	}
	
	public function removeAll() {
		if (array_key_exists($this->_prefix,$_SESSION)) {
			unset($_SESSION[$this->_prefix]);
		}
	}
	
}
