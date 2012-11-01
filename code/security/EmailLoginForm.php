<?php
/**
 * Log-in form for the "member" authentication method
 * @package sapphire
 * @subpackage security
 */
class EmailLoginForm extends MemberLoginForm {

	protected $authenticator_class = 'EmailAuthenticator';

}
