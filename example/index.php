<?php

/**
 * Registers the library's autoloader to remove the need to manually `require` or
 * `include` every file.
 */
require_once "../lib/Autoloader.php";
POA_Autoloader::register();

/**
 * Change this to be the absolute, publicly accessible URL for this file.
 */
const REDIRECT_URL = "http://mysite.com/promiseoauth/example/index.php";

class MyFacebookApp extends POA_Client_OAuth2_FacebookAbstract {

	const CLIENT_ID = 'your client id';
	const CLIENT_SECRET = 'your client secret';
	const REDIRECT_URL = REDIRECT_URL;

	public function __construct() {
		parent::__construct();
		
		/**
		 * The POA_Observer_Generic class automatically handles requesting access tokens
		 * and redirecting users to the OAuth server for authorization, among other
		 * things.
		 */
		$this->addObserver("POA_Observer_Generic");
		
		/**
		 * The POA_Observer_ResponseCacher class automatically handles caching successful
		 * responses so you're not constantly hitting the OAuth server.
		 */
		$this->addObserver("POA_Observer_ResponseCacher");
	}
	
}

class MyTwitterApp extends POA_Client_OAuth1_TwitterAbstract {

	const CLIENT_ID = 'your client id';
	const CLIENT_SECRET = 'your client secret';
	const REDIRECT_URL = REDIRECT_URL;

	public function __construct() {
		parent::__construct();
		$this->addObserver("POA_Observer_Generic");
		$this->addObserver("POA_Observer_ResponseCacher");
	}
	
}

/**
 * PromiseOAuth uses the singleton pattern, so you should never attempt to instantiate
 * apps directly. Instead, just call the static 'get' method to get the singleton
 * instance associated with that class.
 * 
 * It's possible to have multiple apps for the same OAuth service - just subclass the
 * relevant base class twice. For example, create two subclasses of
 * 'POA_Client_OAuth2_FacebookAbstract' and call MyFacebookApp::get() and
 * MyOtherFacebookApp::get() to get two different instances.
 */
$fbApp = MyFacebookApp::get();
$twApp = MyTwitterApp::get();

?>
<!DOCTYPE html>
<html>
	<head>
		<title>OAuth Example</title>
	</head>
	<body>
		<h1>Facebook</h1>
		<?php
			if ($fbApp->hasValidAccessToken()) {
				/**
				 * We have a valid access token - the user has logged in!
				 * Let's print out their name using the 'getName' and 'getUserId' methods.
				 * These two methods are defined for every application, so you can use
				 * them to get information about the user whether they're logging in via
				 * Facebook, Twitter, Google, or another service.
				 */
				?><h2>Hello, <?php echo $fbApp->getName(); ?></h2><?php
				?><h3>Your ID is: <?php echo $fbApp->getUserId(); ?></h3><?php
				/**
				 * PromiseOAuth allows you to make low-level requests to Facebook's
				 * Graph API. The 'fetch' method allows you to perform lightly-wrapped
				 * requests to the OAuth services' API.
				 */
				$response = $fbApp->fetch("/me");
				if ($response->isSuccess()) {
					/**
					 * The HTTP status code of the response is in the 200s - i.e. success.
					 */
					$result = $response->getResult();
					/**
					 * `$result` is an array of user data.
					 */
					if (isset($result['gender']) && $result['gender']) {
						?><p>Your gender is: <?php echo $result['gender']; ?></p><?php
					} else {
						?><p>Couldn't determine your gender.</p><?php
					}
				} else {
					?><p>Unable to fetch your info.</p><?php
				}
			} else {
				/**
				 * We don't have a valid access token - we should ask the user to log in.
				 */
				$fbAuthUrl = $fbApp->getAuthenticationUrl();
				?><a href="<?php echo $fbAuthUrl; ?>">Login with Facebook</a><?php
			}
		?>
		<h1>Twitter</h1>
		<?php
			if ($twApp->hasValidAccessToken()) {
				?><h2>Hello, <?php echo $twApp->getName(); ?></h2><?php
				?><h3>Your ID is: <?php echo $twApp->getUserId(); ?></h3><?php
				/**
				 * Let's perform a Twitter-specific query...
				 */
				$response = $twApp->fetch("/account/verify_credentials.json");
				if ($response->isSuccess()) {
					$result = $response->getResult();
					if (isset($result['location']) && $result['location']) {
						?><p>You are located in <?php echo $result['location']; ?></p><?php
					} else {
						?><p>Couldn't determine your location.</p><?php
					}
				} else {
					?><p>Unable to fetch your info.</p><?php
				}
			} else {
				$twAuthUrl = $twApp->getAuthenticationUrl();
				?><a href="<?php echo $twAuthUrl; ?>">Login with Twitter</a><?php
			}
		?>
	</body>
</html>