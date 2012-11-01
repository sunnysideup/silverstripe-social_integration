<?php

class FacebookAuthenticator extends Authenticator {

	public static function get_name() {
		if(Member::currentUser()) {
			return 'Facebook';
		}
		else {
			return 'Facebook';
		}
	}

	public static function get_login_form(Controller $controller) {
		return new FacebookLoginForm(
			$controller,
			'LoginForm'
		);
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
		return singleton('FacebookCallback')->loginUser();
	}
}
