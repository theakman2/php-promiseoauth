<?php

/**
 * Interface that all classes must implement if they are to listen to events
 * dispatched by POA_Client_BaseAbstracts.
 *
 * Each POAObserver instance listens to events thrown by a single POA_Client_BaseAbstract
 * instance. The POA_Client_BaseAbstract instance is passed into the constructor, so be
 * sure to save this to a class property if necessary. 
 */
interface POA_Observer_ObserverInterface {
	
	/**
	 * The method called when this POAObserver's POA_Client_BaseAbstract triggers
	 * an event.
	 *
	 * @param string $event The name of the event. See the POA_Client_BaseAbstract
	 * class for a list of all possible events.
	 * @param mixed $arg1, $arg2, $arg3, $arg4, $arg5, $arg6 Arguments
	 * a POA_Client_BaseAbstract instance may pass when triggering an event.
	 */
	public function poaEventNotification($event,$arg1,$arg2,$arg3,$arg4,$arg5,$arg6);
	
	public function poaInit();
	
}