<?php

class EmailCallback extends SocialIntegrationControllerBaseClass implements SocialIntegrationAPIInterface {


	/**
	 * make sure to return TRUE as response if the message is sent
	 * successfully
	 * Sends a message from the current user to someone else in the networkd
	 * @param Int | String | Member $to -
	 * @param String $message - Message you are sending
	 * @param String $link - Link to send with message - NOT USED IN EMAIL
	 * @param Array - other variables that we include
	 * @return Boolean - return TRUE as success
	 */
	public static function send_message($to, $message, $link = "", $otherVariables = array()) {

		//FROM
		if(!empty($otherVariables["From"])) {
			$from = $otherVariables["From"];
		}
		else {
			$from = Email::getAdminEmail();
		}

		//TO
		if($to instanceOf Member) {
			$to = $to->Email;
		}
		//SUBJECT
		if(!empty($otherVariables["Subject"])) {
			$subject = $otherVariables["Subject"];
		}
		else {
			$subject = substr($message, 0, 30);
		}

		//BODY
		$body = $message;

		//CC
		if(!empty($otherVariables["CC"])) {
			$cc = $otherVariables["CC"];
		}
		else {
			$cc = "";
		}

		//BCC
		$bcc = Email::getAdminEmail();

		//SEND EMAIL
		$email = new Email(
			$from,
			$to,
			$subject,
			$body,
			$bounceHandlerURL = null,
			$cc,
			$bcc
		);
		return $email->send();
	}

	public static function get_list_of_friends($limit = 12, $searchString = "") {
		return array();
	}

	/**
	 *
	 * return Object | Null
	 */
	public static function get_current_user() {
		return Member::currentUser();
	}

	public static function is_valid_user($id){
		return filter_var( $id, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Link to login form
	 * @param String $returnURL
	 * @return String
	 */
	public static function connect_url($returnURL = "", $existingMember = false) {
		$backURLString = "";
		if($returnURL) {
			$backURLString = 'BackURL='.urlencode($returnURL);
		}
		if($existingMember) {
			$tab = 'EmailLoginForm_LoginForm_tab';
		}
		else {
			$tab = "MemberLoginFormWithSignup_LoginForm_tab";
		}
		//$backLink = urlencode($returnURL);
		//return "Security/login/".$backLink."#".$tab;
		return "Security/login/?email=1&amp;".$backURLString."#".$tab;
	}

	public function meondatabase(){
		print_r(Member::currentUser());
	}


}
