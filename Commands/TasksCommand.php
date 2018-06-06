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
 * Пользовательская команда "/tasks"
 */
class TasksCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'tasks';

    /**
     * @var string
     */
    protected $description = 'Мои задачи';

    /**
     * @var string
     */
    protected $usage = '/tasks';

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
        $chat_id = $this->getMessage()->getChat()->getId();
        $user_id = $this->getMessage()->getFrom()->getId();

        $inline_keyboard = new InlineKeyboard([]);
        $inline_keyboard->addRow(['text' => 'Активные задачи', 'callback_data' => "active_user_tasks:$user_id"]);
        $inline_keyboard->addRow(['text' => 'Завершенные задачи', 'callback_data' => "old_user_tasks:$user_id"]);

        $data = [
            'chat_id'   => $chat_id,
            'reply_markup' => $inline_keyboard,
            'text' => 'Управление задачами'
        ];

        return Request::sendMessage($data);
    }

    /**
     * Обработка действий с задачами
     *
     * @param \Longman\TelegramBot\Entities\Message $message
     * @param string $user_idx
     * @param string $action
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
     */
    public function user_tasks_action($message, $user_idx = null, $action)
    {
        try
        {
            $chat_id = $message->getChat()->getId();
            $msg_id = $message->getMessageId();

            switch($action)
            {
                case 'active_user_tasks':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $data_edit = [
                        'chat_id' => $chat_id,
                        'text' => 'Просмотр активных задач',
                        'message_id' => $msg_id
                    ];

                    $func = new \Functions();
                    $tasks = $func->GetUserTasks($user_idx);

                    foreach ($tasks as $task)
                    {
                        $idx = $task['id'];
                        $inline_keyboard->addRow(['text' => $task['task_name'], 'callback_data' => "single_task_user_tasks:$user_idx-$idx"]);
                    }

                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "menu_user_tasks:$user_idx"]);
                    $data_edit['reply_markup'] = $inline_keyboard;

                    return Request::editMessageText($data_edit);
                }
                case 'single_task_user_tasks':
                {
                    $exp = explode('-', $user_idx);
                    $spec_id = $exp[0];
                    $task_id = $exp[1];

                    $func = new \Functions();
                    $task = $func->GetTaskInfo($task_id);
                    $task_status = "Приостановлена";
                    $btn_text = 'Начать выполнение';
                    $btn_action = 'start_task_user_tasks';

                    if($task['start_time'] > 0)
                    {
                        $task_status = "Выполняется";
                        $btn_text = 'Приостановить выполнение';
                        $btn_action = 'pause_task_user_tasks';
                    }

                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => $btn_text, 'callback_data' => "$btn_action:$user_idx"]);
                    if(strlen($task['files']) > 1) $inline_keyboard->addRow(['text' => 'Скачать файлы', 'callback_data' => "files_task_user_tasks:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Завершить задачу', 'callback_data' => "close_task_user_tasks:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "active_user_tasks:$spec_id"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => "Просмотр задачи\n",
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    $task_name = $task['task_name'];
                    $task_desc = $task['task_desc'];
                    $task_manager = $func->GetUserInfo($task['user_id']);
                    $task_manager = (strlen($task_manager['last_name']) > 1) ? $task_manager['first_name'].' '.$task_manager['last_name'] : $task_manager['first_name'];
                    $task_deadline = $task['dead_date'];
                    $task_time = $func->MakeTime($task['total_time']);

                    $data_edit['text'] .= "Название задачи: $task_name\n";
                    $data_edit['text'] .= "Описание задачи: $task_desc\n";
                    $data_edit['text'] .= "Постановщик задачи: $task_manager\n";
                    $data_edit['text'] .= "Финальный срок: $task_deadline\n";
                    $data_edit['text'] .= "Статус: $task_status\n";
                    $data_edit['text'] .= "Затраченное время: $task_time\n";

                    return Request::editMessageText($data_edit);
                }
                case 'start_task_user_tasks':
                {
                    $exp = explode('-', $user_idx);
                    $task_id = $exp[1];

                    $func = new \Functions();

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id,
                    ];

                    if($func->SetTaskTime($task_id, time()) > 0)
                    {
                        $task = $func->GetTaskInfo($task_id);
                        $task_time = $func->MakeTime($task['total_time']);
                        $task_deadline = $task['dead_date'];
                        $task_name = $task['task_name'];

                        $data_edit['text'] = "Вы приступили к выполнению задачи!\nЗатраченное время: $task_time\nФинальный срок: $task_deadline";
                        $employee = $func->GetUserInfo($task['user_id']);
                        $emp_name = (strlen($employee['last_name']) > 1) ? $employee['first_name'].' '.$employee['last_name'] : $employee['first_name'];

                        $notify = [
                            'chat_id' => $func->GetChatID($task['manager_id'])[0],
                            'text' => "$emp_name приступил к выполнению задачи '$task_name'.\nЗатраченное время: $task_time"
                        ];
                        Request::sendMessage($notify);
                    }
                    else $data_edit['text'] = 'Не удалось продолжить выполнение задачи';

                    return Request::editMessageText($data_edit);
                }
                case 'pause_task_user_tasks':
                {
                    $exp = explode('-', $user_idx);
                    $task_id = $exp[1];

                    $func = new \Functions();

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id,
                    ];

                    if($func->SetTaskTime($task_id) > 0)
                    {
                        $task = $func->GetTaskInfo($task_id);
                        $task_deadline = $task['dead_date'];
                        $task_name = $task['task_name'];
                        $task_time = $func->MakeTime($task['total_time']);

                        $data_edit['text'] = "Вы приостановили выполнение задачи!\nЗатраченное время: $task_time\nФинальный срок: $task_deadline";
                        $employee = $func->GetUserInfo($task['user_id']);
                        $emp_name = (strlen($employee['last_name']) > 1) ? $employee['first_name'].' '.$employee['last_name'] : $employee['first_name'];

                        $notify = [
                            'chat_id' => $func->GetChatID($task['manager_id'])[0],
                            'text' => "$emp_name приостановил выполнение задачи '$task_name'.\nЗатраченное время: $task_time"
                        ];
                        Request::sendMessage($notify);
                    }
                    else $data_edit['text'] = 'Не удалось продолжить выполнение задачи';

                    return Request::editMessageText($data_edit);
                }
                case 'files_task_user_tasks':
                {
                    $exp = explode('-', $user_idx);
                    $task_id = $exp[1];

                    $func = new \Functions();

                    $data_edit = [
                        'chat_id' => $chat_id,
                        'message_id' => $msg_id,
                        'text' => 'Файл отправлен!'
                    ];

                    $task = $func->GetTaskInfo($task_id);
                    $doc = Request::encodeFile($task['files']);

                    if($doc)
                    {
                        $send_doc = [
                            'chat_id' => $chat_id,
                            'document'   => Request::encodeFile($task['files']),
                        ];
                        Request::sendDocument($send_doc);
                    }
                    else $data_edit['text'] = 'Произошла ошибка при отправке файла!';
                    return Request::editMessageText($data_edit);
                }
                case 'close_task_user_tasks':
                {
                    $exp = explode('-', $user_idx);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id
                    ];

                    $func = new \Functions();
                    if($func->CloseTask($exp[1]))
                    {
                        $data_edit['text'] = 'Задача успешно завершена!';

                        $task = $func->GetTaskInfo($exp[1]);
                        $task_time = $func->MakeTime($task['total_time']);
                        $task_name = $task['task_name'];

                        $now_timestamp = new \DateTime();
                        $dead_timestamp = new \DateTime($task['dead_date']);

                        $is_ok = "";

                        if($dead_timestamp > $now_timestamp)  $is_ok = "Задача выполнена в срок.";
                        else $is_ok = "Задача просрочена.";

                        $employee = $func->GetUserInfo($task['user_id']);
                        $emp_name = (strlen($employee['last_name']) > 1) ? $employee['first_name'].' '.$employee['last_name'] : $employee['first_name'];

                        $notify = [
                            'chat_id' => $func->GetChatID($task['manager_id'])[0],
                            'text' => "$emp_name завершил задачу '$task_name'.\nЗатраченное время: $task_time\n$is_ok"
                        ];

                        Request::sendMessage($notify);
                    }
                    else $data_edit['text'] = 'Произошла ошибка при завершении задачи!';

                    return Request::editMessageText($data_edit);
                }
                case 'old_user_tasks':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $data_edit = [
                        'chat_id' => $chat_id,
                        'text' => 'Просмотр завершенных задач',
                        'message_id' => $msg_id
                    ];

                    $func = new \Functions();
                    $tasks = $func->GetUserTasks($user_idx, false);

                    foreach ($tasks as $task)
                    {
                        $idx = $task['id'];
                        $inline_keyboard->addRow(['text' => $task['task_name'], 'callback_data' => "single_old_task_user_tasks:$user_idx-$idx"]);
                    }

                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "menu_user_tasks:$user_idx"]);
                    $data_edit['reply_markup'] = $inline_keyboard;

                    return Request::editMessageText($data_edit);
                }
                case 'single_old_task_user_tasks':
                {
                    $exp = explode('-', $user_idx);
                    $user_id = $exp[0];
                    $task_id = $exp[1];

                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "old_user_tasks:$user_id"]);
                    $data_edit = [
                        'chat_id' => $chat_id,
                        'message_id' => $msg_id,
                        'text' => "Просмотр задачи\n",
                        'reply_markup' => $inline_keyboard
                    ];

                    $func = new \Functions();
                    $task = $func->GetTaskInfo($task_id);

                    $task_name = $task['task_name'];
                    $task_desc = $task['task_desc'];
                    $task_manager = $func->GetUserInfo($task['manager_id']);
                    $task_manager = (strlen($task_manager['last_name']) > 1) ? $task_manager['first_name'].' '.$task_manager['last_name'] : $task_manager['first_name'];
                    $task_deadline = $task['dead_date'];
                    $task_finish = (strlen($task['finish_date']) > 0) ? $task['finish_date'] : "Нет";
                    $task_time = $func->MakeTime($task['total_time']);

                    $data_edit['text'] .= "Название задачи: $task_name\n";
                    $data_edit['text'] .= "Описание задачи: $task_desc\n";
                    $data_edit['text'] .= "Менеджер: $task_manager\n";
                    $data_edit['text'] .= "Финальный срок: $task_deadline\n";
                    $data_edit['text'] .= "Завершена: $task_finish\n";
                    $data_edit['text'] .= "Затраченное время: $task_time\n";

                    return Request::editMessageText($data_edit);
                }
                case 'menu_user_tasks':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Активные задачи', 'callback_data' => "active_user_tasks:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Завершенные задачи', 'callback_data' => "old_user_tasks:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'reply_markup' => $inline_keyboard,
                        'text' => 'Управление задачами',
                        'message_id' => $msg_id
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
