<?php
/*
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
		'test'
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


	/**
	 * holds an instance of the Zend_Oauth_Consumer class
	 * @return Zend_Oauth_Consumer
	 */
	protected static function get_zend_oauth_consumer_class($callback = "nocallback"){
		if(!self::$consumer_key || !self::$consumer_secret) {
			user_error("You must set the following variables: LinkedinCallback::consumer_secret AND LinkedinCallback::consumer_key");
		}
		if(!isset(self::$zend_oauth_consumer_class[$callback])) {
			$config = array(
				'requestTokenUrl' => 'https://api.linkedin.com/uas/oauth/requestToken',
				'userAuthorizationUrl' => 'https://api.linkedin.com/uas/oauth/authorize',
				'accessTokenUrl' => 'https://api.linkedin.com/uas/oauth/accessToken',
				'consumerKey' => self::$consumer_key,
				'consumerSecret' => self::$consumer_secret,
			);
			if($callback && $callback != "nocallback") {
				$config["callbackUrl"] = $callback;
			}
			else {
				$config["callbackUrl"] = "http://dev.sunnysideup.co.nz/bla";
			}
			self::$zend_oauth_consumer_class[$callback] = new Zend_Oauth_Consumer($config);
			self::$zend_oauth_consumer_class_config[$callback] = $config;
			$token = self::$zend_oauth_consumer_class[$callback]->getRequestToken();
			Session::set('LinkedinRequestToken', serialize($token));
		}
		return self::$zend_oauth_consumer_class[$callback];
	}

	function getResponse($url){
			// Fill the keys and secrets you retrieved after registering your app
			$oauth = new OAuth(self::$consumer_key, self::$consumer_secret);
			$token = unserialize(Session::get('LinkedinRequestToken'));
			$oauth->setToken($token);

			$params = array();
			$headers = array();
			$method = OAUTH_HTTP_METHOD_GET;

			// Specify LinkedIn API endpoint to retrieve your own profile
			$url = "http://api.linkedin.com/v1/people/~";

			// By default, the LinkedIn API responses are in XML format. If you prefer JSON, simply specify the format in your call
			$url = "http://api.linkedin.com/v1/people/~?format=json";

			// Make call to LinkedIn to retrieve your own profile
			$oauth->fetch($url, $params, $method, $headers);

			echo $oauth->getLastResponse();
	}


//======================================= STATIC METHODS ===============================================

	/**
	 *
	 * @ return Array
	 */
	static function get_current_user(){
		$member = Member::currentUser();
		if($member && $member->LinkedinID) {
			$LinkedinClass = self::get_linkedin_class();
			return $LinkedinClass->usersShow($member->LinkedinID);
		}
		else {
			return array();
		}
	}


	/**
	 * returns true on success
	 * TODO: check how that works with "link making small techniques".
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
		if($to instanceOf Member) {
			$to = $to->LinkedinID;
		}
		$member = Member::currentUser();
		if($member) {
			if($LinkedinClass = self::get_Linkedin_class()) {
				$LinkedinDetails = self::is_valid_user($to);
				if(!empty($LinkedinDetails["screen_name"])) {
					$toScreenName = $LinkedinDetails["screen_name"];
					$message = "@$toScreenName ".$message." ".$link;
					$LinkedinClass->statusesUpdate($message);
					//followers can also get a direct message
					$followers = self::get_list_of_friends();
					$isFollower = false;
					foreach($followers as $follower) {
						if($follower["id"] == $to) {
							$isFollower = true;
						}
					}
					if($isFollower) {
						$text = $message." ".$link;
						$userId = $to;
						$includeEntities = false;
						//returns the user's details as an array if sent successfully
						//and a string with error message if sent unsuccessfully
						$outcome = $LinkedinClass->directMessagesNew($text, $userId, $screenName = null, $includeEntities = false);
						if(is_array($outcome)) {
							return true;
						}
						else {
							//debug::log($outcome);
						}
					}
					return true;
				}
				else {
					//debug::log("Linkedin user not found");
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
		$rawArray = array();
		$finalArray = array();
		$member = Member::currentUser();
		if($member) {
			if($LinkedinClass = self::get_Linkedin_class()) {
				$followersArray = $LinkedinClass->followersIds($member->LinkedinID, null);
				$followersArrayIDs = empty($followersArray["ids"]) ? array() :$followersArray["ids"];
				$ids = "";
				$count = 0;
				if(count($followersArrayIDs)) {
					foreach($followersArrayIDs as $friend){
						$ids .= "{$friend},";
						$count++;
						if(!($count % 100) || $count == count($followersArrayIDs)){
							$rawArray += $LinkedinClass->usersLookup($ids);
							$ids = "";
						}
					}
				}
				//we are retrieving more so that we can select the right ones.
				$searchResults = $LinkedinClass->usersSearch("q=".$searchString."}", $limit * 3);
				if(count($searchResults)) {
					$rawArray += $searchResults;
				}
				if(Director::isDev()) {
					$rawArray[] = $LinkedinClass->usersShow($member->LinkedinID);
				}
				if(count($rawArray)) {
					$limitCount = 0;
					foreach($rawArray as $friend) {
						if(empty($friend["id"])){$friend["id"] = 0; }
						if(empty($friend["name"])){$friend["name"] = ""; }
						if(empty($friend["screen_name"])){$friend["screen_name"] = ""; }
						if(empty($friend["profile_image_url"])){$friend["profile_image_url"] = self::get_default_avatar(); }
						$haystack = $friend["name"].$friend["screen_name"];
						if(!$searchString || stripos("-".$haystack, $searchString ) ) {
							$name =$friend["name"];
							$name .= " (";
							$name .= $friend["screen_name"];
							$name .= ")";
							$finalArray[$friend["id"]] = array(
								"id" => $friend["id"],
								"name" => $name,
								"picture" => $friend["profile_image_url"]
							);
							$limitCount++;
						}
						if($limitCount > $limit) {
							break;
						}
					}
				}
			}
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
			$returnTo = urlencode($returnTo);
		}
		$callback = $this->AbsoluteLink('Connect?ret=' . $returnTo);
		$callback = $token->addToUrl($callback);
		$consumer = self::get_zend_oauth_consumer_class($callback);
		$token = $consumer->getRequestToken();
		Session::set('LinkedinRequestToken', serialize($token));
		$url = $consumer->getRedirectUrl(array(
			'force_login' => 'true'
		));
		return self::curr()->redirect($url);
	}

	/**
	 * Connects the current user.
	 * completes connecting process
	 * @param SS_HTTPRequest $reg
	 */
	public function Connect(SS_HTTPRequest $req) {
		$securityToken = SecurityToken::inst();
		if(!$securityToken->checkRequest($req)) return $securityToken->httpError(400);
		$data = null;
		$access = null;
		$user = 0;
		if($req->getVars() && !$req->getVar('oauth_problem') && Session::get('LinkedinRequestToken')) { //&& /**/
			$consumer = self::get_zend_oauth_consumer_class();
			$token = unserialize(Session::get('LinkedinRequestToken'));
			try {
				$access = $consumer->getAccessToken($req->getVars(), $token);
				die("aaa");
				$client = $access->getHttpClient(self::$zend_oauth_consumer_class_config["nocallback"]);
				$client->setUri('http://api.linkedin.com/v1/people/~:(id,first-name,last-name)');
				$client->setMethod(Zend_Http_Client::GET);
				$client->setHeaders('x-li-format', 'json');
				$response = $client->request();
				$data = $response->getBody();
				$data = json_decode($data);
			}
			catch(Exception $e) {
				$this->httpError(500, $e->getMessage());
			}
		}
		else {
			//debug::log("could not connect to linkedin");
		}
		Session::clear('LinkedinRequestToken');
		if($data && $user && is_numeric($user) && $access) {
			$this->updateUserFromLinkedinData($user, $data, $access, false);
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

	public function loginUser() {
		$token = SecurityToken::inst();
		$callback = $this->AbsoluteLink('Login');
		$callback = $token->addToUrl($callback);
		$config = array(
			'callbackUrl' => $callback,
			'consumerKey' => self::$consumer_key,
			'consumerSecret' => self::$consumer_secret,
			'siteUrl' => 'https://api.linkedin.com',
			'requestTokenUrl' => 'https://api.linkedin.com/uas/oauth/requestToken',
			'accessTokenUrl' => 'https://api.linkedin.com/uas/oauth/accessToken',
			'authorizeUrl' => 'https://www.linkedin.com/uas/oauth/authenticate'
		);
		$consumer = new Zend_Oauth_Consumer($config);
		$token = $consumer->getRequestToken();
		Session::set('LinkedinRequestToken', serialize($token));
		$url = $consumer->getRedirectUrl();
		return self::curr()->redirect($url);
	}

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
			$access = $consumer->getAccessToken($req->getVars(), $token);
			$client = $access->getHttpClient(self::$zend_oauth_consumer_class_config["nocallback"]);
			$client->setUri('http://api.linkedin.com/v1/people/~:(id,first-name,last-name)');
			$client->setMethod(Zend_Http_Client::GET);
			$client->setHeaders('x-li-format', 'json');
			$response = $client->request();

			$data = $response->getBody();
			$data = json_decode($data);
			$id = $data->id;
			$name = "$data->firstName $data->lastName";
		}
		catch(Exception $e) {
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.message', $e->getMessage());
			Session::set('FormInfo.LinkedinLoginForm_LoginForm.formError.type', 'error');
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
		Session::clear('Linkedin.Request.Token');
		$m = $this->CurrentMember();
		if($m) {
			$m->LinkedinID = $m->LinkedinSecret = $m->LinkedinToken = $m->LinkedinPicture = $m->LinkedinName = $m->LinkedinScreenName  = null;
			$m->write();
		}
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
	protected function updateUserFromLinkedinData($user, $twitterData, $access, $keepLoggedIn = false){
		//clean up data
		if(is_array($linkedinData) ) {
			$obj = new DataObject();
			foreach($linkedinData as $key => $value) {
				$obj->$key = $value;
			}
			$linkedinData = $obj;
		}

		//find member
		$member = null;
		if($user) {
			$member = DataObject::get_one('Member', '"LinkedinID" = \'' . Convert::raw2sql($user) . '\'');
		}
		if(!$member) {
			$member = Member::currentUser();
			if(!$member) {
				$member = new Member();
			}
		}
		//check if anyone else uses the email:
		if(!$member->exists()) {
			if($linkedinEmail = Convert::raw2sql($linkedinEmail->email)) {
				$memberID = intval($member->ID)-0;
				$existingMember = DataObject::get_one(
					'Member',
					'("Email" = \'' . $linkedinEmail . '\' OR "LinkedinEmail" = \''.$linkedinEmail.'\') AND "Member"."ID" <> '.$memberID
				);
				if($existingMember) {
					$member = $existingMember;
				}
			}
		}
		$member->LinkedinID = empty($user) ? 0 : $user;
		$member->LinkedinURL = empty($LinkedinData->link) ? "" : $LinkedinData->link;
		$member->LinkedinPicture = empty($LinkedinData->picture) ? "" : $LinkedinData->picture;
		$member->LinkedinName = empty($LinkedinData->name) ? "" : $LinkedinData->name;;
		$member->LinkedinEmail = empty($LinkedinData->email) ? "" : $LinkedinData->email;
		$member->LinkedinFirstName = empty($LinkedinData->first_name) ? "" : $LinkedinData->first_name;
		$member->LinkedinMiddleName = empty($LinkedinData->middle_name) ? "" : $LinkedinData->middle_name;
		$member->LinkedinLastName = empty($LinkedinData->last_name) ? "" : $LinkedinData->last_name;
		$member->LinkedinUsername = empty($LinkedinData->username) ? "" : $LinkedinData->username;
		if(!$member->FirstName) {
			$member->FirstName = $member->LinkedinFirstName;
		}
		if(!$member->Surname) {
			$member->Surname = $member->LinkedinLastName;
		}
		if(!empty($LinkedinData->email)) {
			if(!$member->Email) {
				$memberID = intval($member->ID)-0;
				$anotherMemberWithThisEmail = DataObject::get_one(
					'Member',
					'("Email" = \'' . $LinkedinData->email . '\' OR "LinkedinEmail" = \''.$LinkedinData->email.'\') AND "Member"."ID" <> '.$memberID
				);
				if(!$anotherMemberWithThisEmail) {
					$member->Email = $LinkedinData->email;
				}
			}
		}
		$member->write();
		$oldMember = Member::currentUser();
		if($oldMember) {
			if($oldMember->ID != $member->ID) {
				$oldMember->logout();
				$member->login($keepLoggedIn);
			}
			else {
				//already logged in - nothing to do.
			}
		}
		else {
			$member->login($keepLoggedIn);
		}
		return $member;
	}


//========================================================== TESTS =====================================

	function debug(){
		$member = Member::currentUser();
		if($member) {
			echo "<ul>";
			echo "<li>LinkedinID: ".$member->LinkedinID."</li>";
			echo "<li>LinkedinName: ".$member->LinkedinName."</li>";
			echo "<li>LinkedinScreenName: ".$member->LinkedinScreenName."</li>";
			echo "<li>LinkedinToken: ".$member->LinkedinToken."</li>";
			echo "<li>LinkedinSecret: ".$member->LinkedinSecret."</li>";
			echo "<li>LinkedinPicture: ".$member->LinkedinPicture."</li>";
			echo "</ul>";
		}
		else {
			echo "<h2>You are not logged in.</h2>";
		}
	}


}
