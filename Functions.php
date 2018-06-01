<?php

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;

class Functions
{
    private $db;

    /**
     * Инициализирует класс
     */
    function __construct()
    {
        $config = new Config();

        $this->db = new \mysqli(
            $config->mysql_credentials['host'],
            $config->mysql_credentials['user'],
            $config->mysql_credentials['password'],
            $config->mysql_credentials['database']
        );
        $this->db->query('SET NAMES utf8');
    }

    /**
     * Уничтожает класс
     */
    function __destruct()
    {
        $this->db->close();
    }

    /**
     * Возвращает список администраторов
     *
     * @param array $admins
     *
     * @return array
     */
    public function FetchAdmins($admins = [])
    {
        $result = $this->db->query('SELECT id FROM admins')->fetch_row();
        if(count($result))
        {
            foreach($result as $admin)
            {
                $admins[] = (int) $admin;
            }
        }
        return $admins;
    }

    /**
     * Возвращает запрос от клиента
     *
     * @return \Longman\TelegramBot\Entities\Update | false
     */
    public function GetUpdate()
    {
        try
        {
            $config = new Config();
            $post = json_decode(Request::getInput(), true);
            return new Longman\TelegramBot\Entities\Update($post, $config->bot_username);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
        return false;
    }

    /**
     * Проверяет, есть ли у пользователя авторизация
     *
     * @return bool
     */
    public function CheckUserAuth()
    {
        $update = $this->GetUpdate();
        if($update->getMessage() == null) return true;

        $user_id = $update->getMessage()->getFrom()->getId();
        $username = $update->getMessage()->getFrom()->getUsername();

        $check = $this->db->query("SELECT id FROM users_allowed WHERE user_id = '$user_id'");
        if($check->num_rows > 0)
        {
            $check->free_result();
            $this->db->query("UPDATE users_allowed SET username = '$username' WHERE user_id = '$user_id'");
            return true;
        }
        else
        {
            $check = $this->db->query("SELECT id FROM users_allowed WHERE username = '$username'");
            if($check->num_rows > 0)
            {
                $check->free_result();
                $this->db->query("UPDATE users_allowed SET user_id = '$user_id' WHERE username = '$username'");
                return true;
            }
            else
            {
                $token = trim($update->getMessage()->getText(true));
                $token = ($update->getMessage()->getCommand() == 'start' && strlen($token) == 10) ? $token : '';

                $check->free_result();
                $check = $this->db->query("SELECT id FROM users_allowed WHERE token = '$token'");
                if($check->num_rows > 0)
                {
                    $check->free_result();
                    $this->db->query("UPDATE users_allowed SET user_id = '$user_id' WHERE token = '$token'");
                    $this->db->query("UPDATE users_allowed SET token = '' WHERE user_id = '$user_id'");
                    $this->db->query("UPDATE users_allowed SET username = '$username' WHERE user_id = '$user_id'");
                    try
                    {
                        $data = [
                            'chat_id' => $update->getMessage()->getChat()->getId(),
                            'text' => 'Вы прошли авторизацию. Список команд: /help'
                        ];

                        Request::sendMessage($data);
                    }
                    catch (TelegramException $e)
                    {
                        //echo $e;
                    }
                    return false;
                }
                else return false;
            }
        }
    }

    /**
     * Добавляет авторизацию пользователя
     *
     * @param string $username
     *
     * @return int
     */
    public function AddAuth($username)
    {
        $this->db->query("INSERT INTO users_allowed (username) VALUES('$username')");
        return $this->db->affected_rows;
    }

    /**
     * Удаляет авторизацию пользователя
     *
     * @param string $username
     *
     * @return int
     */
    public function RevokeAuth($username)
    {
        $this->db->query("DELETE FROM users_allowed WHERE username = '$username'");
        error_log($this->db->affected_rows);
        return $this->db->affected_rows;
    }

    /**
     * Добавляет авторизационный токен
     *
     * @param string $token
     *
     * @return int
     */
    public function AddToken($token)
    {
        $this->db->query("INSERT INTO users_allowed (token) VALUES('$token')");
        return $this->db->affected_rows;
    }

    /**
     * Удаляет авторизационный токен
     *
     * @param string $token
     *
     * @return int
     */
    public function RevokeToken($token)
    {
        $this->db->query("DELETE FROM users_allowed WHERE token = '$token'");
        error_log($this->db->affected_rows);
        return $this->db->affected_rows;
    }
}