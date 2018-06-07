<?php

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;

class Functions
{
    private $db;

    /**
     * Инициализирует класс
     *
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
     *
     */
    function __destruct()
    {
        $this->db->close();
    }

    /**
     * Возвращает рандомную строку
     *
     * @param int $max
     *
     * @return string
     */
    function GenerateString($max = 10)
    {
        $char = "qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
        $size = strlen($char)-1;
        $key = null;
        while($max--) { $key.=$char[rand(0,$size)]; }
        return $key;
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
        $result = $this->db->query('SELECT user_id FROM admins')->fetch_all();
        if(count($result))
        {
            foreach($result as $line)
            {
                $admins[] = (int) $line[0];
            }
        }
        return $admins;
    }

    /**
     * Возвращает список менеджеров
     *
     * @param array $managers
     *
     * @return array
     */
    public function FetchManagers($managers = [])
    {
        $result = $this->db->query('SELECT user_id FROM managers')->fetch_all();
        if(count($result))
        {
            foreach($result as $line)
            {
                $managers[] = (int) $line[0];
            }
        }
        return $managers;
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

        $ins = $this->db->query("SELECT username FROM user WHERE id = '$user_id'");
        if($ins->num_rows < 1)
        {
            $who = $update->getMessage()->getFrom();
            $first_name = $who->getFirstName();
            $last_name = $who->getLastName();
            $code = $who->getLanguageCode();
            $this->db->query("INSERT INTO user VALUES('$user_id', '0', '$first_name', '$last_name', '$username', '$code', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )");
        }

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
                $token = ($update->getMessage()->getCommand() == 'start' && strlen($token) == 10) ? $token : false;

                if(!$token) return false;
                $check->free_result();
                $check = $this->db->query("SELECT id FROM users_allowed WHERE token = '$token'");
                if($check->num_rows > 0 && strlen($token) > 1)
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
        return $this->db->affected_rows;
    }

    /**
     * Добавляет авторизационный токен
     *
     * @return string | false
     */
    public function AddToken()
    {
        $token = $this->GenerateString();
        $this->db->query("INSERT INTO users_allowed (token) VALUES('$token')");
        return ($this->db->affected_rows > 0) ? $token : false;
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
        return $this->db->affected_rows;
    }

    /**
     * Добавляет администратора
     *
     * @param int $user_id
     *
     * @return int
     */
    public function AddAdmin($user_id)
    {
        $this->db->query("INSERT INTO admins (user_id) VALUES('$user_id')");
        return $this->db->affected_rows;
    }

    /**
     * Отображает информацию о пользователе
     *
     * @param int $user_id
     *
     * @return array
     */
    public function GetUserInfo($user_id)
    {
        return $this->db->query("SELECT * FROM user WHERE id = '$user_id'")->fetch_assoc();
    }

    /**
     * Удаляет администратора
     *
     * @param int $user_id
     *
     * @return int
     */
    public function RevokeAdmin($user_id)
    {
        $this->db->query("DELETE FROM admins WHERE user_id = '$user_id'");
        return $this->db->affected_rows;
    }

    /**
     * Узнает ID чата
     *
     * @param int $user_id
     *
     * @return string
     */
    public function GetChatID($user_id)
    {
        return $this->db->query("SELECT chat_id FROM user_chat WHERE user_id = '$user_id'")->fetch_row();
    }

    /**
     * Добавляет менеджера
     *
     * @param int $user_id
     *
     * @return int
     */
    public function AddManager($user_id)
    {
        $this->db->query("INSERT INTO managers (user_id) VALUES('$user_id')");
        return $this->db->affected_rows;
    }

    /**
     * Удаляет менеджера
     *
     * @param int $user_id
     *
     * @return int
     */
    public function RevokeManager($user_id)
    {
        $this->db->query("DELETE FROM managers WHERE user_id = '$user_id'");
        return $this->db->affected_rows;
    }

    /**
     * Проверяет, является ли пользователь менеджером
     *
     * @param int $user_id
     *
     * @return int
     */
    public function IsManager($user_id)
    {
        return (in_array($user_id, $this->FetchManagers())) ? true : false;
    }

    /**
     * Возвращает список подчиненных менеджера
     *
     * @param int $manager_id
     *
     * @return array $employees
     */
    public function FetchManagerEmployees($manager_id)
    {
        $employees = [];
        $result = $this->db->query("SELECT user_id FROM employees WHERE manager_id = '$manager_id'")->fetch_all();
        if(count($result))
        {
            foreach($result as $line)
            {
                $employees[] = (int) $line[0];
            }
        }
        return $employees;
    }

    /**
     * Проверяет введенную дату на валидность
     *
     * @param string $date
     * @param string $format
     *
     * @return bool
     */
    function validate_timestamp($date, $format = 'Y-m-d H:i')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    /**
     * Добавляет задачу в БД
     *
     * @param string $name
     * @param string $desc
     * @param int $user_id
     * @param int $manager_id
     * @param string $dead_date
     *
     * @return int
     */
    public function AddTask($name, $desc, $user_id, $manager_id, $dead_date)
    {
        $this->db->query("INSERT INTO tasks (task_name,task_desc,user_id,manager_id,dead_date) VALUES('$name', '$desc', '$user_id', '$manager_id', '$dead_date')");
        return $this->db->affected_rows;
    }

    /**
     * Возвращает список заданий, поставленных менеджером
     *
     * @param int $manager_id
     * @param bool $active
     *
     * @return array
     */
    public function GetManagerTasks($manager_id, $active = true)
    {
        $tasks = [];
        $status = ($active) ? 1 : 0;
        $result = $this->db->query("SELECT * FROM tasks WHERE manager_id = '$manager_id' AND status = '$status'");
        if($result->num_rows > 0)
        {
            while($line = $result->fetch_assoc())
            {
                $tasks[] = $line;
            }
        }
        return $tasks;
    }

    /**
     * Возвращает список заданий, поставленных менеджером
     *
     * @param int $user_id
     * @param bool $active
     *
     * @return array
     */
    public function GetUserTasks($user_id, $active = true)
    {
        $tasks = [];
        $status = ($active) ? 1 : 0;
        $result = $this->db->query("SELECT * FROM tasks WHERE user_id = '$user_id' AND status = '$status'");
        if($result->num_rows > 0)
        {
            while($line = $result->fetch_assoc())
            {
                $tasks[] = $line;
            }
        }
        return $tasks;
    }

    /**
     * Возвращает ID файла, прикрепленного к задаче
     *
     * @param int $task_id
     *
     * @return int
     */
    public function GetTaskFile($task_id)
    {
        return $this->db->query("SELECT files FROM tasks WHERE id = '$task_id'")->fetch_assoc()['files'];
    }

    /**
     * Устанавливает новый ID файла задачи
     *
     * @param int $task_id
     * @param int $file_id
     *
     * @return int
     */
    public function SetTaskFile($task_id, $file_id)
    {
        $this->db->query("UPDATE tasks SET files = '$file_id' WHERE id = '$task_id'");
        return $this->db->affected_rows;
    }

    /**
     * Устанавливает новый ID файла задачи
     *
     * @param int $task_id
     * @param int $time
     * @param bool $total
     *
     * @return int
     */
    public function SetTaskTime($task_id, $time = 0)
    {
        if($time == 0)
        {
            $start_time = $this->GetTaskInfo($task_id);
            $new_total = time() - $start_time['start_time'];
            $this->db->query("UPDATE tasks SET total_time = total_time + '$new_total' WHERE id = '$task_id'");
        }
        $this->db->query("UPDATE tasks SET start_time = '$time' WHERE id = '$task_id'");
        return $this->db->affected_rows;
    }

    /**
     * Возвращает информацию о задаче
     *
     * @param int $task_id
     *
     * @return array
     */
    public function GetTaskInfo($task_id)
    {
        return $this->db->query("SELECT * FROM tasks WHERE id = '$task_id'")->fetch_assoc();
    }

    /**
     * Закрывает задачу
     *
     * @param int $task_id
     *
     * @return int
     */
    public function CloseTask($task_id)
    {
        $now = new \DateTime();
        $now = $now->format("Y-m-d H:i:s");
        $this->db->query("UPDATE tasks SET status = 0, finish_date = '$now' WHERE id = '$task_id'");
        return $this->db->affected_rows;
    }

    /**
     * Возвращает время, полученное из секунд
     *
     * @param int $value
     *
     * @return string
     */
    public function MakeTime($value)
    {
        $seconds = intval($value%60);
        $total_minutes = intval($value/60);
        $minutes = $total_minutes%60;
        $hours = intval($total_minutes/60);
        return "$hours:$minutes:$seconds";
    }

    /**
     * Удаляет подчиненного
     *
     * @param int $manager_id
     * @param int $user_id
     *
     * @return int
     */
    public function RevokeEmployee($manager_id, $user_id)
    {
        $this->db->query("DELETE FROM employees WHERE manager_id = '$manager_id' AND user_id = '$user_id' ");
        return $this->db->affected_rows;
    }

    /**
     * Добавляет подчиненного
     *
     * @param int $manager_id
     * @param int $user_id
     *
     * @return int
     */
    public function AddEmployee($manager_id, $user_id)
    {
        $is_ok = $this->db->query("SELECT id FROM employees WHERE user_id = '$user_id'")->fetch_all();
        if(count($is_ok) > 0) return 0;
        $this->db->query("INSERT INTO employees (user_id, manager_id) VALUES ('$user_id', '$manager_id')");
        return $this->db->affected_rows;
    }
}