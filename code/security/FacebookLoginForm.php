<?php

class FacebookLoginForm extends LoginForm {

	protected $authenticator_class = 'FacebookAuthenticator';

	public function __construct($controller, $method, $fields = null, $actions = null, $checkCurrentUser = true) {
		if(isset($_REQUEST['BackURL'])) {
			$backURL = $_REQUEST['BackURL'];
		} else {
			$backURL = Session::get('BackURL');
		}
		$member = Member::currentUser();
		$removeField = "";
		if($member) {
			$removeField = new ReadonlyField('FacebookButton', 'Facebook', $member->getFacebookButton());
			$removeField->dontEscape = true;
		}
		if($checkCurrentUser && $member && Member::logged_in_session_exists()) {
			$fields = new FieldSet(
				new HiddenField("AuthenticationMethod", null, $this->authenticator_class, $this)
			);
			if(!$member->FacebookID) {
				$fields->push(new LiteralField("FacebookAddExplanation", "<p class=\"message good\">You are already logged in but you can connect your Facebook account to your account with us.</p> "));
			}
			$actions = new FieldSet(
				new FormAction("logout", _t('Member.BUTTONLOGINOTHER', "Log in as someone else"))
			);

		}
		else {
			if(!$fields) {
				$fields = new FieldSet(
					new HiddenField("AuthenticationMethod", null, $this->authenticator_class, $this)
				);
				if(Security::$autologin_enabled) {
					$fields->push(new CheckboxField(
						"Remember",
						_t('Member.REMEMBERME', 'Remember me next time?'),
						Session::get('SessionForms.FacebookLoginForm.Remember'),
						$this
					));
				}
			}
			if(!$actions) {
				$actions = new FieldSet(
					new FormAction('dologin', 'Sign in with Facebook')
				);
			}
		}
		if(!empty($backURL)) {
			$fields->push(new HiddenField('BackURL', 'BackURL', $backURL));
		}
		if($removeField) {
			$actions->push($removeField);
		}
		parent::__construct(
			$controller,
			$method,
			$fields,
			$actions
		);
	}

	protected function getMessageFromSession() {
		parent::getMessageFromSession();
		if(($member = Member::currentUser()) && !$this->message) {
			$this->message = sprintf(_t('Member.LOGGEDINAS'), $member->FirstName);
		}
	}

	protected function dologin($data) {
		if(!empty($data['BackURL'])) {
			Session::set('BackURL', $data['BackURL']);
		}
		Session::set('SessionForms.FacebookLoginForm.Remember', !empty($data['Remember']));
		return FacebookAuthenticator::authenticate($data, $this);
	}

	/**
	 * Log out form handler method
	 *
	 * This method is called when the user clicks on "logout" on the form
	 * created when the parameter <i>$checkCurrentUser</i> of the
	 * {@link __construct constructor} was set to TRUE and the user was
	 * currently logged in.
	 */
	public function logout() {
		$s = new Security();
		$s->logout();
	}

	//
	public function test(){
		FacebookCallback::test();
	}

}
