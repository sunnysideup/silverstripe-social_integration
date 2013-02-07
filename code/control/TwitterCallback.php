<?php


if(!file_exists('Zend/Oauth.php')) {
	// The autoloader can skip this if TwitterCallback is called before twitter/_config is included
	require_once dirname(dirname(dirname(__FILE__))) . '/_config.php';
}

require_once  dirname(dirname(dirname(__FILE__))).'/thirdparty/Zend/Oauth/Consumer.php';

class TwitterCallback extends SocialIntegrationControllerBaseClass implements SocialIntegrationAPIInterface {

//======================================= AVAILABLE METHODS ===============================================

	/**
	 * Standard SS variable determining what this controller can do
	 * @var Array
	 */
	public static $allowed_actions = array(
		'TwitterConnect',
		'Connect',
		'Login',
		'FinishTwitter',
		'remove',
		'test'
	);


//======================================= CONFIGURATION STATIC ===============================================


	/**
	 * Get from Twitter
	 * @var String
	 */
	protected static $consumer_secret = null;
		public static function set_consumer_secret($s) {self::$consumer_secret = $s;}
		public static function get_consumer_secret($s) {return self::$consumer_secret;}

	/**
	 * Get from Twitter
	 * @var String
	 */
	protected static $consumer_key = null;
		public static function set_consumer_key($s) {self::$consumer_key = $s;}
		public static function get_consumer_key() {return self::$consumer_key;}

//======================================= CONFIGURATION NON-STATIC ===============================================


//======================================= THIRD-PARTY CONNECTION ===============================================

	/**
	 * used to hold the Zend_Oauth_Consumer
	 * we keep one for each callback
	 * the default callback is nocallback
	 * @var array
	 */
	protected static $zend_oauth_consumer_class = null;

	/**
	 * when creating a new Zend_Oauth_Consumer
	 * we also return the configs
	 * To access the standard config use:
	 * self::$zend_oauth_consumer_class_config["nocallback"];
	 *
	 * @var Array
	 */
	protected static $zend_oauth_consumer_class_config = null;

	/**
	 * holds an instance of the Zend_Oauth_Consumer class
	 * @return Zend_Oauth_Consumer
	 */
	private static function get_zend_oauth_consumer_class($callback = "nocallback"){
		if(!self::$consumer_key || !self::$consumer_secret) {
			user_error("You must set the following variables: TwitterCallback::consumer_secret AND TwitterCallback::consumer_key");
		}
		if(!isset(self::$zend_oauth_consumer_class[$callback])) {
			if($callback && $callback != "nocallback") {
				$config["callbackUrl"] = $callback;
			}
			$config['consumerKey'] = self::$consumer_key;
			$config['consumerSecret'] = self::$consumer_secret;
			$config['siteUrl'] = 'https://api.twitter.com/oauth';
			$config['authorizeUrl'] = 'https://api.twitter.com/oauth/authenticate';
			self::$zend_oauth_consumer_class[$callback] = new Zend_Oauth_Consumer($config);
			self::$zend_oauth_consumer_class_config[$callback] = $config;
		}
		return self::$zend_oauth_consumer_class[$callback];
	}

	/**
	 * used to hold the twitter class
	 * @var Twitter
	 */
	private static $twitter_class = null;

	/**
	 * holds an instance of the Twitter Connect Class
	 * @return Twitter Class
	 */
	private static function get_twitter_class(){
		if(!self::$twitter_class) {
			$member = Member::currentUser();
			if($member && $member->TwitterID) {
				require_once(dirname(dirname(dirname(__FILE__))).'/thirdparty/twitter/twitter.php');
				self::$twitter_class = new Twitter(self::$consumer_key, self::$consumer_secret);
				if($member->TwitterToken && $member->TwitterSecret) {
					self::$twitter_class->setOAuthToken($member->TwitterToken);
					self::$twitter_class->setOAuthTokenSecret($member->TwitterSecret);
				}
			}
		}
		return self::$twitter_class;
	}


//======================================= STATIC METHODS ===============================================

	/**
	 *
	 * @ return Array | Null
	 */
	static function get_current_user(){
		$member = Member::currentUser();
		if($member && $member->TwitterID) {
			$twitterClass = self::get_twitter_class();
			return new ArrayData($twitterClass->usersShow($member->TwitterID));
		}
		else {
			return null;
		}
	}

	/**
	 * returns true on success
	 * Message + Link can not be more than 140 characters!
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
			$to = $to->TwitterID;
		}
		$member = Member::currentUser();
		if($member) {
			if($twitterClass = self::get_twitter_class()) {
				$twitterDetails = self::is_valid_user($to);
				if(!empty($twitterDetails["screen_name"])) {
					$toScreenName = $twitterDetails["screen_name"];
					$message = trim(strip_tags(stripslashes($message)));
					$message = "@$toScreenName ".$message." ".$link;
					$twitterClass->statusesUpdate($message);
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
						$outcome = $twitterClass->directMessagesNew($text, $userId, $screenName = null, $includeEntities = false);
						if(is_array($outcome)) {
							return true;
						}
						else {
							SS_Log::log($outcome, SS_Log::NOTICE);
						}
					}
					return true;
				}
				else {
					SS_Log::log("Twitter user not found", SS_Log::NOTICE);
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
			if($twitterClass = self::get_twitter_class()) {
				$followersArray = $twitterClass->followersIds($member->TwitterID, null);
				$followersArrayIDs = empty($followersArray["ids"]) ? array() :$followersArray["ids"];
				$ids = "";
				$count = 0;
				if(count($followersArrayIDs)) {
					foreach($followersArrayIDs as $friend){
						$ids .= "{$friend},";
						$count++;
						if(!($count % 100) || $count == count($followersArrayIDs)){
							$rawArray += $twitterClass->usersLookup($ids);
							$ids = "";
						}
					}
				}
				//we are retrieving more so that we can select the right ones.
				$searchResults = $twitterClass->usersSearch("q=".$searchString."}", $limit * 3);
				if(count($searchResults)) {
					$rawArray += $searchResults;
				}
				if(Director::isDev()) {
					$rawArray[] = $twitterClass->usersShow($member->TwitterID);
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
	 * checks if a user exists and returns an array of
	 * friend details if they exist.
	 * @return false | array
	 */
	function user_lookup($screen_name) {
		$rawArray = array();
		if($twitterClass = self::get_twitter_class()) {
			//we are retrieving more so that we can select the right ones.
			//return $twitterClass->usersShow("", $screen_name);
			$searchResults = $twitterClass->usersSearch("q=".$screen_name."}",100);
			if(count($searchResults)) {
				$rawArray += $searchResults;
			}
			if(count($rawArray)) {
				foreach($rawArray as $friend) {
					if(empty($friend["id"])){$friend["id"] = 0; }
					if(empty($friend["name"])){$friend["name"] = ""; }
					if(empty($friend["screen_name"])){$friend["screen_name"] = ""; }
					if(empty($friend["profile_image_url"])){$friend["profile_image_url"] = self::get_default_avatar(); }
					if(strtolower($friend["screen_name"]) == strtolower($screen_name)) {
						return $friend;
					}
				}
			}
		}
		return false;
	}

	/**
	 * checks if a user exists
	 * @param String $id - screen_name
	 */
	public static function is_valid_user($idOrScreenName){
		if(is_numeric($idOrScreenName) && intval($idOrScreenName) == $idOrScreenName) {
			$twitterClass = self::get_twitter_class();
			$userData = $twitterClass->usersShow($idOrScreenName);
		}
		else {
			$userData = self::user_lookup($idOrScreenName);
		}
		if(is_array($userData)) {
			if(!count($userData)) {
				$userData = null;
			}
		}
		return  $userData ? $userData : false;
	}

//======================================= STANDARD SS METHODS ===============================================



	public function __construct() {
		if(self::$consumer_secret == null || self::$consumer_key == null) {
			user_error('Cannot instigate a TwitterCallback object without a consumer secret and key', E_USER_ERROR);
		}
		parent::__construct();
	}


//======================================= CONNECT ===============================================


	/**
	 * easy access to the connection
	 *
	 */
	public function TwitterConnect() {
		if($this->isAjax()) {
			return $this->connectUser($this->Link('FinishTwitter'));
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
		Session::set('Twitter.Request.Token', serialize($token));
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
		$token = SecurityToken::inst();
		if(!$token->checkRequest($req)) return $this->httpError(400);
		$data = null;
		$access = null;
		$user = 0;
		if($req->getVars() && !$req->getVar('denied') && Session::get('Twitter.Request.Token')) {
			$consumer = self::get_zend_oauth_consumer_class();
			$token = Session::get('Twitter.Request.Token');
			if(is_string($token)) {
				$token = unserialize($token);
			}
			try {
				$access = $consumer->getAccessToken($req->getVars(), $token);
				$client = $access->getHttpClient(self::$zend_oauth_consumer_class_config["nocallback"]);
				$client->setUri('https://api.twitter.com/1/account/verify_credentials.json');
				$client->setMethod(Zend_Http_Client::GET);
				$client->setParameterGet('skip_status', 't');
				$response = $client->request();

				$data = $response->getBody();
				$data = json_decode($data);
				$user = $data->id;
				Session::set('Twitter' , array(
					'ID' => $data->id,
					'Handle' => $data->screen_name,
				));
			}
			catch(Exception $e) {
				$this->httpError(500, $e->getMessage());
				SS_Log::log($e, SS_Log::ERR);
			}
		}
		else {
			SS_Log::log("could not connect to twitter", SS_Log::NOTICE);
		}
		Session::clear('Twitter.Request.Token');
		if($data && $user && is_numeric($user) && $access) {
			$this->updateUserFromTwitterData($user, $data, $access, false);
		}
		$returnURL = $this->returnURL();
		return $this->redirect($returnURL);
	}

	/**
	 *
	 *
	 * cleans up the twitter connection
	 * Do we really need this?
	 */
	public function FinishTwitter($request) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);
		//end security check
		if($this->CurrentMember()->TwitterID) {
			$array = array(
				'handle' => $this->CurrentMember()->TwitterHandle,
				'removeLink' => $token->addToUrl($this->Link('RemoveTwitter')),
			);
			return '
				<script type="text/javascript">//<![CDATA[
					opener.TwitterResponse(' . Convert::raw2json($array) . ');
					window.close();
				//]]></script>';
		}
		else {
			return '<script type="text/javascript">window.close();</script>';
		}
	}


//======================================= LOGIN USER ===============================================

	public function loginUser() {
		$token = SecurityToken::inst();
		$callback = $this->AbsoluteLink('Login');
		$callback = $token->addToUrl($callback);
		$consumer = self::get_zend_oauth_consumer_class($callback);
		$token = $consumer->getRequestToken();
		Session::set('Twitter.Request.Token', serialize($token));
		$url = $consumer->getRedirectUrl();
		return self::curr()->redirect($url);
	}


	/**
	 * works with the login form
	 */
	public function Login(SS_HTTPRequest $req) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($req)) return $this->httpError(400);
		if($req->getVar('denied')) {
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.message', 'Login cancelled.');
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#TwitterLoginForm_LoginForm_tab');
		}
		$consumer = self::get_zend_oauth_consumer_class();
		$token = Session::get('Twitter.Request.Token');
		if(is_string($token)) {
			$token = unserialize($token);
		}
		try{
			$access = $consumer->getAccessToken($req->getVars(), $token);
			$client = $access->getHttpClient(self::$zend_oauth_consumer_class_config["nocallback"]);
			$client->setUri('https://api.twitter.com/1/account/verify_credentials.json');
			$client->setMethod(Zend_Http_Client::GET);
			$client->setParameterGet('skip_status', 't');
			$response = $client->request();
			$data = $response->getBody();
			$data = json_decode($data);
			$user = $data->id;
		}
		catch(Exception $e) {
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.message', $e->getMessage());
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.type', 'error');
			SS_Log::log($e, SS_Log::ERR);
			return $this->redirect('Security/login#TwitterLoginForm_LoginForm_tab');
		}
		if(!is_numeric($user)) {
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.message', 'Invalid user id received from Twitter.');
			Session::set('FormInfo.TwitterLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#TwitterLoginForm_LoginForm_tab');
		}
		if($data && $user && is_numeric($user) && $access) {
			$this->updateUserFromTwitterData($user, $data, $access, Session::get('SessionForms.TwitterLoginForm.Remember'));
		}
		$backURL = $this->returnURL();
		return $this->redirect($backURL);
	}

	public function remove($request = null) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);
		return $this->RemoveTwitter($request);
	}


	// ============================================== REMOVE TWITTER ========================

	public function RemoveTwitter($request) {
		//security
		//remove twitter identification
		$m = $this->CurrentMember();
		if($m) {
			$m->TwitterID = $m->TwitterSecret = $m->TwitterToken = $m->TwitterPicture = $m->TwitterName = $m->TwitterScreenName  = null;
			$m->write();
		}
		$returnURL = $this->returnURL();
		$this->redirect($returnURL);
	}

//========================================================== HELPER FUNCTIONS =====================================



	/**
	 * Saves the Twitter data to the member and logs in the member if that has not been done yet.
	 * @param Int $user - the ID of the current twitter user
	 * @param Object $twitterData - the data returned from twitter
	 * @param Object $access - access token
	 * @param Boolean $keepLoggedIn - does the user stay logged in
	 * @return Member
	 */
	protected function updateUserFromTwitterData($user, $twitterData, $access, $keepLoggedIn = false){
		if(is_array($twitterData) ) {
			$obj = new DataObject();
			foreach($twitterData as $key => $value) {
				$obj->$key = $value;
			}
			$twitterData = $obj;
		}
		//find member
		$member = DataObject::get_one('Member', '"TwitterID" = \'' . Convert::raw2sql($user) . '\'');
		if(!$member) {
			$member = Member::currentUser();
			if(!$member) {
				$member = new Member();
			}
		}
		$member->TwitterToken = $access->getParam('oauth_token');
		$member->TwitterSecret = $access->getParam('oauth_token_secret');
		$member->TwitterID = empty($user) ? 0 : $user;
		$member->TwitterPicture = empty($twitterData->profile_image_url) ? "" : $twitterData->profile_image_url;
		$member->TwitterName = empty($twitterData->name) ? "" : $twitterData->name;
		$member->TwitterScreenName = empty($twitterData->screen_name) ? "" : $twitterData->screen_name;
		if(!$twitterData->name) {
			$twitterData->name = $twitterData->screen_name;
		}
		if(!$member->FirstName && !$member->Surname && $twitterData->name) {
			$member->FirstName = $twitterData->name;
			if($twitterDataNameArray = explode(" ", $twitterData->name)) {
				if(is_array($twitterDataNameArray) && count($twitterDataNameArray) == 2) {
					$member->FirstName = $twitterDataNameArray[0];
					$member->Surname = $twitterDataNameArray[1];
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

	function meondatabase(){
		$member = Member::currentUser();
		if($member) {
			echo "<ul>";
			echo "<li>TwitterID: ".$member->TwitterID."</li>";
			echo "<li>TwitterName: ".$member->TwitterName."</li>";
			echo "<li>TwitterScreenName: ".$member->TwitterScreenName."</li>";
			echo "<li>TwitterToken: ".$member->TwitterToken."</li>";
			echo "<li>TwitterSecret: ".$member->TwitterSecret."</li>";
			echo "<li>TwitterPicture: ".$member->TwitterPicture."</li>";
			echo "</ul>";
		}
		else {
			echo "<h2>You are not logged in.</h2>";
		}
	}



}
