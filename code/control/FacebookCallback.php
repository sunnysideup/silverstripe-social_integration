<?php
/**
 * A controller that connects with Facebook, using the Facebook PHP SDK
 *
 * USEFUL LINKS:
 * https://developers.facebook.com/docs/reference/login/extended-permissions/
 *
 */

if(!defined("SS_FACEBOOK_API_PATH")) {
	define("SS_FACEBOOK_API_PATH", str_replace("/code/control", "",dirname(__FILE__))."/thirdparty/facebook/src/");
}
require_once SS_FACEBOOK_API_PATH . 'facebook.php';

class FacebookCallback extends SocialIntegrationControllerBaseClass implements SocialIntegrationAPIInterface {

//======================================= AVAILABLE METHODS ===============================================

	/**
	 * Standard SS variable
	 * @var Array
	 */
	public static $allowed_actions = array(
		'FacebookConnect',
		'Connect',
		'Login',
		'FinishFacebook',
		'remove',
		'test'
	);


//======================================= CONFIGURATION STATIC ===============================================

	/**
	 * get it from developer.facebook.com
	 * @var String
	 */
	protected static $facebook_id = null;
		public static function set_facebook_id($i) {self::$facebook_id = $i;}
		public static function get_facebook_id() {return self::$facebook_id;}

	/**
	 * get it from developer.facebook.com
	 * @var String
	 */
	protected static $facebook_secret = null;
		public static function set_facebook_secret($s) {self::$facebook_secret = $s;}
		public static function get_facebook_secret() {return self::$facebook_secret;}

	/**
	 * use email as a back-up
	 * for checking if the user already exists.
	 * @var Boolean
	 */
	protected static $email_fallback = true;
		public static function get_email_fallback() {return self::$email_fallback;}
		public static function set_email_fallback($val) {self::$email_fallback = (bool)$val;}


	/**
	 * @see: https://developers.facebook.com/docs/authentication/permissions/
	 * @var Array
	 */
	protected static $permissions = false;
		public static function add_permission($s) {if(!self::$permissions) {self::$permissions = array(); } if(!in_array($s, self::$permissions)) {self::$permissions[] = $s;}}
		public static function set_permissions($a) {self::$permissions = $a;}
		public static function get_permissions() {return self::$permissions;}


//======================================= CONFIGURATION NON-STATIC ===============================================




//======================================= THIRD-PARTY CONNECTION ===============================================

	/**
	 *
	 *
	 * @var facebook Class
	 */
	protected static $facebook_sdk_class = null;

	/**
	 * holds an instance of the FB class
	 * @param Boolean $getEvenWithoutCurrentMember
	 * @return Facebook
	 */
	protected static function get_facebook_sdk_class($getEvenWithoutCurrentMember = false){
		if(!self::$facebook_id || !self::$facebook_secret) {
			user_error("You must set the following variables: Facebook::facebook_id AND Facebook::facebook_secret");
		}
		if(!self::$facebook_sdk_class) {
			$member = Member::currentUser();
			if(($member && $member->FacebookID) || $getEvenWithoutCurrentMember) {
				self::$facebook_sdk_class = new Facebook(
					array(
						'appId' => self::$facebook_id,
						'secret' => self::$facebook_secret,
						'cookie' => true
					)
				);
			}
		}
		return self::$facebook_sdk_class;
	}


//======================================= STATIC METHODS ===============================================

	/**
	 * returns the currently logged in FB user
	 * @return Object | Null
	 */
	public static function get_current_user() {
		$user = null;
		$data = null;
		$facebook = self::get_facebook_sdk_class();
		if($facebook){
			$user = $facebook->getUser();
			if($user) {
				try {
					$data = $facebook->api('/me');
					if(isset($data->error)) {
						$data = null;
						SS_Log::log($data->error->message, SS_Log::NOTICE);
					}
				}
				catch(FacebookApiException $e) {
					$data = null;
					SS_Log::log($e, SS_Log::NOTICE);
				}
				try {
					$picture = $facebook->api('/me/?fields=picture');
					if(isset($picture["picture"]["data"]["url"])) {
						$data["picture"] = $picture["picture"]["data"]["url"];
					}
					if(isset($data->error)) {
						SS_Log::log(print_r($data->error, 1).$data->error->message, SS_Log::NOTICE);
					}
				}
				catch(FacebookApiException $e) {
					SS_Log::log($e, SS_Log::NOTICE);
				}
			}
		}
		return $user ? $data : null;
	}

	/*
	/**
	 * returns true if the message is sent successfully
	 * @param Int | Member | String $to
	 * @param String $message
	 * @param String $link - link to send with message
	 * @param Array $otherVariables - other variables used in message.
			 - $redirect_uri = "",
			 - $RedirectURL = "",
			 - $name = "",
			 - $caption = "",
			 - $description = "",
			 - $pictureURL = "",
			 - $Subject = "",
			 - $actions = array()
	 * @param String $senderEmail - link to send with message

	 * @see:  https://developers.facebook.com/docs/reference/php/facebook-api/
	 * @see   http://facebook.stackoverflow.com/questions/2943297/how-send-message-facebook-friend-through-graph-api-using-accessstoken
	 *
	 * @return EmailLink (Dialogue Feed)
	 */
	public static function send_message(
		$to = "me",
		$message,
		$link = "",
		$otherVariables = array()
	){
		//FACEBOOK
		if($to instanceOf Member) {
			$to = $to->FacebookUsername;
		}
		$facebook = self::get_facebook_sdk_class();
		if($facebook) {
			$user = $facebook->getUser();
			//get email data that does not go to GRAPH:
			if(isset($otherVariables["senderEmail"])) {
				$senderEmail = $otherVariables["senderEmail"];
				unset($otherVariables["senderEmail"]);
			}
			elseif($sender = Member::currentUser()) {
				$senderEmail = $sender->Email;
			}

			//start hack
			$message = trim(strip_tags(stripslashes($message)));
			//end hack
			$postArray = array(
				'message' => $message,
				'link' => $link,
			);
			if(count($otherVariables)) {
				foreach($otherVariables as $key => $value) {
					$postArray[$key] = $value;
				}
			}
			if($user) {
				if(empty($otherVariables["Subject"])) {
					$subject = substr($message, 0, 30);
				}
				else {
					$subject = $otherVariables["Subject"];
				}
				//------------- SEND EMAIL TO START DIALOGUE ---
				//BUILD LINK
				$emailLink = "https://www.facebook.com/dialog/feed?"
						."to=".$to."&amp;"
						."app_id=".self::get_facebook_id()."&amp;"
						."link=".urlencode($link)."&amp;"
						."message=".urlencode($message)."&amp;";
				//FROM
				if(isset($otherVariables["redirect_uri"])) {
					$emailLink .= "redirect_uri=".urlencode(Director::absoluteURL("/").$otherVariables["redirect_uri"])."&amp;";
				}
				elseif(isset($otherVariables["RedirectURL"])) {
					$emailLink .= "redirect_uri=".urlencode(Director::absoluteURL("/").$otherVariables["RedirectURL"])."&amp;";
				}
				else {
					$emailLink .= "redirect_uri=".urlencode(Director::absoluteURL("/"))."&amp;";
				}
				if(isset($otherVariables["pictureURL"])) {
					$emailLink .= "picture=".urlencode(Director::absoluteURL("/").$otherVariables["pictureURL"])."&amp;";
				}
				if(isset($otherVariables["description"])) {
					$emailLink .= "description=".urlencode($otherVariables["description"])."&amp;";
				}
				elseif($message) {
					$emailLink .= "description=".urlencode($message)."&amp;";
				}
				if(isset($otherVariables["name"])) {
					$emailLink .= "name=".urlencode($otherVariables["name"])."&amp;";
				}
				if(isset($otherVariables["caption"])) {
					$emailLink .= "caption=".urlencode($otherVariables["caption"])."&amp;";
				}
				elseif(isset($otherVariables["Subject"])) {
					$emailLink .= "caption=".urlencode($otherVariables["Subject"])."&amp;";
				}
				$from = Email::getAdminEmail();
				//TO
				//SUBJECT
				$subject = _t("FacebookCallback.ACTION_REQUIRED", "Action required for").": ".$subject;
				//BODY
				$body =
					_t("FacebookCallback.PLEASE_CLICK_ON_THE_LINK", " Please click on the link ")
					." <a href=\"".$emailLink."\" target=\"_blank\">"._t("FacebookCallback.OPEN_FACEBOOK", "open facebook")."</a> ".
					_t("FacebookCallback.TO_SEND_A_MESSAGE_TO_FRIEND", "to send a message to your friend. ").
					_t("FacebookCallback.DIRECT_LINK", " You can also send the link directly to your friend: ").$link;
				//BCC
				$bcc = Email::getAdminEmail();
				//SEND
				$email = new Email(
					$from,
					$senderEmail,
					$subject,
					$body
				);
				$email->send();
				// We have a user ID, so probably a logged in user.
				// If not, we'll get an exception, which we handle below.
				if(1 == 2) {
					if($to instanceOf Member) {
						$to = $to->FacebookUsername;
					}
					try {
						$ret_obj = $facebook->api('/'.$to.'/feed', 'POST', $postArray);
						//SS_Log::log($ret_obj, SS_Log::NOTICE);
						return $body;
					}
					catch(FacebookApiException $e) {
						// If the user is logged out, you can have a
						// user ID even though the access token is invalid.
						// In this case, we'll get an exception, so we'll
						// just ask the user to login again here.
						SS_Log::log($user."---".$e->getType()."---".$e->getMessage()."---".$to."---".$message."---".$link."---".$otherVariables."---".print_r($user, 1).print_r(Member::currentUser(), 1), SS_Log::NOTICE);
					}
				}
			}
			else {
				SS_Log::log("tried to send a message from facebook without being logging in...", SS_Log::NOTICE);
			}
		}
		return false;
	}

	/**
	 *
	 *
	 * @return Array (array("id" => ..., "name" => ...., "picture" => ...))
	 */
	public static function get_list_of_friends($limit = 12, $searchString = ""){
		$returnObject = array();
		$facebook = self::get_facebook_sdk_class();
		if($facebook){
			if($user = $facebook->getUser()) {
				$fullList = $facebook->api('/me/friends?fields=id,name,picture');
				$count = 0;
				if(Director::isDev()) {
					$me = self::get_current_user();
					$returnObject[$count]["id"] = $me["id"];
					$returnObject[$count]["name"] = $me["name"];
					$returnObject[$count]["picture"] = $me["picture"];
					$count++;
				}
				if(isset($fullList["data"])) {
					$limitCount = 0;
					foreach($fullList["data"] as $friend) {
						if(!$searchString || stripos("-".$friend["name"], $searchString ) ) {
							$returnObject[$count]["id"] = $friend["id"];
							$returnObject[$count]["name"] = $friend["name"];
							if(isset($friend["picture"]["data"]["url"])) {
								$returnObject[$count]["picture"] = $friend["picture"]["data"]["url"];
							}
							elseif(isset($friend["picture"])) {
								$returnObject[$count]["picture"] = $friend["picture"];
							}
							$count++;
						}
						if($count >= $limit) {
							break;
						}
					}
				}
			}
		}
		return $returnObject;
	}

	public static function is_valid_user($id){
		return true;
	}

	public static function get_updates($lastNumber = 12){
		$returnObject = array();
		$facebook = self::get_facebook_sdk_class();
		if($facebook){
			if($user = $facebook->getUser()) {
				return $facebook->api(
					$path = "/me/statuses",
					$method = "GET",
					$params = array(
						"limit" => 100,
						"since" => 2005,
					)
				);
			}
		}
	}
//======================================= STANDARD SS METHODS ===============================================

	/**
	 * magical PHP method
	 */
	public function __construct() {
		if(self::$facebook_secret == null || self::$facebook_id == null) {
			user_error('Cannot instigate a FacebookCallback object without an application secret and id', E_USER_ERROR);
		}
		parent::__construct();
	}



//==================================== CONNECT ==============================================

	/**
	 * easy access to the connection
	 *
	 */
	public function FacebookConnect() {
		if($this->isAjax()) {
			return $this->connectUser($this->Link('FinishFacebook'));
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
		$facebook = self::get_facebook_sdk_class($getEvenWithoutCurrentMember = true);
		$user = $facebook->getUser();
		$data = self::get_current_user();
		$token = SecurityToken::inst();
		$returnTo = urlencode($returnTo);
		$returnTo = $token->addToUrl($returnTo);
		$callback = $this->AbsoluteLink('Connect?BackURL=' . $returnTo);
		$callback = $token->addToUrl($callback);
		if(self::$permissions) {
			$extra += array(
				'scope' => implode(', ', self::$permissions)
			);
		}
		if($user && empty($extra)) {
			return self::curr()->redirect($callback);
		}
		else {
			return self::curr()->redirect(
				$facebook->getLoginUrl(
					array(
						'redirect_uri' => $callback
					)
					+ $extra
				)
			);
		}
	}

	/**
	 * Connects the current user.
	 * completes connecting process
	 * @param SS_HTTPRequest $reg
	 */
	public function Connect(SS_HTTPRequest $req) {
		//security
		$token = SecurityToken::inst();
		if(!$token->checkRequest($req)) return $this->httpError(400);

		$data = null;

		if($req->getVars() && !$req->getVar('error')) {
			$facebook = self::get_facebook_sdk_class($getEvenWithoutCurrentMember = true);
			$user = $facebook->getUser();
			$data = self::get_current_user();
		}
		if($data && $user && is_numeric($user)) {
			$this->updateUserFromFacebookData($user, $data, false);
		}
		$returnURL = $this->returnURL();
		return $this->redirect($returnURL);
	}

	/**
	 * finish the login from facebook
	 * @param HTTPRequest $request
	 * @return String Javascript
	 */
	public function FinishFacebook($request) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);

		$member = Member::currentUser();
		if($member && $member->FacebookID) {
			return '<script type="text/javascript">//<![CDATA[
			opener.FacebookResponse(' . \Convert::raw2json(array(
				'name' => $member->FacebookName,
				'pages' => $member->getFacebookPages(),
				'removeLink' => $token->addToUrl($this->Link('RemoveFacebook')),
			)) . ');
			window.close();
			//]]></script>';
		}
		else {
			return '<script type="text/javascript">window.close();</script>';
		}
	}






//==================================== LOGIN ==============================================

	public function loginUser(Array $extra = array(), $return = false) {
		$facebook = self::get_facebook_sdk_class($getEvenWithoutCurrentMember = true);
		$user = $facebook->getUser();
		$data = self::get_current_user();
		$token = SecurityToken::inst();
		if($return) {
			$return = $token->addToUrl($return);
			$return = urlencode($return);
		}
		$callback = $this->AbsoluteLink('Login' . ($return ? '?ret=' . $return : ''));
		$callback = $token->addToUrl($callback);
		if(self::$permissions) {
			$perms = self::$permissions;
		}
		else {
			$perms = array();
		}
		if($perms) {
			$extra += array(
				'scope' => implode(', ', $perms)
			);
		}

		if($user && empty($extra)) {
			return self::curr()->redirect($callback);
		}
		else {
			return self::curr()->redirect($facebook->getLoginUrl(array(
				'redirect_uri' => $callback,
			) + $extra));
		}
	}

	public function Login(SS_HTTPRequest $req) {
		//security
		$token = SecurityToken::inst();
		if(!$token->checkRequest($req)) return $this->httpError(400);

		//denied!
		if($req->getVar('denied') || $req->getVar('error_reason') == 'user_denied') {
			Session::set('FormInfo.FacebookLoginForm_LoginForm.formError.message', 'Login cancelled.');
			Session::set('FormInfo.FacebookLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#FacebookLoginForm_LoginForm_tab');
		}
		$facebook = self::get_facebook_sdk_class($getEvenWithoutCurrentMember = true);
		$user = $facebook->getUser();
		$data = self::get_current_user();
		$error = "";
		if(!$user) {
			$error = 'Login cancelled.';
			return $this->redirect('Security/login#FacebookLoginForm_LoginForm_tab');
		}
		if($error) {
			Session::set('FormInfo.FacebookLoginForm_LoginForm.formError.message', 'Login error: ' . $data->error->message);
			Session::set('FormInfo.FacebookLoginForm_LoginForm.formError.type', 'error');
			return $this->redirect('Security/login#FacebookLoginForm_LoginForm_tab');
		}
		$this->updateUserFromFacebookData($user, $data, $keepLoggedIn = Session::get('SessionForms.FacebookLoginForm.Remember'));
		Session::clear('SessionForms.FacebookLoginForm.Remember');
		return $this->redirect($this->returnURL());
	}



//========================================== REMOVE  =====================================

	/**
	 * alias for RemoveFaceBook
	 */
	public function remove($request = null) {
		$token = SecurityToken::inst();
		if(!$token->checkRequest($request)) return $this->httpError(400);
		return $this->RemoveFacebook($request);
	}

	/**
	 * remove connection to facebook
	 * TO DO: remove links
	 * TO DO: FB session
	 * @param HTTPRequest
	 */
	public function RemoveFacebook($request) {
		//security check
		//
		$m = $this->CurrentMember();
		if($m) {
			$m->FacebookID = 0;
			$m->FacebookURL = "";
			$m->FacebookPicture = "";
			$m->FacebookName = "";
			$m->FacebookEmail = "";
			$m->FacebookFirstName = "";
			$m->FacebookMiddleName = "";
			$m->FacebookLastName = "";
			$m->FacebookUsername = "";
			$m->write();
		}
		$facebook = new Facebook(array(
			'appId' => self::$facebook_id,
			'secret' => self::$facebook_secret
		));
		//do we need to encode URL ????
		$url = $facebook->getLogoutUrl(array("next" => $this->returnURL(true)));
		$this->redirect($url);
	}

//========================================== HELPER METHODS =====================================




	/**
	 * Saves the FB data to the member and logs in the member if that has not been done yet.
	 * @param Int $user - the ID of the current twitter user
	 * @param Object $facebookData - the data returned from FB
	 * @param Boolean $keepLoggedIn - does the user stay logged in
	 * @return Member
	 */
	protected function updateUserFromFacebookData($user, $facebookData, $keepLoggedIn = false){
		//clean up data
		if(is_array($facebookData) ) {
			$obj = new DataObject();
			foreach($facebookData as $key => $value) {
				$obj->$key = $value;
			}
			$facebookData = $obj;
		}

		//find member
		$member = null;
		if($user) {
			$member = DataObject::get_one('Member', '"FacebookID" = \'' . Convert::raw2sql($user) . '\'');
		}
		if(!$member) {
			$member = Member::currentUser();
			if(!$member) {
				$member = new Member();
			}
		}
		//check if anyone else uses the email:
		if($facebookEmail = Convert::raw2sql($facebookData->email)) {
			$memberID = intval($member->ID)-0;
			$existingMember = DataObject::get_one(
				'Member',
				'("Email" = \'' . $facebookEmail . '\' OR "FacebookEmail" = \''.$facebookEmail.'\') AND "Member"."ID" <> '.$memberID
			);
			if($existingMember) {
				$member = $existingMember;
			}
		}
		$member->FacebookID = empty($user) ? 0 : $user;
		$member->FacebookURL = empty($facebookData->link) ? "" : $facebookData->link;
		$member->FacebookPicture = empty($facebookData->picture) ? "" : $facebookData->picture;
		$member->FacebookName = empty($facebookData->name) ? "" : $facebookData->name;;
		$member->FacebookEmail = empty($facebookData->email) ? "" : $facebookData->email;
		$member->FacebookFirstName = empty($facebookData->first_name) ? "" : $facebookData->first_name;
		$member->FacebookMiddleName = empty($facebookData->middle_name) ? "" : $facebookData->middle_name;
		$member->FacebookLastName = empty($facebookData->last_name) ? "" : $facebookData->last_name;
		$member->FacebookUsername = empty($facebookData->username) ? "" : $facebookData->username;
		if(!$member->FirstName) {
			$member->FirstName = $member->FacebookFirstName;
		}
		if(!$member->Surname) {
			$member->Surname = $member->FacebookLastName;
		}
		if(!empty($facebookData->email)) {
			if(!$member->Email) {
				$memberID = intval($member->ID)-0;
				$anotherMemberWithThisEmail = DataObject::get_one(
					'Member',
					'("Email" = \'' . $facebookData->email . '\' OR "FacebookEmail" = \''.$facebookData->email.'\') AND "Member"."ID" <> '.$memberID
				);
				if(!$anotherMemberWithThisEmail) {
					$member->Email = $facebookData->email;
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


	/**
	 * retrieve the various identities this user has on Facebook
	 *
	 * @return Array
	 */
	public function getFacebookPages() {
		$facebook = self::get_facebook_sdk_class();
		$user = $facebook->getUser();
		if($user) {
			$pages = array(
				'me/feed' => 'Personal Page'
			);
			try {
				$resp = $facebook->api('/me/accounts', 'GET');
				if(isset($resp->data)) {
					foreach($resp->data as $app) {
						if($app->category != 'Application') {
							$pages[$app->id] = $app->name . ' <small>(' . $app->category . ')</small>';
						}
					}
				}
			}
			catch(FacebookApiException $e) {
				SS_Log::log($e, SS_Log::ERR);
			}
			return $pages;
		}
		return array();
	}



	function meondatabase(){
		$member = Member::currentUser();
		if($member) {
			echo "<ul>";
			echo "<li>FacebookID: ".$member->FacebookID."</li>";
			echo "<li>FacebookName: ".$member->FacebookName."</li>";
			echo "<li>FacebookEmail: ".$member->FacebookEmail."</li>";
			echo "<li>FacebookFirstName: ".$member->FacebookFirstName."</li>";
			echo "<li>FacebookMiddleName: ".$member->FacebookMiddleName."</li>";
			echo "<li>FacebookLastName: ".$member->FacebookLastName."</li>";
			echo "<li>FacebookUsername: ".$member->FacebookUsername."</li>";
			echo "<li>FacebookPicture: <img src=\"".$member->FacebookPicture."\" alt=\"\" /></li>";
			echo "<li>FacebookURL: <a href=\"".$member->FacebookURL."\" />click ".$member->FacebookURL."</a></li>";
			echo "</ul>";
		}
		else {
			echo "<h2>You are not logged in.</h2>";
		}
	}
}
