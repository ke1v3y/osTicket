<?php
require(INCLUDE_DIR.'class.ostsession.php');
require(INCLUDE_DIR.'class.usersession.php');


abstract class AuthenticatedUser {
    //Authorization key returned by the backend used to authorize the user
    private $authkey;

    // Get basic information
    abstract function getId();
    abstract function getUsername();
    abstract function getRole();

    //Backend used to authenticate the user
    abstract function getAuthBackend();

    //Authentication key
    function setAuthKey($key) {
        $this->authkey = $key;
    }

    function getAuthKey() {
        return $this->authkey;
    }

    // logOut the user
    function logOut() {

        if ($bk = $this->getAuthBackend())
            return $bk->signOut($this);

        return false;
    }
}

interface AuthDirectorySearch {
    /**
     * Indicates if the backend can be used to search for user information.
     * Lookup is performed to find user information based on a unique
     * identifier.
     */
    function lookup($id);

    /**
     * Indicates if the backend supports searching for usernames. This is
     * distinct from information lookup in that lookup is intended to lookup
     * information based on a unique identifier
     */
    function search($query);
}

/**
 * Authentication backend
 *
 * Authentication provides the basis of abstracting the link between the
 * login page with a username and password and the staff member,
 * administrator, or client using the system.
 *
 * The system works by allowing the AUTH_BACKENDS setting from
 * ost-config.php to determine the list of authentication backends or
 * providers and also specify the order they should be evaluated in.
 *
 * The authentication backend should define a authenticate() method which
 * receives a username and optional password. If the authentication
 * succeeds, an instance deriving from <User> should be returned.
 */
abstract class AuthenticationBackend {
    static protected $registry = array();
    static $name;
    static $id;


    /* static */
    static function register($class) {
        if (is_string($class) && class_exists($class))
            $class = new $class();

        if (!is_object($class)
                || !($class instanceof AuthenticationBackend))
            return false;

        return static::_register($class);
    }

    static function _register($class) {
        // XXX: Raise error if $class::id is already in the registry
        static::$registry[$class::$id] = $class;
    }

    static function allRegistered() {
        return static::$registry;
    }

    static function getBackend($id) {

        if ($id
                && ($backends = static::allRegistered())
                && isset($backends[$id]))
            return $backends[$id];
    }

    static function process($username, $password=null, &$errors) {

        if (!$username)
            return false;

        $backends =  static::getAllowedBackends($username);
        foreach (static::allRegistered() as $bk) {
            if ($backends //Allowed backends
                    && $bk->supportsAuthentication()
                    && !in_array($bk::$id, $backends))
                // User cannot be authenticated against this backend
                continue;

            // All backends are queried here, even if they don't support
            // authentication so that extensions like lockouts and audits
            // can be supported.
            $result = $bk->authenticate($username, $password);

            if ($result instanceof AuthenticatedUser
                    && (static::login($result, $bk)))
                return $result;
            // TODO: Handle permission denied, for instance
            elseif ($result instanceof AccessDenied) {
                $errors['err'] = $result->reason;
                break;
            }
        }

        $info = array('username'=>$username, 'password'=>$password);
        Signal::send('auth.login.failed', null, $info);
    }

    function processSignOn(&$errors) {

        foreach (static::allRegistered() as $bk) {
            // All backends are queried here, even if they don't support
            // authentication so that extensions like lockouts and audits
            // can be supported.
            $result = $bk->signOn();
            if ($result instanceof AuthenticatedUser) {
                //Perform further Object specific checks and the actual login
                if (!static::login($result, $bk))
                    continue;

                return $result;
            }
            // TODO: Handle permission denied, for instance
            elseif ($result instanceof AccessDenied) {
                $errors['err'] = $result->reason;
                break;
            }
        }
    }

    static function searchUsers($query) {
        $users = array();
        foreach (static::$registry as $bk) {
            if ($bk instanceof AuthDirectorySearch) {
                $users += $bk->search($query);
            }
        }
        return $users;
    }

    /**
     * Fetches the friendly name of the backend
     */
    function getName() {
        return static::$name;
    }

    /**
     * Indicates if the backed supports authentication. Useful if the
     * backend is used for logging or lockout only
     */
    function supportsAuthentication() {
        return true;
    }

    /**
     * Indicates if the backend supports changing a user's password. This
     * would be done in two fashions. Either the currently-logged in user
     * want to change its own password or a user requests to have their
     * password reset. This requires an administrative privilege which this
     * backend might not possess, so it's defined in supportsPasswordReset()
     */
    function supportsPasswordChange() {
        return false;
    }

    function supportsPasswordReset() {
        return false;
    }

    function signOn() {
        return null;
    }

    protected function validate($auth) {
        return null;
    }

    abstract function authenticate($username, $password);
    abstract function login($user, $bk);
    abstract static function getUser(); //Validates  authenticated users.
    abstract function getAllowedBackends($userid);
    abstract protected function getAuthKey($user);
    abstract static function signOut($user);
}

class RemoteAuthenticationBackend {
    var $create_unknown_user = false;
}

abstract class StaffAuthenticationBackend  extends AuthenticationBackend {

    static private $_registry = array();

    static function _register($class) {
        static::$_registry[$class::$id] = $class;
    }

    static function allRegistered() {
        return array_merge(self::$_registry, parent::allRegistered());
    }

    function isBackendAllowed($staff, $bk) {

        if (!($backends=self::getAllowedBackends($staff->getId())))
            return true;  //No restrictions

        return in_array($bk::$id, array_map('strtolower', $backends));
    }

    function getAllowedBackends($userid) {

        $backends =array();
        //XXX: Only one backend can be specified at the moment.
        $sql = 'SELECT backend FROM '.STAFF_TABLE
              .' WHERE backend IS NOT NULL ';
        if (is_numeric($userid))
            $sql.= ' AND staff_id='.db_input($userid);
        else {
            $sql.= ' AND (username='.db_input($userid) .' OR email='.db_input($userid).')';
        }

        if (($res=db_query($sql)) && db_num_rows($res))
            $backends[] = db_result($res);

        return array_filter($backends);
    }

    function login($staff, $bk) {
        global $ost;

        if (!$bk || !($staff instanceof Staff))
            return false;

        // Ensure staff is allowed for realz to be authenticated via the backend.
        if (!static::isBackendAllowed($staff, $bk)
            || !($authkey=$bk->getAuthKey($staff)))
            return false;

        //Log debug info.
        $ost->logDebug('Staff login',
            sprintf("%s logged in [%s], via %s", $staff->getUserName(),
                $_SERVER['REMOTE_ADDR'], get_class($bk))); //Debug.

        $sql='UPDATE '.STAFF_TABLE.' SET lastlogin=NOW() '
            .' WHERE staff_id='.db_input($staff->getId());
        db_query($sql);

        //Tag the authkey.
        $authkey = $bk::$id.':'.$authkey;

        //Now set session crap and lets roll baby!
        $authsession = &$_SESSION['_auth']['staff'];

        $authsession = array(); //clear.
        $authsession['id'] = $staff->getId();
        $authsession['key'] =  $authkey;

        $staff->setAuthKey($authkey);
        $staff->refreshSession(); //set the hash.

        $_SESSION['TZ_OFFSET'] = $staff->getTZoffset();
        $_SESSION['TZ_DST'] = $staff->observeDaylight();

        //Regenerate session id.
        $sid = session_id(); //Current id
        session_regenerate_id(true);
        // Destroy old session ID - needed for PHP version < 5.1.0
        // DELME: remove when we move to php 5.3 as min. requirement.
        if(($session=$ost->getSession()) && is_object($session)
                && $sid!=session_id())
            $session->destroy($sid);

        Signal::send('auth.login.succeeded', $staff);

        $staff->cancelResetTokens();

        return true;
    }

    /* Base signOut
     *
     * Backend should extend the signout and perform any additional signout
     * it requires.
     */

    static function signOut($staff) {
        global $ost;

        $_SESSION['_auth']['staff'] = array();
        $ost->logDebug('Staff logout',
                sprintf("%s logged out [%s]",
                    $staff->getUserName(),
                    $_SERVER['REMOTE_ADDR'])); //Debug.

        Signal::send('auth.logout', $staff);
    }

    static function getUser() {

        if (!isset($_SESSION['_auth']['staff'])
                || !$_SESSION['_auth']['staff']['key'])
            return null;

        list($id, $auth) = explode(':', $_SESSION['_auth']['staff']['key']);

        if (!($bk=static::getBackend($id)) //get the backend
                || !$bk->supportsAuthentication() //Make sure it can authenticate
                || !($staff = $bk->validate($auth)) //Get AuthicatedUser
                || !($staff instanceof Staff)
                || $staff->getId() != $_SESSION['_auth']['staff']['id'] // check ID
                )
            return null;

        $staff->setAuthKey($_SESSION['_auth']['staff']['key']);


        return $staff;
    }

    protected function getAuthKey($staff) {
        return null;
    }
}

abstract class UserAuthenticationBackend  extends AuthenticationBackend {

    static private $_registry = array();

    static function _register($class) {
        static::$_registry[$class::$id] = $class;
    }

    static function allRegistered() {
        return array_merge(self::$_registry, parent::allRegistered());
    }

    function getAllowedBackends($userid) {
        // White listing backends for specific user not supported.
        return array();
    }

    function login($user, $bk) {
        global $ost;

        if (!$user || !$bk
                || !$bk::$id //Must have ID
                || !($authkey = $bk->getAuthKey($user)))
            return false;

        //Tag the authkey.
        $authkey = $bk::$id.':'.$authkey;

        //Set the session goodies
        $authsession = &$_SESSION['_auth']['user'];

        $authsession = array(); //clear.
        $authsession['id'] = $user->getId();
        $authsession['key'] = $authkey;
        $_SESSION['TZ_OFFSET'] = $ost->getConfig()->getTZoffset();
        $_SESSION['TZ_DST'] = $ost->getConfig()->observeDaylightSaving();

        //The backend used decides the format of the auth key.
        // XXX: encrypt to hide the bk??
        $user->setAuthKey($authkey);

        $user->refreshSession(); //set the hash.

        //Log login info...
        $msg=sprintf('%s (%s) logged in [%s]',
                $user->getUserName(), $user->getId(), $_SERVER['REMOTE_ADDR']);
        $ost->logDebug('User login', $msg);

        //Regenerate session ID.
        $sid=session_id(); //Current session id.
        session_regenerate_id(TRUE); //get new ID.
        if(($session=$ost->getSession()) && is_object($session) && $sid!=session_id())
            $session->destroy($sid);

        return true;
    }

    static function signOut($user) {
        global $ost;

        $_SESSION['_auth']['user'] = array();
        $ost->logDebug('User logout',
                sprintf("%s logged out [%s]",
                    $user->getUserName(), $_SERVER['REMOTE_ADDR']));
    }

    protected function getAuthKey($user) {
        return null;
    }

    static function getUser() {

        if (!isset($_SESSION['_auth']['user'])
                || !$_SESSION['_auth']['user']['key'])
            return null;

        list($id, $auth) = explode(':', $_SESSION['_auth']['user']['key']);

        if (!($bk=static::getBackend($id)) //get the backend
                || !$bk->supportsAuthentication() //Make sure it can authenticate
                || !($user=$bk->validate($auth)) //Get AuthicatedUser
                || !($user instanceof AuthenticatedUser) // Make sure it user
                || $user->getId() != $_SESSION['_auth']['user']['id'] // check ID
                )
            return null;

        $user->setAuthKey($_SESSION['_auth']['user']['key']);

        return $user;
    }
}

/**
 * This will be an exception in later versions of PHP
 */
class AccessDenied {
    function AccessDenied() {
        call_user_func_array(array($this, '__construct'), func_get_args());
    }
    function __construct($reason) {
        $this->reason = $reason;
    }
}

/**
 * Simple authentication backend which will lock the login form after a
 * configurable number of attempts
 */
abstract class AuthStrikeBackend extends AuthenticationBackend {

    function authenticate($username, $password=null) {
        return static::authStrike($username, $password);
    }

    function signOn() {
        return static::authStrike('Unknown');
    }

    static function signOut($user) {
        return false;
    }


    function login($user, $bk) {
        return false;
    }

    static function getUser() {
        return null;
    }

    function supportsAuthentication() {
        return false;
    }

    function getAllowedBackends($userid) {
        return array();
    }

    function getAuthKey($user) {
        return null;
    }

    abstract function  authStrike($username, $password=null);
}

/*
 * Backend to monitor staff's failed login attempts
 */
class StaffAuthStrikeBackend extends  AuthStrikeBackend {

    function authstrike($username, $password=null) {
        global $ost;

        $cfg = $ost->getConfig();

        $authsession = &$_SESSION['_auth']['staff'];

        if($authsession['laststrike']) {
            if((time()-$authsession['laststrike'])<$cfg->getStaffLoginTimeout()) {
                $authsession['laststrike'] = time(); //reset timer.
                return new AccessDenied('Max. failed login attempts reached');
            } else { //Timeout is over.
                //Reset the counter for next round of attempts after the timeout.
                $authsession['laststrike']=null;
                $authsession['strikes']=0;
            }
        }

        $authsession['strikes']+=1;
        if($authsession['strikes']>$cfg->getStaffMaxLogins()) {
            $authsession['laststrike']=time();
            $alert='Excessive login attempts by a staff member?'."\n".
                   'Username: '.$username."\n"
                   .'IP: '.$_SERVER['REMOTE_ADDR']."\n"
                   .'TIME: '.date('M j, Y, g:i a T')."\n\n"
                   .'Attempts #'.$authsession['strikes']."\n"
                   .'Timeout: '.($cfg->getStaffLoginTimeout()/60)." minutes \n\n";
            $ost->logWarning('Excessive login attempts ('.$username.')', $alert,
                    $cfg->alertONLoginError());
            return new AccessDenied('Forgot your login info? Contact Admin.');
        //Log every other failed login attempt as a warning.
        } elseif($authsession['strikes']%2==0) {
            $alert='Username: '.$username."\n"
                    .'IP: '.$_SERVER['REMOTE_ADDR']."\n"
                    .'TIME: '.date('M j, Y, g:i a T')."\n\n"
                    .'Attempts #'.$authsession['strikes'];
            $ost->logWarning('Failed staff login attempt ('.$username.')', $alert, false);
        }
    }
}
StaffAuthenticationBackend::register(StaffAuthStrikeBackend);

/*
 * Backend to monitor user's failed login attempts
 */
class UserAuthStrikeBackend extends  AuthStrikeBackend {

    function authstrike($username, $password=null) {
        global $ost;

        $cfg = $ost->getConfig();

        $authsession = &$_SESSION['_auth']['user'];

        //Check time for last max failed login attempt strike.
        if($authsession['laststrike']) {
            if((time()-$authsession['laststrike'])<$cfg->getClientLoginTimeout()) {
                $authsession['laststrike'] = time(); //renew the strike.
                return new AccessDenied('You\'ve reached maximum failed login attempts allowed.');
            } else { //Timeout is over.
                //Reset the counter for next round of attempts after the timeout.
                $authsession['laststrike'] = null;
                $authsession['strikes'] = 0;
            }
        }

        $authsession['strikes']+=1;
        if($authsession['strikes']>$cfg->getClientMaxLogins()) {
            $authsession['laststrike'] = time();
            $alert='Excessive login attempts by a user.'."\n".
                    'Login: '.$username.': '.$password."\n".
                    'IP: '.$_SERVER['REMOTE_ADDR']."\n".'Time:'.date('M j, Y, g:i a T')."\n\n".
                    'Attempts #'.$authsession['strikes'];
            $ost->logError('Excessive login attempts (user)', $alert, ($cfg->alertONLoginError()));
            return new AccessDenied('Access Denied');
        } elseif($authsession['strikes']%2==0) { //Log every other failed login attempt as a warning.
            $alert='Login: '.$username.': '.$password."\n".'IP: '.$_SERVER['REMOTE_ADDR'].
                   "\n".'TIME: '.date('M j, Y, g:i a T')."\n\n".'Attempts #'.$authsession['strikes'];
            $ost->logWarning('Failed login attempt (user)', $alert);
        }

    }
}
UserAuthenticationBackend::register(UserAuthStrikeBackend);


class osTicketAuthentication extends StaffAuthenticationBackend {
    static $name = "Local Authentication";
    static $id = "local";

    function authenticate($username, $password) {
        if (($user = new StaffSession($username)) && $user->getId() &&
                $user->check_passwd($password)) {

            //update last login && password reset stuff.
            $sql='UPDATE '.STAFF_TABLE.' SET lastlogin=NOW() ';
            if($user->isPasswdResetDue() && !$user->isAdmin())
                $sql.=',change_passwd=1';
            $sql.=' WHERE staff_id='.db_input($user->getId());
            db_query($sql);

            return $user;
        }
    }

    protected function getAuthKey($staff) {

        if(!($staff instanceof Staff))
            return null;

        return $staff->getUsername(); //FIXME:
    }

    protected function validate($authkey) {

        if (($staff = new StaffSession($authkey)) && $staff->getId())
            return $staff;
    }

}
StaffAuthenticationBackend::register(osTicketAuthentication);

/*
 * AuthToken Authentication Backend
 *
 * Provides auto-login facility for end users with valid link
 *
 * Ticket used to loggin is tracked durring the session this is
 * important in the future when auto-logins will be
 * limited to single ticket view.
 */
class AuthTokenAuthentication extends UserAuthenticationBackend {
    static $name = "Auth Token Authentication";
    static $id = "authtoken";


    function signOn() {

        $user = null;
        if ($_GET['auth']) {
            if (($u = TicketUser::lookupByToken($_GET['auth'])))
                $user = new ClientSession($u);
        }
        // Support old ticket based tokens.
        elseif ($_GET['t'] && $_GET['e'] && $_GET['a']) {
            if (($ticket = Ticket::lookupByNumber($_GET['t'], $_GET['e']))
                    // Using old ticket auth code algo - hardcoded here because it
                    // will be removed in ticket class in the upcoming rewrite
                    && !strcasecmp($_GET['a'], md5($ticket->getId() .  $_GET['e'] . SECRET_SALT))
                    && ($owner = $ticket->getOwner()))
                $user = new ClientSession($owner);
        }

        return $user;
    }


    protected function getAuthKey($user) {

        if (!$this->supportsAuthentication() || !$user)
            return null;

        //Generate authkey based the type of ticket user
        // It's required to validate users going forward.
        $authkey = sprintf('%s%dt%dh%s',  //XXX: Placeholder
                    ($user->isOwner() ? 'o':'c'),
                    $user->getId(),
                    $user->getTicketId(),
                    md5($user->getId().$this->id));

        return $authkey;
    }

    protected function validate($authkey) {

        $regex = '/^(?P<type>\w{1})(?P<id>\d+)t(?P<tid>\d+)h(?P<hash>.*)$/i';
        $matches = array();
        if (!preg_match($regex, $authkey, $matches))
            return false;

        $user = null;
        switch ($matches['type']) {
            case 'c': //Collaborator
                $criteria = array( 'userId' => $matches['id'],
                        'ticketId' => $matches['tid']);
                if (($c = Collaborator::lookup($criteria))
                        && ($c->getTicketId() == $matches['tid']))
                    $user = new ClientSession($c);
                break;
            case 'o': //Ticket owner
                if (($ticket = Ticket::lookup($matches['tid']))
                        && ($o = $ticket->getOwner())
                        && ($o->getId() == $matches['id']))
                    $user = new ClientSession($o);
                break;
        }

        //Make sure the authkey matches.
        if (!$user || strcmp($this->getAuthKey($user), $authkey))
            return null;


        return $user;
    }

    function authenticate($username, $password) {
        return false;
    }

}
UserAuthenticationBackend::register(AuthTokenAuthentication);

?>
