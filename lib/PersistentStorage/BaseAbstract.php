<?php
abstract class POA_PersistentStorage_BaseAbstract {
	
	/**
	 * Get the key $key from the store or return $default if it doesn't exist
	 * 
	 * @param mixed $key
	 * @param mixed $default
	 * @return mixed
	 */
	abstract public function get($key,$default=null);
	
	/**
	 * Set the key $key to the value $value and have it expire in $expiry minutes.
	 * If the key already exists, it is overidden and the expiry is topped up.
	 * 
	 * @param mixed $key
	 * @param mixed $value
	 * @param float $expiry
	 * @return void
	 */
	abstract public function set($key,$value,$expiry=60);
	
	/**
	 * Remove the specified $key from the store. If the key doesn't exist, no action will
	 * take place.
	 * @param mixed $key
	 * @return void
	 */
	abstract public function remove($key);
	
	/**
	 * Remove all keys from the store.
	 * @return void
	 */
	abstract public function removeAll();
}