<?php
namespace app\models;
use app\core\Model;

class UserModel
{
    /*public function checkUser()
        {
            if(!isset($_COOKIE['ovl_session'])) return false;
            $db = $this->db;
            $session = $this->db->Get('ovl_sessions', 'user_name', 'session_id', $_COOKIE['ovl_session']);
            if($session->num_rows < 1)
            {
                unset($_COOKIE['ovl_session']);
                setcookie('ovl_session', null, -1, '/');
                return false;
            }
            $session = $session->fetch_assoc();
            $account = $db->Get('ovl_accounts', 'user_name', 'user_name', $session['user_name']);
            if($account->num_rows < 1)
            {
                unset($_COOKIE['ovl_session']);
                setcookie('ovl_session', null, -1, '/');
                return false;
            }
            $account = $account->fetch_assoc();
            if($account['user_name'] == $session['user_name'] && time() < $session['expire']) return $account['user_name'];
            else
            {
                unset($_COOKIE['ovl_session']);
                setcookie('ovl_session', null, -1, '/');
                return false;
            }
        }*/
    /*public function addSession($uname, $when = true)
    {
        $rand = GenerateWord(20);
        if($when == true) $when = time()+3600;
        else $when = time()+(86400*30);
        $this->db->Add('ovl_sessions',"$uname, $rand, $when");
        setcookie('ovl_session', $rand, $when, '/', 'ovl.io', true, true);
    }*/

    public static function addUser($username, $name, $pwd, $email)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        return Model::object()->query("INSERT INTO accounts (userName, displayName, userPwd, userEmail, ip) VALUES ('$username', '$name', '$pwd', '$email', '$ip')");
    }

    public static function getData($who, $fields)
    {
        return Model::object()->query("SELECT $fields FROM users WHERE email = '$who'");
    }

    public static function setUser($who, $field, $value)
    {
        return Model::object()->query("UPDATE users SET $field = '$value' WHERE email = '$who'");
    }
}