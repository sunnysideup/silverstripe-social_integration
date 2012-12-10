<?php


abstract class SocialIntegrationControllerBaseClass extends Controller {

	/**
	 * default profile pic in case none is available
	 * @var String
	 */
	private static $default_avatar = "http://placeholder.it/32x32";
		public static function get_default_avatar() {return self::$default_avatar;}
		public static function set_default_avatar($s) {self::$default_avatar = $s;}

	/**
	 * tells us if a class is a Social Integration API class (e.g. Facebook, Twiiter, etc....)
	 * @param String $className
	 * @return Boolean
	 */
	public static function is_social_integration_api_class($className) {
		if(class_exists($className)) {
			$arrayOfInterfacesItImplements = class_implements($className);
			if(is_array($arrayOfInterfacesItImplements) && in_array("SocialIntegrationAPIInterface", $arrayOfInterfacesItImplements)) {
				return true;
			}
		}
		return false;
	}


	/**
	 * one stop shop button
	 * @return Object (
	 *   IsConnected,
	 *   IsLoggedIn,
	 *   Link,
	 *   ConnectedName,
	 *   ConnectedImageURL
	 * )
	 */
	public static function get_login_button($backURL = "", $member){
		//back URL
		if(!$backURL) {
			$backURL = $_SERVER['REQUEST_URI'];
		}
		$position = strpos($backURL, "?");
		if($position) {
			$backURL = substr($backURL, 0, $position);
		}
		$backURL = str_replace("//", "/", $backURL);
		$backURL .= "#".strtolower(self::my_service_name())."_tab";
		$backURL = "?BackURL=".urlencode($backURL);
		//security
		$token = SecurityToken::inst();
		if($member->exists()) {
			//AJAX FUNCTIONALITY
			//Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
			//Requirements::javascript(THIRDPARTY_DIR . '/jquery-livequery/jquery.livequery.js');
			//Requirements::javascript('social_integration/javascript/'.strtolower(self::my_service_name()).".js");
			$method = "has".self::my_service_name();
			if($member->$method()) {
				$removeURL = Controller::join_links(self::my_class_name(), 'remove', $backURL);
				$removeURL = $token->addToUrl($removeURL);
				$nameField = self::my_service_name()."Name";
				$pictureField = self::my_service_name()."Picture";
				return new ArrayData(
					array(
						"IsConnected" => true,
						"IsLoggedIn" => Member::currentUserID() == $member->ID ? true : false,
						"Link" => $removeURL,
						"ConnectedName" => $member->$nameField,
						"ConnectedImageURL" => $member->$pictureField
					)
				);
			}
			else {
				$connectURL = Controller::join_links(self::my_class_name(), self::my_service_name().'Connect', $backURL);
				$connectURL = $token->addToUrl($connectURL);
				return new ArrayData(
					array(
						"IsConnected" => false,
						"IsLoggedIn" => Member::currentUserID() == $member->ID ? true : false,
						"Link" => $connectURL,
						"ConnectedName" => "",
						"ConnectedImageURL" => ""
					)
				);
			}
		}
		else {
			$connectURL = Controller::join_links(self::my_class_name(), self::my_service_name().'Connect', $backURL);
			$connectURL = $token->addToUrl($connectURL);
			return new ArrayData(
				array(
					"IsConnected" => false,
					"IsLoggedIn" => false,
					"Link" => $connectURL,
					"ConnectedName" => "",
					"ConnectedImageURL" => ""
				)
			);
		}
	}


	/**
	 * @param String $returnURL
	 * @return String
	 */
	public static function login_url($returnURL = "") {
		$backURLString = "";
		if($returnURL) {
			$backURLString = '?BackURL='.urlencode($returnURL);
		}
		return 'Security/login/'.$backURLString.'#'.self::my_security_form().'_LoginForm_tab';
	}

	/**
	 * Link to login form
	 * @param String $returnURL
	 * @return String
	 */
	public static function connect_url($returnURL = "", $existingMember = false) {
		$backURLString = "";
		if($returnURL) {
			$backURLString = '?BackURL='.urlencode($returnURL);
		}
		$method = self::my_service_name()."Connect";
		$className = self::my_class_name();
		return $className."/".$method."/".$backURLString;
	}


	/**
	 * redirects to login prompt, lets the user log in and returns to
	 * the returnURL specified.
	 * @param String $returnURL
	 * @return REDIRECTS!
	 */
	public static function redirect_to_login_prompt($returnURL = "") {
		$className = self::my_class_name();
		return self::curr()->redirect($className::login_url($returnURL));
	}

	/**
	 * The class being called
	 * (e.g. FacebookCallback::my_class_name should return FacebookCallback)
	 * @return String
	 */
	protected static function my_class_name(){
		return get_called_class();
	}

	/**
	 * The current ClassName without the "Callback" portion.
	 * @return String
	 */
	protected static function my_service_name(){
		return str_replace("Callback", "", self::my_class_name());
	}

	/**
	 * The name of the security form.
	 * @return String
	 */
	protected function my_security_form(){
		return self::my_class_name()."LoginForm";
	}

	/**
	 * returns Absolute URL to a link within this controller,
	 * by default it is the "Connect" link, because this controller
	 * always needs an action.
	 * @return String
	 */
	public function Title() {
		return self::my_service_name();
	}

	/**
	 * returns Absolute URL to a link within this controller,
	 * by default it is the "Connect" link, because this controller
	 * always needs an action.
	 * @return String
	 */
	public function AbsoluteLink($action = "") {
		if(!$action) {
			$action = $this->serviceName."Connect";
		}
		return Director::absoluteURL($this->Link($action));
	}

	/**
	 * returns relative URL to a link within this controller,
	 * by default it is the "Connect" link, because this controller
	 * always needs an action.
	 * @return String
	 */
	public function Link($action = "") {
		if(!$action) {
			$action = $this->serviceName."Connect";
		}
		$className = self::my_class_name();
		return self::join_links($className, $action);
	}

	public static function is_valid_user($screen_name){
		return true;
	}


//========================================== HELPER METHODS =====================================

	public function __construct() {
		parent::__construct();
	}

	/**
	 * you need to add an action
	 */
	public function index() {
		if(Director::isDev()) {
			return $this->renderWith(array("SocialIntegrationControllerBaseClass"));
		}
		else {
			$this->httpError(403);
		}
	}

	/**
	 * works out best Return URL
	 * @param Boolean $hasAbsoluteBaseURL - should it include the Base URL (e.g. http://www.mysite.com)
	 * @return String
	 */
	protected function returnURL($hasAbsoluteBaseURL = false){
		$returnURL = "/Security/login/";
		if(!empty($this->requestParams["BackURL"])) {
			$returnURL = $this->requestParams["BackURL"];
		}
		elseif(Session::get("BackURL")) {
			$returnURL = Session::get("BackURL");
			Session::set("BackURL", "");
			Session::clear("BackURL");
		}
		$returnURL = urldecode($returnURL);
		if($hasAbsoluteBaseURL) {
			$returnURL = Director::absoluteBaseURL().$returnURL;
		}
		return $returnURL;
	}

	public function Tests(){
		if(Director::isDev()) {
			$dos = new DataObjectSet();
			$tests = array(
				"connect" => "connect to this service",
				"login" => "do a traditional log in to this service",
				"meonservice" => "what details does this service know about me right now, retrieve from service",
				"meondatabase" => "what data is stored with current member",
				"updates" => "show my latest updates",
				"friends" => "get a list of my friends (or the equivalent (e.g. followers))",
				"friendssearch" => "????",
				"isvaliduser" => "????",
				"sendmessage" => "test sending a message",
				"remove" => "remove this service from my account"
			);
			foreach($tests as $test => $description) {
				$dos->push(
					new ArrayData(
						array(
							"Link" => "/".$this->Link("test/".$test."/"),
							"Name" => "<strong>".$test."</strong>".": ".$description
						)
					)
				);
			}
			return $dos;
		}
		return null;
	}


	function menondatabase(){
		//to be completed
	}

	public function test($request){
		$className = self::my_class_name();
		$testType = $request->param("ID");
		$IDField = self::my_service_name()."ID";
		echo "<h2>TEST: $testType</h2><pre>";
		switch($testType) {
			case "connect":
				return $this->connectUser("/$className/test/meonservice/");
				break;
			case "login":
				print_r($className::redirect_to_login_prompt("/$className/test/meonservice/"));
				break;
			case "meonservice":
				$outcome = $className::get_current_user();
				if(!$outcome) {
					echo "NOT CONNECTED";
				}
				else {
					print_r($outcome);
				}
				break;
			case "meondatabase":
				$this->meondatabase();
				break;
			case "updates":
				print_r($className::get_updates());
				break;
			case "friends":
				print_r($className::get_list_of_friends());
				break;
			case "friendssearch":
				print_r($className::get_list_of_friends(7, "john"));
				break;
			case "isvaliduser":
				$member = Member::currentUser();
				if($member) {
					if(self::my_service_name() == "Twitter") {
						$IDField = "TwitterScreenName";
					}
					$id = $member->$IDField;
				}
				else {
					$id = "";
				}
				print_r($className::is_valid_user($id));
				break;
			case "sendmessage":
				$member = Member::currentUser();
				$otherVariables = array();
				if($member) {
					if(self::my_service_name() == "Facebook") {
						$otherVariables = array(
							"name" => "test name",
							"caption" =>  "test caption",
							"description" => "test description"
						);
					}
					$fieldName = self::my_service_name()."ID";
					$outcome = $className::send_message(
						$member->$fieldName,
						$message = "message goes here",
						$link = "http://www.google.com",
						$otherVariables
					);
				}
				else {
					$outcome = "You must be logged in to send a message.";
				}
				echo "
					<h1>Sending message to yourself</h1>
					<p>OUTCOME: <pre>...$outcome</pre>";
				break;
			case "remove":
				$method = "Remove".self::my_service_name();
				echo $this->$method(null);
				break;
			default:
				echo "no test to run";
		}
		echo "</pre><hr /><a href=\"/$className\">back to test index</a>";
	}

}
