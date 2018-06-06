<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Conversation;

/**
 * Команда менеджера "/manage"
 */
class EmployeesCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'employees';

    /**
     * @var string
     */
    protected $description = 'Мои подчиненные';

    /**
     * @var string
     */
    protected $usage = '/employees';

    /**
     * @var string
     */
    protected $version = '0.0.1';

    /**
     * @var string
     */
    protected $private_only = true;

    /**
     * Объект диалога
     *
     * @var \Longman\TelegramBot\Conversation
     */
    protected $conversation;

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Запуск команды и обработка уже запущенных процедур
     * обработки входящих параметров
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | bool
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $func = new \Functions();

        $chat_id = $this->getMessage()->getChat()->getId();
        $user_id = $this->getMessage()->getFrom()->getId();

        if(!$func->IsManager($user_id)) return false;

        $this->conversation = new Conversation($user_id, $chat_id);

        if(isset($this->conversation->notes['awaiting_reply']))
        {
            switch($this->conversation->notes['awaiting_reply'])
            {
                case 'add_employees':
                {
                    return $this->employees_action($this->getMessage(), null, $this->conversation->notes['awaiting_reply']);
                }
                default:
                {
                    return false;
                }
            }
        }
        else
        {
            $inline_keyboard = new InlineKeyboard([]);
            $inline_keyboard->addRow(['text' => 'Мои подчиненные', 'callback_data' => "current_employees:$user_id"]);
            $inline_keyboard->addRow(['text' => 'Добавить подчиненного', 'callback_data' => "add_employees:$user_id"]);

            $data = [
                'chat_id'   => $chat_id,
                'reply_markup' => $inline_keyboard,
                'text' => 'Управление моими подчиненными'
            ];

            return Request::sendMessage($data);
        }
    }

    /**
     * Обработка действий с подчиненными
     *
     * @param \Longman\TelegramBot\Entities\Message $message
     * @param string $user_idx
     * @param string $action
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
     */
    public function employees_action($message, $user_idx = null, $action)
    {
        try
        {
            $chat_id = $message->getChat()->getId();
            $msg_id = $message->getMessageId();

            switch($action)
            {
                case 'current_employees':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $func = new \Functions();
                    $employees = $func->FetchManagerEmployees($user_idx);
                    foreach ($employees as $employee)
                    {
                        $info = $func->GetUserInfo($employee);
                        $info = (strlen($info['last_name']) > 2) ? $info['first_name'].' '.$info['last_name'] : $info['first_name'];
                        $inline_keyboard->addRow(['text' => $info, 'callback_data' => "delete_employees:$user_idx-$employee"]);
                    }
                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "menu_employees:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Удалить подчиненного',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'delete_employees':
                {
                    $exp = explode('-', $user_idx);
                    $employee_id = $exp[1];
                    $user_id = $exp[0];

                    $func = new \Functions();

                    $data_edit = [
                        'chat_id' => $chat_id,
                        'message_id' => $msg_id
                    ];

                    if($func->RevokeEmployee($user_id ,$employee_id))
                    {
                        $data_edit['text'] = 'Подчиненный успешно удален!';

                        $notify = [
                            'chat_id' => $func->GetChatID($employee_id)[0],
                            'text' => "У вас был удален менеджер."
                        ];

                        Request::sendMessage($notify);
                    }
                    else $data_edit['text'] = 'Произошла ошибка при удалении подчиненного!';

                    return Request::editMessageText($data_edit);
                }
                case 'add_employees':
                {
                    try
                    {
                        $chat_id = $message->getChat()->getId();
                        $user_id = ($user_idx) ? $user_idx : $message->getFrom()->getId();
                        $text = trim($message->getText(true));

                        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
                        $notes = &$this->conversation->notes;
                        !is_array($notes) && $notes = [];

                        $data = [
                            'chat_id' => $chat_id,
                        ];

                        if(!isset($notes['awaiting_reply']))
                        {
                            $data_edit = [
                                'chat_id' => $chat_id,
                                'message_id' => $msg_id,
                                'text' => "Введите ID пользователя, например 123456\nДля отмены действия отправьте /cancel"
                            ];

                            $notes['awaiting_reply'] = $action;
                            $this->conversation->update();

                            return Request::editMessageText($data_edit);
                        }

                        if ($user_idx) return true;

                        if (strlen($text) < 4) $data['text'] = 'Введите ID пользователя, например 123456';
                        else
                        {
                            $func = new \Functions();
                            if ($func->AddEmployee($user_id, $text) > 0)
                            {
                                $task_manager = $func->GetUserInfo($user_id);
                                $task_manager = (strlen($task_manager['last_name']) > 1) ? $task_manager['first_name'].' '.$task_manager['last_name'] : $task_manager['first_name'];

                                $notify = [
                                    'chat_id' => $func->GetChatID($text)[0],
                                    'text' => "Вам был назначен менеджер $task_manager."
                                ];

                                Request::sendMessage($notify);
                                $data['text'] = 'Подчиненный успешно добавлен';
                                $this->conversation->stop();
                            }
                            else $data['text'] = "Ошибка добавления, попробуйте еще раз\nВозможно у пользователя уже есть менеджер!";
                        }
                        return Request::sendMessage($data);
                    }
                    catch(TelegramException $e)
                    {
                        //echo $e;
                    }
                }
                case 'menu_employees':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Мои подчиненные', 'callback_data' => "current_employees:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Добавить подчиненного', 'callback_data' => "add_employees:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                        'text' => 'Управление моими подчиненными'
                    ];

                    return Request::editMessageText($data_edit);
                }
            }
        }

        catch(TelegramException $e)
        {
            //echo $e;
        }

        return true;
    }
}
