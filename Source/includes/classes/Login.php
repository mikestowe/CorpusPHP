<?

/**
* Basic login class for use on front and back side of projects
*
* @todo General Cleanup and Generification
* @package CorpusPHP
*/
class Login {

	/**
	* Object that holds the user object, brought over from the session
	* @var User
	*/
	public $user;

	function __construct() {
		if( $_SESSION['corpus']['user'] instanceof User ) {
			$this->user =& $_SESSION['corpus']['user'];
		}else{
			$this->user = false;
		}
	}

	function IsLoggedIn() { return ($this->user instanceof User); }

	function VerifyLoggedIn() {
		global $_ms, $_nh, $_meta;
		if(!$this->IsLoggedIn()) {
			$_ms->add(GENERIC_LOGIN_ERROR, true);
			$this->Logout();
			$_nh->SetSnapshot('application/index.php', 'login');
			redirect('login.php');
		}
	}

	function Logout() { unset($_SESSION['corpus']['user']); }

	function attempt($user,$pass) {
		
		$uinfo = db::fetch("Select user_id, access From users WHERE email = '".db::input(trim($user))."' AND PASSWORD(password) = PASSWORD('".db::input(trim($pass))."')", db::ROW);
		
		if($uinfo['user_id'] > 0) {
			$dbo_name = ucwords($uinfo['access']);
			if( class_exists($dbo_name) && is_subclass_of($dbo_name, 'User') ) {
				$user = new $dbo_name( $uinfo['user_id'] );
			}else{
				$user = new User( $uinfo['user_id'] );
			}
			$_SESSION['corpus']['user'] = $user;
			return true;
		}
		$this->Logout();
		return false;
	}
}