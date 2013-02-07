<?php
/**
 * http://www.formatix.eu/en/update-linkedin-status-using-zend-oauth.html
 * http://www.contentwithstyle.co.uk/content/linkedin-and-zendoauth/
 * https://developer.linkedin.com/documents/authentication#granting
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 * see: https://developer.linkedin.com/documents/reading-data
 *
 */

if(!file_exists('Zend/Oauth.php')) {
	// The autoloader can skip this if LinkedinCallback is called before Linkedin/_config is included
	require_once dirname(dirname(dirname(__FILE__))) . '/_config.php';
}

//require_once  dirname(dirname(dirname(__FILE__))).'/thirdparty/linkedin/linkedin_3.3.0.class.php';

class LinkedinCallback extends SocialIntegrationControllerBaseClass implements SocialIntegrationAPIInterface {

//======================================= AVAILABLE METHODS ===============================================

	/**
	 * Standard SS variable determining what this controller can do
	 * @var Array
	 */
	public static $allowed_actions = array(
		'LinkedinConnect',
		'Connect',
		'Login',
		'FinishLinkedin',
		'remove',
		'test',
		'basicconcept'
	);

//======================================= CONFIGURATION STATIC ===============================================


	/**
	 * Get from Linkedin
	 * @var String
	 */
	private static $consumer_key = null;
		public static function set_consumer_key($s) {self::$consumer_key = $s;}
		public static function get_consumer_key() {return self::$consumer_key;}

	/**
	 * Get from Linkedin
	 * @var String
	 */
	private static $consumer_secret = null;
		public static function set_consumer_secret($s) {self::$consumer_secret = $s;}
		public static function get_consumer_secret() {return self::$consumer_secret;}

	/**
	 * Get from Linkedin
	 * @see: https://developer.linkedin.com/documents/authentication#granting
	 * @var Array
	 */
		private static $permission_scope =  'r_emailaddress,r_network,w_messages';
		public static function set_permission_scope($s) {self::$permission_scope = $s;}
		public static function get_permission_scope() {return self::$permission_scope;}

//======================================= CONFIGURATION NON-STATIC ===============================================

//======================================= THIRD-PARTY CONNECTION ===============================================
	/**
	 * used to hold the Zend_Oauth_Consumer
	 * we keep one for each callback
	 * the default callback is nocallback
	 * @var array
	 */
	private static $zend_oauth_consumer_class = null;

	/**
	 * when creating a new Zend_Oauth_Consumer
	 * we also return the configs
	 * To access the standard config use:
	 * self::$zend_oauth_consumer_class_config["nocallback"];
	 *
	 * @var Array
	 */
	private static $zend_oauth_consumer_class_config = null;


	private $options;
	private $consumer;
	private $client;
	private $token;

	/**
	 * holds an instance of the Zend_Oauth_Consumer class
	 * @return Zend_Oauth_Consumer
	 */
	protected function getConsumer($callback = "nocallback"){
		if(!self::$consumer_key || !self::$consumer_secret) {
			user_error("You must set the following variables: LinkedinCallback::consumer_secret AND LinkedinCallback::consumer_key");
		}
		$this->options = array(
			'version' => '1.0',
			'localUrl' => Director::absoluteBaseURL().'LinkedinCallback/',
			'requestTokenUrl' => 'https://api.linkedin.com/uas/oauth/requestToken',
			'userAuthorizationUrl' => 'https://api.linkedin.com/uas/oauth/authorize',
			'accessTokenUrl' => 'https://api.linkedin.com/uas/oauth/accessToken',
			'consumerKey' => self::get_consumer_key(),
			'consumerSecret' => self::get_consumer_secret(),
		);
		if($callback && $callback != "nocallback") {
			$this->options["callbackUrl"] = Director::absoluteBaseURL().$callback;
		}
		else {
			$this->options["callbackUrl"] = Director::absoluteBaseURL()."/LinkedinCallback/Connect/";
		}
		$this->consumer = new Zend_Oauth_Consumer($this->options);
		$token = $this->consumer->getRequestToken(array('scope' =>self::get_permission_scope()));
			//Session::set('LinkedinRequestToken', serialize($token));

		if ( !isset ( $_SESSION ['LINKEDIN_ACCESS_TOKEN'] )) {
			// We do not have any Access token Yet
			if (! empty ( $_GET ) && count($_GET) > 1) {
				// But We have some parameters passed throw the URL
				if(!isset($_SESSION ['LINKEDIN_REQUEST_TOKEN'])) {
					$_SESSION ['LINKEDIN_REQUEST_TOKEN'] = null;
				}
				// Get the LinkedIn Access Token
				$this->token = $this->consumer->getAccessToken ( $_GET, unserialize ( $_SESSION ['LINKEDIN_REQUEST_TOKEN'] ) );

				// Store the LinkedIn Access Token
				$_SESSION ['LINKEDIN_ACCESS_TOKEN'] = serialize ( $this->token );
			}
			else {
				// We have Nothing

				// Start Requesting a LinkedIn Request Token
				$this->token = $this->consumer->getRequestToken (array('scope' => self::get_permission_scope()));

				// Store the LinkedIn Request Token
				$_SESSION ['LINKEDIN_REQUEST_TOKEN'] = serialize ( $this->token );

				// Redirect the Web User to LinkedIn Authentication  Page
				$this->consumer->redirect(array("_l" => i18n::get_locale()));

				$url = $this->consumer->getRedirectUrl();
				return self::curr()->redirect($url);

			}
		}
		else {
			// We've already Got a LinkedIn Access Token

			// Restore The LinkedIn Access Token
			$this->token = unserialize ( $_SESSION ['LINKEDIN_ACCESS_TOKEN'] );

		}

		// Use HTTP Client with built-in OAuth request handling
		$this->client = $this->token->getHttpClient($this->options);
		return $this->consumer;

	}

	function getResponse($url, $format = "json"){
		// Set LinkedIn URI
		$this->client->setUri('https://api.linkedin.com/v1/people/~?format=json');
		// Set Method (GET, POST or PUT)
		$this->client->setMethod(Zend_Http_Client::GET);
		// Get Request Response
		$response = $this->client->request();
	}


//======================================= STATIC METHODS ===============================================

	/**
	 * returns an array of data if there is anything and NULL if there is no information.
	 * @ return Array | Null
	 */
	static function get_current_user(){
		$member = Member::currentUser();
		if($member && $member->LinkedinID) {
			$linkedinCallback = new LinkedinCallback();
			if($linkedinCallback->getConsumer()) {
				// Set LinkedIn URI
				$linkedinCallback->client->setUri('https://api.linkedin.com/v1/people/~:(id,email-address,first-name,last-name,picture-url)'); //				$this->client->setUri('http://api.linkedin.com/v1/people/~:(id,first-name,last-name)');
				// Set Method (GET, POST or PUT)
				$linkedinCallback->client->setMethod(Zend_Http_Client::GET);
				// Get Request Response
				$linkedinCallback->client->setHeaders('x-li-format', 'json');

				$response = $linkedinCallback->client->request();

				$data = $response->getBody();
				$data = json_decode($data);
				return $data;
			}
		}
		else {
			return null;
		}
	}

	static function get_updates(){
		return "NOT IMPLEMENTED YET";
	}
	/**
	 * returns true on success
	 * @param Int | Member | String $to
	 * @param String $message
	 * @param String $link - link to send with message
	 * @param Array $otherVariables - other variables used in message.
	 * @return boolean
	 */
	public static function send_message(
		$to = 0,
		$message,
		$link = "",
		$otherVariables = array()
	){
		$member = Member::currentUser();
		if($member && $member->LinkedinID) {
			$linkedinCallback = new LinkedinCallback();
			if($linkedinCallback->getConsumer()) {
				//TO
				if($to instanceOf Member) {
					$to = $to->LinkedinID;
				}
				//MESSAGE
				$message = trim(strip_tags(stripslashes($message)));
				//SUBJECT
				if(!empty($otherVariables["Subject"])) {
					$subject = $otherVariables["Subject"];
				}
				else {
					$subject = substr($message, 0, 30);
				}
				//XML POST
				$body ='<?xml version=\'1.0\' encoding=\'UTF-8\'?>
<mailbox-item>
	<recipients>
		<recipient>
			<person path=\'/people/~\'/>
		</recipient>
		<recipient>
			<person path=\'/people/'.$to.'\'/>
		</recipient>
	</recipients>
	<subject>'.htmlspecialchars($subject).'</subject>
	<body>'.htmlspecialchars($message).' '.$link.'</body>
</mailbox-item>';
				// Set LinkedIn URI
				$linkedinCallback->client->setUri('https://api.linkedin.com/v1/people/~/mailbox'); //				//
				// Set Method (GET, POST or PUT)
				$linkedinCallback->client->setMethod(Zend_Http_Client::POST);
				//SET BODY
				$linkedinCallback->client->setRawData($body,'text/xml');
				//SET XML
				$linkedinCallback->client->setHeaders('Content-Type', 'text/xml');
				//send it!
				$response = $linkedinCallback->client->request();
				//did it go ok?
				if($response->isSuccessful()) {
					return 1;
				}
			}
		}
		return false;
	}

	/**
	 *
	 * If we can not find enough followers, we add any user.
	 *
	 * @param Int $limit - the number of users returned
	 * @param String $search - the users searched for
	 *
	 * @return Array (array("id" => ..., "name" => ...., "picture" => ...))
	 */
	public static function get_list_of_friends($limit = 12, $searchString = ""){
		$finalArray = array();
		$member = Member::currentUser();
		if($member && $member->LinkedinID) {
			$linkedinCallback = new LinkedinCallback();
			if($linkedinCallback->getConsumer()) {
				$me = null;
				if(Director::isDev()) {
					$me = self::get_current_user();
				}
				// Set LinkedIn URI
				$linkedinCallback->client->setUri('https://api.linkedin.com/v1/people/~/connections:(id,first-name,last-name,picture-url)'); //				//
				// Set Method (GET, POST or PUT)
				$linkedinCallback->client->setMethod(Zend_Http_Client::GET);
				// Get Request Response
				$linkedinCallback->client->setHeaders('x-li-format', 'json');

				$response = $linkedinCallback->client->request();

				$data = $response->getBody();
				$data = json_decode($data);
				if($me) {
					//adding yourself to the top of the list
					array_unshift($data->values, $me);
				}
				if($data && is_object($data) && $data->values && is_array($data->values) && count($data->values) ) {
					foreach($data->values as $key => $friend) {
						if($key > $limit) {
							break;
						}
						if(empty($friend->firstName)) { $friend->firstName = "";}
						if(empty($friend->lastName)) { $friend->lastName = "";}
						if(empty($friend->pictureUrl)) { $friend->pictureUrl = "";}
						$finalArray[$key] = array(
							"id" => $friend->id,
							"name" => $friend->firstName." ".$friend->lastName,
							"picture" => $friend->pictureUrl
						);
					}
				}
			}
		}
		if(!count($finalArray)) {
			$finalArray = null;
		}
		return $finalArray;
	}

	/**
	 * checks if a user exists
	 * @param String $id - linkedin ID
	 */
	public static function is_valid_user($id){
		return true;
	}

//======================================= STANDARD SS METHODS ===============================================



	public function __construct() {
		if(self::$consumer_secret == null || self::$consumer_key == null) {
			user_error('Cannot instigate a LinkedinCallback object without a consumer secret and key', E_USER_ERROR);
		}
		parent::__construct();
	}


//======================================= CONNECT ===============================================

	/**
	 * easy access to the connection
	 *
	 */
	public function LinkedinConnect() {
		if($this->isAjax()) {
			return $this->connectUser($this->Link('FinishLinkedin'));
		}
		else {
			Session::set("BackURL", $this->returnURL());
			return $this->connectUser($this->returnURL());
		}
	}

	/**
	 * STEP 1 of the connecting process
	 * @param String $returnTo - the URL to return to
	 * @param Array $extra - additional paramaters
	 */
	public function connectUser($returnTo = '', Array $extra = array()) {
		$token = SecurityToken::inst();
		if($returnTo) {
			$returnTo = $token->addToUrl($returnTo);
		}
		Session::set("BackURL", $returnTo);
		$callback = $this->AbsoluteLink('Connect');
		return self::curr()->redirect($callback);
	}

	/**
	 * Connects the current user.
	 * completes connecting process
	 * @param SS_HTTPRequest $reg
	 */
	public function Connect(SS_HTTPRequest $req) {
		//$securityToken = SecurityToken::inst();
		//if(!$securityToken->checkRequest($req)) return $this->httpError(400);
		try{
			$this->getConsumer(); //&& /**/
			// Set LinkedIn URI
			$this->client->setUri('https://api.linkedin.com/v1/people/~:(id,email-address,first-name,last-name,picture-url)'); //				$this->client->setUri('http://api.linkedin.com/v1/people/~:(id,first-name,last-name)');
			// Get Request Response
			// Set Method (GET, POST or PUT)
			$this->client->setMethod(Zend_Http_Client::GET);

			$response = $this->client->request();
			$responseBody =  $response->getBody();
			$data = simplexml_load_string($responseBody);
			if($data) {
				$this->updateUserFromLinkedinData($data);
			}
		}
		catch(Exception $e) {
			$this->httpError(500, $e->getMessage());
			SS_Log::log(print_r($e, 1), SS_Log::ERR);
		}
		$returnURL = $this->returnURL();
		return $this->redirect($returnURL);
	}


	public function FinishLinkedin($request) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);
		if($this->CurrentMember()->LinkedinID) {
			return '<script type="text/javascript">//<![CDATA[
			opener.LinkedinResponse(' . \Convert::raw2json(array(
				'name' => $this->CurrentMember()->LinkedinName,
				'removeLink' => $token->addToUrl($this->Link('RemoveLinkedin')),
			)) . ');
			window.close();
			//]]></script>';
		} else {
			return '<script type="text/javascript">window.close();</script>';
		}
	}





//==================================== LOGIN ==============================================

	/**
	 * Works with the login form
	 */
	public function Login(SS_HTTPRequest $req) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($req)) return $this->httpError(400);
		if($req->getVar('oauth_problem')) {
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.message', 'Login cancelled.');
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#LinkedinLoginForm_LoginForm_tab');
		}
		$consumer = self::get_zend_oauth_consumer_class();
		//$token = Session::get('Linkedin.Request.Token');
		if(is_string($token)) {
			$token = unserialize($token);
		}
		try{
			$this->getConsumer();
			$this->client->setUri('http://api.linkedin.com/v1/people/~:(id,first-name,last-name)');
			$this->client->setMethod(Zend_Http_Client::GET);
			$this->client->setHeaders('x-li-format', 'json');
			$response = $this->client->request();

			$data = $response->getBody();
			$data = json_decode($data);
			$id = $data->id;
			$name = "$data->firstName $data->lastName";
		}
		catch(Exception $e) {
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.message', $e->getMessage());
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.type', 'error');
			SS_Log::log($e, SS_Log::ERR);
			return $this->redirect('Security/login#LinkedinLoginForm_LoginForm_tab');
		}
		if(!is_numeric($user)) {
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.message', 'Invalid user id received from Linkedin.');
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#LinkedinLoginForm_LoginForm_tab');
		}
		if($data && $user && is_numeric($user) && $access) {
			$this->updateUserFromLinkedinData($user, $data, $access, Session::get('SessionForms.LinkedinLoginForm.Remember'));
		}
		$backURL = $this->returnURL();
		return $this->redirect($backURL);
	}


	public function remove($request = null) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);
		return $this->RemoveLinkedin($request);
	}


	// ============================================== REMOVE Linkedin ========================

	public function RemoveLinkedin($request) {
		//security
		//remove Linkedin identification
		//Session::clear('Linkedin.Request.Token');
		$m = $this->CurrentMember();
		if($m) {
			$m->LinkedinID = $m->LinkedinFirstName = $m->LinkedinLastName = $m->LinkedinPicture = $m->LinkedinEmail = null;
			$m->write();
		}
		unset($_SESSION ['LINKEDIN_ACCESS_TOKEN']);
		unset($_SESSION ['LINKEDIN_REQUEST_TOKEN']);
		$returnURL = $this->returnURL();
		$this->redirect($returnURL);
	}

//========================================================== HELPER FUNCTIONS =====================================


//========================================================== HELPER FUNCTIONS =====================================



	/**
	 * Saves the Linkedin data to the member and logs in the member if that has not been done yet.
	 * @param Int $user - the ID of the current twitter user
	 * @param Object $twitterData - the data returned from twitter
	 * @param Object $access - access token
	 * @param Boolean $keepLoggedIn - does the user stay logged in
	 * @return Member
	 */
	protected function updateUserFromLinkedinData($data){
		//find member
		$member = null;
		if($data) {
			$member = DataObject::get_one('Member', '"LinkedinID" = \'' . Convert::raw2sql($data->id) . '\'');
		}
		if(!$member) {
			$member = Member::currentUser();
			if(!$member) {
				$member = new Member();
			}
		}
		if($linkedinEmail = Convert::raw2sql($data->{'email-address'})) {
			$memberID = intval($member->ID)-0;
			$existingMember = DataObject::get_one(
				'Member',
				'("Email" = \'' . $linkedinEmail . '\' OR "LinkedinEmail" = \''.$linkedinEmail.'\') AND "Member"."ID" <> '.$memberID
			);
			if($existingMember) {
				$member = $existingMember;
			}
		}
		$member->LinkedinID = Convert::raw2sql(strval($data->id));
		$member->LinkedinEmail = $linkedinEmail;
		$member->LinkedinPicture = Convert::raw2sql(strval($data->{'picture-url'}));
		$member->LinkedinFirstName = Convert::raw2sql(strval($data->{'first-name'}));
		$member->LinkedinLastName = Convert::raw2sql(strval($data->{'last-name'}));
		if(!$member->FirstName) {
			$member->FirstName = $member->LinkedinFirstName;
		}
		if(!$member->Surname) {
			$member->Surname = $member->LinkedinLastName;
		}
		$member->write();
		$oldMember = Member::currentUser();
		if($oldMember) {
			if($oldMember->ID != $member->ID) {
				$oldMember->logout();
				$member->login(true);
			}
			else {
				//already logged in - nothing to do.
			}
		}
		else {
			$member->login(true);
		}
		return $member;
	}


//========================================================== TESTS =====================================

	function meondatabase(){
		$member = Member::currentUser();
		if($member) {
			echo "<ul>";
			echo "<li>LinkedinID: ".$member->LinkedinID."</li>";
			echo "<li>LinkedinEmail: ".$member->LinkedinEmail."</li>";
			echo "<li>LinkedinFirstName: ".$member->LinkedinFirstName."</li>";
			echo "<li>LinkedinLastName: ".$member->LinkedinLastName."</li>";
			echo "<li>LinkedinPicture: <img src=\"".$member->LinkedinPicture."\" /> ".$member->LinkedinPicture."</li>";
			echo "</ul>";
		}
		else {
			echo "<h2>You are not logged in.</h2>";
		}
	}




	public function basicconcept(){
		$this->getConsumer();

		// Set LinkedIn URI
		$this->client->setUri('https://api.linkedin.com/v1/people/~');
		// Set Method (GET, POST or PUT)
		$this->client->setMethod(Zend_Http_Client::GET);
		// Get Request Response
		$response = $this->client->request();

		// Get the XML containing User's Profile
		$content =  $response->getBody();

		// Uncomment Following Line To display XML result
		// header('Content-Type: ' . $response->getHeader('Content-Type'));
		// echo $content;
		// exit;

		// Use simplexml to transform XML to a PHP Object
		$xml = simplexml_load_string($content);

		// Uncomment Following Line To display Simple XML Object Structure
		 echo '<pre>';
		 print_r($xml);
		 echo'</pre>';

		// Display Profile Information as you wish
		$firstName = $xml->{'first-name'};
		$lastName = $xml->{'last-name'};

	}


}
