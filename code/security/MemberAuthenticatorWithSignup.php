<?php
/**
 * Authenticator for the default "member" method
 *
 * @author Markus Lanthaler <markus@silverstripe.com>
 * @package sapphire
 * @subpackage security
 */
class MemberAuthenticatorWithSignup extends Authenticator {


  /**
   * Method that creates the login form for this authentication method
   *
   * @param Controller The parent controller, necessary to create the
   *                   appropriate form action tag
   * @return Form Returns the login form to use with this authentication
   *              method
   */
  public static function get_login_form(Controller $controller) {
    return Object::create("MemberLoginFormWithSignup", $controller, "LoginForm");
  }



	/**
	 * Get the name of the authentication method
	 *
	 * @return string Returns the name of the authentication method.
	 */
	public static function get_name() {
		if(Member::currentUser()) {
			return _t('MemberAuthenticator.UPDATE', "Update your account details");
		}
		else {
			return _t('MemberAuthenticator.SIGNUP', "Signup with Email");
		}
	}


	public static function authenticate($RAW_data, Form $form = null) {
		return true;
	}

}

