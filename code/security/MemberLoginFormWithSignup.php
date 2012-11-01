<?php
/**
 * Log-in form for the "member" authentication method
 * @package sapphire
 * @subpackage security
 */
class MemberLoginFormWithSignup extends LoginForm {

	/**
	 * This field is used in the "You are logged in as %s" message
	 * @var string
	 */
	public $loggedInAsField = 'FirstName';

	protected $authenticator_class = 'MemberAuthenticatorWithSignup';

	/**
	 * Constructor
	 *
	 * @param Controller $controller The parent controller, necessary to
	 *                               create the appropriate form action tag.
	 * @param string $name The method on the controller that will return this
	 *                     form object.
	 * @param FieldSet|FormField $fields All of the fields in the form - a
	 *                                   {@link FieldSet} of {@link FormField}
	 *                                   objects.
	 * @param FieldSet|FormAction $actions All of the action buttons in the
	 *                                     form - a {@link FieldSet} of
	 *                                     {@link FormAction} objects
	 * @param bool $checkCurrentUser If set to TRUE, it will be checked if a
	 *                               the user is currently logged in, and if
	 *                               so, only a logout button will be rendered
	 * @param string $authenticatorClassName Name of the authenticator class that this form uses.
	 */
	function __construct($controller, $name, $fields = null, $actions = null, $checkCurrentUser = true) {

		// This is now set on the class directly to make it easier to create subclasses
		// $this->authenticator_class = $authenticatorClassName;

		if(isset($_REQUEST['BackURL'])) {
			$backURL = $_REQUEST['BackURL'];
		}
		else {
			$backURL = Session::get('BackURL');
		}
		$member = Member::currentUser();
		$label = singleton('Member')->fieldLabel(Member::get_unique_identifier_field());

		if($checkCurrentUser && $member) {
			$fields = new FieldSet(
				new HiddenField("AuthenticationMethod", null, $this->authenticator_class, $this),
				new TextField("FirstNameSignup", "Voornaam", $member->FirstName),
				new TextField("SurnameSignup", "Achternaam", $member->Surname),
				new TextField("EmailSignup", $label, $member->Email),
				new PasswordField("PasswordSignup", _t('Member.PASSWORD', 'Password'))
			);
			$actions = new FieldSet(
				new FormAction("createorupdateaccount", _t('Member.UPDATEDETAILS', "Update your details")),
				new FormAction("logout", _t('Member.BUTTONLOGINOTHER', "Log in as someone else"))
			);
		}
		else {
			if(!$fields) {
				$fields = new FieldSet(
					new HiddenField("AuthenticationMethod", null, $this->authenticator_class, $this),
					new TextField("FirstNameSignup", "Voornaam", Session::get('SessionForms.MemberLoginFormWithSignup.FirstNameSignup'), null, $this),
					new TextField("SurnameSignup", "Achternaam", Session::get('SessionForms.MemberLoginFormWithSignup.SurnameSignup'), null, $this),
					new TextField("EmailSignup", $label, Session::get('SessionForms.MemberLoginFormWithSignup.EmailSignup'), null, $this),
					new PasswordField("PasswordSignup", _t('Member.PASSWORD', 'Password'))
				);
				if(Security::$autologin_enabled) {
					$fields->push(
						new CheckboxField(
							"RememberSignup",
							_t('Member.REMEMBERME', "Remember me next time?")
						)
					);
				}
			}
			if(!$actions) {
				$actions = new FieldSet(
					new FormAction('createorupdateaccount', _t('Member.BUTTONCREATEACCOUNT', "Create account"))
				);
			}
		}

		if(isset($backURL)) {
			$fields->push(new HiddenField('BackURL', 'BackURL', $backURL));
		}
		$requiredFields =
		parent::__construct($controller, $name, $fields, $actions);
		$validator = new RequiredFields(array("EmailSignup", "FirstNameSignup", "SurnameSignup", "PasswordSignup"));
		$validator->setForm($this);
		$this->validator = $validator;

		// Focus on the email input when the page is loaded
		// Only include this if other form JS validation is enabled
		if($this->getValidator()->getJavascriptValidationHandler() != 'none') {
			Requirements::customScript(<<<JS
				(function() {
					var el = document.getElementById("MemberLoginForm_LoginForm_EmailSignup");
					if(el && el.focus) el.focus();
				})();
JS
			);
		}
	}

	/**
	 * Get message from session
	 */
	protected function getMessageFromSession() {
		parent::getMessageFromSession();
		Session::set('MemberLoginFormWithSignup.force_message', false);
	}


	/**
	 * Login form handler method
	 *
	 * This method is called when the user clicks on "Log in"
	 *
	 * @param array $data Submitted data
	 */
	public function createorupdateaccount($data, $form) {
		$passwordOK = true;
		if(!$passwordOK) {
			Session::set('Security.Message.message',
				_t('Member.PASSWORDINVALID', "Your password is not valid.")
			);
			$loginLink = Director::absoluteURL(Security::Link("login"));
			if($backURL) {
				$loginLink .= '?BackURL=' . urlencode($backURL);
			}
			Director::redirect($loginLink . '#' . $this->FormName() .'_tab');
		}
		if($this->createOrUpdateUser($data, $form)) {
			Session::clear('SessionForms.MemberLoginForm.EmailSignup');
			Session::clear('SessionForms.MemberLoginForm.FirstNameSignup');
			Session::clear('SessionForms.MemberLoginForm.SurnameSignup');
			Session::clear('SessionForms.MemberLoginForm.RememberSignup');
			if(!isset($_REQUEST['BackURL'])) {
				if(Session::get("BackURL")) {
					$_REQUEST['BackURL'] = Session::get("BackURL");
				}
			}
			Session::clear("BackURL");
			if(isset($_REQUEST['BackURL']) && $_REQUEST['BackURL'] && Director::is_site_url($_REQUEST['BackURL'])) {
				Director::redirect($_REQUEST['BackURL']);
			}
			elseif (Security::default_login_dest()) {
				Director::redirect(Director::absoluteBaseURL() . Security::default_login_dest());
			}
			else {
				$member = Member::currentUser();
				if($member) {
					$firstname = Convert::raw2xml($member->FirstName);
					if(!empty($data['RememberSignup'])) {
						Session::set('SessionForms.MemberLoginForm.RememberSignup', '1');
						$member->logIn(true);
					}
					else {
						$member->logIn();
					}
					Session::set('Security.Message.message',
						sprintf(_t('Member.THANKYOUFORCREATINGACCOUNT', "Thank you for creating an account, %s"), $firstname)
					);
					Session::set("Security.Message.type", "good");
				}
				Director::redirectBack();
			}
		}
		else {
			Session::set('Security.Message.message',
				_t('Member.MEMBERALREADYEXISTS', "A member with this email already exists.")
			);
			Session::set("Security.Message.type", "error");
			Session::set('SessionForms.MemberLoginFormWithSignup.EmailSignupSignup', $data['EmailSignup']);
			Session::set('SessionForms.MemberLoginFormWithSignup.FirstNameSignup', $data['FirstNameSignup']);
			Session::set('SessionForms.MemberLoginFormWithSignup.SurnameSignup', $data['SurnameSignup']);
			Session::set('SessionForms.MemberLoginFormWithSignup.RememberSignup', isset($data['RememberSignup']));
			if(isset($_REQUEST['BackURL'])) {
				$backURL = $_REQUEST['BackURL'];
			}
			else {
				$backURL = null;
			}
		 	if($backURL) {
				Session::set('BackURL', $backURL);
			}
			if($badLoginURL = Session::get("BadLoginURL")) {
				Director::redirect($badLoginURL);
			}
			else {
				// Show the right tab on failed login
				$loginLink = Director::absoluteURL(Security::Link("login"));
				if($backURL) {
					$loginLink .= '?BackURL=' . urlencode($backURL);
				}
				Director::redirect($loginLink . '#' . $this->FormName() .'_tab');
			}
		}
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


  /**
   * Try to authenticate the user
   *
   * @param array Submitted data
   * @return Member Returns the member object on successful authentication
   *                or NULL on failure.
   */
	public function createOrUpdateUser($data, $form) {
		$currentUserID = intval(Member::currentUserID()) - 0;
		$existingMember = DataObject::get_one("Member", "\"".Member::get_unique_identifier_field()."\" = '".Convert::raw2sql($data[Member::get_unique_identifier_field()."Signup"])."' AND \"Member\".\"ID\" <> ".$currentUserID);
		$loginMemberAfterCreation = true;
		$loggedInUser = $member = Member::currentUser();
		if($existingMember && $loggedInUser) {
			$this->extend('authenticationFailed', $data);
			return null;
		}
		elseif($existingMember && !$loggedInUser) {
			$member = $existingMember;
		}
		elseif($loggedInUser) {
			$loginMemberAfterCreation = false;
		}
		else {
			$member = new Member();
		}
		$member->FirstName = trim(Convert::raw2sql($data["FirstNameSignup"]));
		$member->Surname = trim(Convert::raw2sql($data["SurnameSignup"]));
		$member->Email = trim(Convert::raw2sql($data["EmailSignup"]));
		$member->Password = trim(Convert::raw2sql($data["PasswordSignup"]));
		$member->write();
		if($loginMemberAfterCreation) {
			$member->LogIn(isset($data['RememberSignup']));
		}
		return $member;
	}

}




class MemberLoginFormWithSignup_Validator extends RequiredFields{

	/**
	 * Ensures member unique id stays unique and other basic stuff...
	 * @param $data = array Form Field Data
	 * @return Boolean
	 **/
	function php($data){
		$valid = parent::php($data);
		$uniqueFieldNameForMember = Member::get_unique_identifier_field();
		$uniqueFieldNameForForm = $uniqueFieldNameForMember."Signup";
		$loggedInMember = Member::currentUser();
		if(isset($data[$uniqueFieldNameForForm]) && $loggedInMember && $data[$uniqueFieldNameForForm]){
			if(!$loggedInMember->IsShopAdmin()) {
				$uniqueFieldValue = Convert::raw2sql($data[$uniqueFieldNameForForm]);
				$anotherMember = DataObject::get_one('Member',"\"$uniqueFieldNameForMember\" = '$uniqueFieldValue' AND \"Member\".\"ID\" <> ".$loggedInMember->ID);
				//can't be taken
				if($anotherMember->Password){
					$message = sprintf(
						_t("Account.ALREADYTAKEN",  '%1$s is already taken by another member. Please log in or use another %2$s'),
						$uniqueFieldValue,
						$uniqueFieldNameForForm
					);
					$this->validationError(
						$uniqueFieldNameForForm,
						$message,
						"required"
					);
					$valid = false;
				}
			}
		}
		/*
		// check password fields are the same before saving
		if(isset($data["Password"]["_Password"]) && isset($data["Password"]["_ConfirmPassword"])) {
			if($data["Password"]["_Password"] != $data["Password"]["_ConfirmPassword"]) {
				$this->validationError(
					"Password",
					_t('Account.PASSWORDSERROR', 'Passwords do not match.'),
					"required"
				);
				$valid = false;
			}
			if(!$loggedInMember && !$data["Password"]["_Password"]) {
				$this->validationError(
					"Password",
					_t('Account.SELECTPASSWORD', 'Please select a password.'),
					"required"
				);
				$valid = false;
			}
		}
		* */
		if(!$valid) {
			$this->form->sessionMessage(_t('Account.ERRORINFORM', 'We could not save your details, please check your errors below.'), "bad");
		}
		return $valid;
	}

}

