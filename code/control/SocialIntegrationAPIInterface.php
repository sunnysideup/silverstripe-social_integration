<?php



interface SocialIntegrationAPIInterface {


//======================================= STATIC METHODS ===============================================

	/**
	 * Link to login form
	 * @param String $returnURL
	 * @return String
	 */
	public static function login_url($returnURL = "");


	/**
	 * Link to connect
	 * @param String $returnURL
	 * @return String
	 */
	public static function connect_url($returnURL = "", $existingMember = false);

	/**
	 * redirects to login prompt, lets the user log in and returns to
	 * the returnURL specified.
	 * @param String $returnURL
	 * @return REDIRECTS!
	 */
	public static function redirect_to_login_prompt($returnURL = "");


	/**
	 * returns all the data of the currently logged in / connected user.
	 *
	 * @return Array | Null
	 */
	public static function get_current_user();

	/**
	 * gets a list of friends
	 *
	 *
	 */
	public static function get_list_of_friends();

	/**
	 * make sure to return TRUE as response if the message is sent
	 * successfully
	 * Sends a message from the current user to someone else in the networkd
	 * @param Int $userID - Facebook user id.
	 * @param String $message - Message you are sending
	 * @param String $link - Link to send with message
	 * @return Boolean - return TRUE as success
	 */
	public static function send_message($to, $message, $link = "", $otherVariables = array());

	/**
	 * Checks if the id provided is a valid member of the class.
	 * @return Boolean
	 */
	public static function is_valid_user($id);

	public function test($request);

	public function meondatabase();

}
