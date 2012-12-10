<?php

class LinkedinAuthenticator extends Authenticator {
	public static function get_name() {
		return 'Linkedin';
	}

	public static function get_login_form(Controller $controller) {
		return new LinkedinLoginForm(
			$controller,
			'LoginForm'
		);
	}

	public static function authenticate($RAW_data, Form $form = null) {
		return singleton('LinkedinCallback')->loginUser();
	}
}
