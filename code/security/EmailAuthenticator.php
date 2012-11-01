<?php
/**
 * Authenticator for the default "member" method
 *
 * @author Markus Lanthaler <markus@silverstripe.com>
 * @package sapphire
 * @subpackage security
 */
class EmailAuthenticator extends MemberAuthenticator {

  private static $authenticators = array('EmailAuthenticator');

	private static $default_authenticator = 'EmailAuthenticator';

	/**
	 * Method that creates the login form for this authentication method
	 *
	 * @param Controller The parent controller, necessary to create the
	 *                   appropriate form action tag
	 * @return Form Returns the login form to use with this authentication
	 *              method
	 */
	public static function get_login_form(Controller $controller) {
		return Object::create("EmailLoginForm", $controller, "LoginForm");
	}


  /**
   * Method to authenticate an user
   *
   * @param array $RAW_data Raw data to authenticate the user
   * @param Form $form Optional: If passed, better error messages can be
   *                             produced by using
   *                             {@link Form::sessionMessage()}
   * @return bool|Member Returns FALSE if authentication fails, otherwise
   *                     the member object
   */
	public static function authenticate($RAW_data, Form $form = null) {
		return parent::authenticate($RAW_data, $form);
	}

  /**
   * @return string
   */
  public static function get_default_authenticator() {
    return "EmailAuthenticator";
  }

}

