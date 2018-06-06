<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\AdminCommands\UserCommand;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Commands\UserCommands\ManageCommand;
use Longman\TelegramBot\Commands\UserCommands\TasksCommand;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * Callback query command
 *
 * This command handles all callback queries sent via inline keyboard buttons.
 *
 * @see InlinekeyboardCommand.php
 */
class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Reply to callback query';

    /**
     * @var string
     */
    protected $version = '1.1.1';

    /**
     * Метод для обработки входящих запросов
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
     */

    public function execute()
    {
        $callback_query    = $this->getCallbackQuery();
        $data = explode(':', $callback_query->getData());

        switch($data[0])
        {
            case 'add_user':
            case 'delete_user':
            {
                if(!$this->getTelegram()->isAdmin()) return false;
                return $this->user_callback($callback_query, $data[1], $data[0]);
            }
            case 'add_token':
            case 'delete_token':
            {
                if(!$this->getTelegram()->isAdmin()) return false;
                return $this->token_callback($callback_query, $data[1], $data[0]);
            }
            case 'menu_admin':
            case 'show_admin':
            case 'info_admin':
            case 'delete_admin':
            case 'add_admin':
            case 'exit_menu_admin':
            {
                if(!$this->getTelegram()->isAdmin()) return false;
                return $this->admin_callback($callback_query, $data[1], $data[0]);
            }
            case 'menu_managers':
            case 'add_managers':
            case 'show_managers':
            case 'info_managers':
            case 'delete_managers':
            case 'exit_menu_managers':
            {
                if(!$this->getTelegram()->isAdmin()) return false;
                return $this->manage_managers_callback($callback_query, $data[1], $data[0]);
            }
            case 'menu_mgr_tasks':
            case 'add_mgr_tasks':
            case 'active_mgr_tasks':
            case 'old_mgr_tasks':
            case 'add_input_mgr_tasks':
            case 'confirm_add_mgr_tasks':
            case 'cancel_add_mgr_tasks':
            case 'single_task_mgr_tasks':
            case 'add_file_mgr_tasks':
            case 'close_mgr_tasks':
            case 'single_old_task_mgr_tasks':
            {
                $func = new \Functions();
                if(!$func->IsManager($data[1])) return false;
                return $this->manager_tasks($callback_query, $data[1], $data[0]);
            }
            case 'menu_user_tasks':
            case 'active_user_tasks':
            case 'single_task_user_tasks':
            case 'start_task_user_tasks':
            case 'pause_task_user_tasks':
            case 'files_task_user_tasks':
            case 'close_task_user_tasks':
            case 'old_user_tasks':
            case 'single_old_task_user_tasks':
            {
                return $this->user_tasks($callback_query, $data[1], $data[0]);
            }
        }

        return Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
    }

    /**
     * Обработка кнопок добавить и удалить пользователя
     *
     * @param \Longman\TelegramBot\Entities\CallbackQuery $callback_query
     * @param string $user_id
     * @param string $action
     */
    public function user_callback($callback_query, $user_id, $action)
    {
        try
        {
            $chat_id = $callback_query->getFrom()->getId();
            $msg_id = $callback_query->getMessage()->getMessageId();

            $data_edit = [
                'chat_id'   => $chat_id,
                'text' => "Введите имя пользователя, например user123\nДля отмены действия отправьте /cancel",
                'message_id' => $msg_id
            ];
            Request::editMessageText($data_edit);

            $cmd = new UserCommand($this->getTelegram());
            $cmd->user_action($callback_query->getMessage(), $user_id, $action);

            Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
        }
        catch(TelegramException $e)
        {
            //throw new TelegramException($e);
            //echo $e;
        }
    }

    /**
     * Обработка кнопок добавить и удалить токен
     *
     * @param \Longman\TelegramBot\Entities\CallbackQuery $callback_query
     * @param string $user_id
     * @param string $action
     */
    public function token_callback($callback_query, $user_id, $action)
    {
        try
        {
            $chat_id = $callback_query->getFrom()->getId();
            $msg_id = $callback_query->getMessage()->getMessageId();

            if($action == 'delete_token')
            {
                $data_edit = [
                    'chat_id'   => $chat_id,
                    'text' => "Введите токен для удаления(10 символов) , например abcdef1230\nДля отмены действия отправьте /cancel",
                    'message_id' => $msg_id
                ];
                Request::editMessageText($data_edit);
            }

            $cmd = new UserCommand($this->getTelegram());
            $cmd->token_action($callback_query->getMessage(), $user_id, $action);

            Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
    }

    /**
     * Обработка нажатия кнопок административного раздела
     *
     * @param \Longman\TelegramBot\Entities\CallbackQuery $callback_query
     * @param string $user_id
     * @param string $action
     */
    public function admin_callback($callback_query, $user_id, $action)
    {
        try
        {
            $chat_id = $callback_query->getFrom()->getId();
            $msg_id = $callback_query->getMessage()->getMessageId();
            if($action == 'add_admin')
            {
                $data_edit = [
                    'chat_id'   => $chat_id,
                    'text' => "Введите User ID пользователя, например 3892136\nДля того, чтобы узнать User ID используйте\n/whois [часть имени/ника]\nДля отмены действия отправьте /cancel",
                    'message_id' => $msg_id
                ];
                Request::editMessageText($data_edit);
            }
            $cmd = new UserCommand($this->getTelegram());
            $cmd->admin_action($callback_query->getMessage(), $user_id, $action);

            Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
    }

    /**
     * Обработка нажатия кнопок раздела менеджеров
     *
     * @param \Longman\TelegramBot\Entities\CallbackQuery $callback_query
     * @param string $user_id
     * @param string $action
     */
    public function manage_managers_callback($callback_query, $user_id, $action)
    {
        try
        {
            $chat_id = $callback_query->getFrom()->getId();
            $msg_id = $callback_query->getMessage()->getMessageId();
            if($action == 'add_managers')
            {
                $data_edit = [
                    'chat_id'   => $chat_id,
                    'text' => "Введите User ID пользователя, например 3892136\nДля того, чтобы узнать User ID используйте\n/whois [часть имени/ника]\nДля отмены действия отправьте /cancel",
                    'message_id' => $msg_id
                ];
                Request::editMessageText($data_edit);
            }
            $cmd = new UserCommand($this->getTelegram());
            $cmd->managers_action($callback_query->getMessage(), $user_id, $action);

            Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
    }

    /**
     * Обработка кнопок, нажимаемых менеджерами
     *
     * @param \Longman\TelegramBot\Entities\CallbackQuery $callback_query
     * @param string $user_id
     * @param string $action
     *
     */
    public function manager_tasks($callback_query, $user_id, $action)
    {
        try
        {
            $cmd = new ManageCommand($this->getTelegram());
            $cmd->tasks_action($callback_query->getMessage(), $user_id, $action);
            Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
    }

    /**
     * Обработка кнопок, нажимаемых пользователями
     *
     * @param \Longman\TelegramBot\Entities\CallbackQuery $callback_query
     * @param string $user_id
     * @param string $action
     *
     */
    public function user_tasks($callback_query, $user_id, $action)
    {
        try
        {
            $cmd = new TasksCommand($this->getTelegram());
            $cmd->user_tasks_action($callback_query->getMessage(), $user_id, $action);
            Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
    }
}
