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

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Conversation;

/**
 * Manage command
 *
 * Gets executed when a user first starts using the bot.
 */
class ManageCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'manage';

    /**
     * @var string
     */
    protected $description = 'Управление задачами';

    /**
     * @var string
     */
    protected $usage = '/manage';

    /**
     * @var string
     */
    protected $version = '0.0.1';

    /**
     * @var string
     */
    protected $private_only = true;

    /**
     * Conversation Object
     *
     * @var \Longman\TelegramBot\Conversation
     */
    protected $conversation;

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | bool
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $chat_id = $this->getMessage()->getChat()->getId();
        $user_id = $this->getMessage()->getFrom()->getId();

        $this->conversation = new Conversation($user_id, $chat_id);

        if(isset($this->conversation->notes['awaiting_reply']))
        {
            switch($this->conversation->notes['awaiting_reply'])
            {
                case 'add_input_mgr_tasks':
                case 'add_file_mgr_tasks':
                {
                    return $this->tasks_action($this->getMessage(), null, $this->conversation->notes['awaiting_reply']);
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
            $inline_keyboard->addRow(['text' => 'Создать задачу', 'callback_data' => "add_mgr_tasks:$user_id"]);
            $inline_keyboard->addRow(['text' => 'Активные задачи', 'callback_data' => "active_mgr_tasks:$user_id"]);
            $inline_keyboard->addRow(['text' => 'Завершенные задачи', 'callback_data' => "old_mgr_tasks:$user_id"]);

            $data = [
                'chat_id'   => $chat_id,
                'reply_markup' => $inline_keyboard,
                'text' => 'Управление задачами'
            ];

            return Request::sendMessage($data);
        }
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
    public function tasks_action($message, $user_idx = null, $action)
    {
        try
        {
            $chat_id = $message->getChat()->getId();
            $msg_id = $message->getMessageId();

            switch($action)
            {
                case 'add_mgr_tasks':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $func = new \Functions();
                    $employees = $func->FetchManagerEmployees($user_idx);
                    foreach ($employees as $employee)
                    {
                        $info = $func->GetUserInfo($employee);
                        $info = (strlen($info['last_name']) > 2) ? $info['first_name'].' '.$info['last_name'] : $info['first_name'];
                        $inline_keyboard->addRow(['text' => $info, 'callback_data' => "add_input_mgr_tasks:$employee"]);
                    }
                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "menu_mgr_tasks:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Выберите исполнителя',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'add_input_mgr_tasks':
                {
                    $user_id = ($user_idx) ? $user_idx : $message->getFrom()->getId();
                    $text = trim($message->getText(true));

                    $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
                    $notes = &$this->conversation->notes;
                    !is_array($notes) && $notes = [];

                    $data = [
                        'chat_id'   => $chat_id,
                    ];

                    if(!isset($notes['awaiting_reply'])) // первый вызов с ID исполнителя
                    {
                        $notes['awaiting_reply'] = $action;
                        $notes['task_employee'] = $user_id;
                        $notes['stage'] = 0;
                        $this->conversation->update();

                        $data_edit = [
                            'chat_id'   => $chat_id,
                            'text' => "Введите название задачи, например 'создать базу данных'\nДля отмены действия отправьте /cancel",
                            'message_id' => $msg_id
                        ];
                        return Request::editMessageText($data_edit);
                    }

                    switch($notes['stage'])
                    {
                        case 0:
                        {
                            if(strlen($text) < 5) $data['text'] = 'Введите название задачи, например "создать базу данных"';
                            else
                            {
                                ++$notes['stage'];
                                $notes['task_name'] = $text;
                                $notes['task_manager'] = $user_id;
                                $this->conversation->update();
                                $data['text'] = 'Введите детальное описание задачи';
                            }
                            break;
                        }
                        case 1:
                        {
                            if(strlen($text) < 10) $data['text'] = 'Введите детальное описание задачи';
                            else
                            {
                                ++$notes['stage'];
                                $notes['task_desc'] = $text;
                                $this->conversation->update();
                                $data['text'] = "Укажите срок сдачи задачи, например 2018-01-02 12:00, где:\n2018 - год, 01 - месяц, 02 - день.";
                            }
                            break;
                        }
                        case 2:
                        {
                            $func = new \Functions();
                            if(!$func->validate_timestamp($text)) $data['text'] = 'Некорректный формат даты! Формат даты: 2018-01-02 12:00';
                            else
                            {
                                $notes['task_time'] = $text;
                                unset($notes['stage']);
                                $this->conversation->update();

                                $task_name = $notes['task_name'];
                                $task_desc = $notes['task_desc'];
                                $task_time = $notes['task_time'];

                                $task_employee = $func->GetUserInfo($notes['task_employee']);
                                $task_employee = (strlen($task_employee['last_name']) > 2) ? $task_employee['first_name'].' '.$task_employee['last_name'] : $task_employee['first_name'];

                                $inline_keyboard = new InlineKeyboard([]);
                                $inline_keyboard->addRow(['text' => 'Добавить задачу', 'callback_data' => "confirm_add_mgr_tasks:$user_id"]);
                                $inline_keyboard->addRow(['text' => 'Отменить действие', 'callback_data' => "cancel_add_mgr_tasks:$user_id"]);

                                $data['text'] = "Название задачи: $task_name\n";
                                $data['text'] .= "Описание задачи: $task_desc\n";
                                $data['text'] .= "Крайний срок: $task_time\n";
                                $data['text'] .= "Исполнитель: $task_employee\n";
                                $data['text'] .= "Введенная информация верна?\n";
                                $data['reply_markup'] = $inline_keyboard;
                            }
                            break;
                        }
                    }
                    return Request::sendMessage($data);
                }
                case 'confirm_add_mgr_tasks':
                {
                    $user_id = ($user_idx) ? $user_idx : $message->getFrom()->getId();

                    $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
                    $notes = &$this->conversation->notes;
                    !is_array($notes) && $notes = [];

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id
                    ];
                    $func = new \Functions();
                    $task = $notes['task_name'];

                    $result = $func->AddTask($notes['task_name'], $notes['task_desc'], $notes['task_employee'], $notes['task_manager'], $notes['task_time']);

                    if($result > 0)
                    {
                        $data_edit['text'] = 'Задача добавлена!';
                        $tell_user = [
                            'text' => "Вам была добавлена задача '$task'.\nУзнать подробнее /tasks",
                            'chat_id' => $func->GetChatID($notes['task_employee'])[0]
                        ];
                        Request::sendMessage($tell_user);
                    }
                    else $data_edit['text'] = 'Не удалось добавить задачу, попробуйте снова';

                    unset($notes);
                    $this->conversation->stop();
                    return Request::editMessageText($data_edit);
                }
                case 'cancel_add_mgr_tasks':
                {
                    $user_id = ($user_idx) ? $user_idx : $message->getFrom()->getId();

                    $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
                    $notes = &$this->conversation->notes;
                    !is_array($notes) && $notes = [];

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => "Добавление задачи отменено!",
                        'message_id' => $msg_id
                    ];
                    $this->conversation->cancel();
                    return Request::editMessageText($data_edit);
                }
                case 'active_mgr_tasks':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $data_edit = [
                        'chat_id' => $chat_id,
                        'text' => 'Просмотр активных задач',
                        'message_id' => $msg_id
                    ];

                    $func = new \Functions();
                    $tasks = $func->GetManagerTasks($user_idx);

                    foreach ($tasks as $task)
                    {
                        $idx = $task['id'];
                        $inline_keyboard->addRow(['text' => $task['task_name'], 'callback_data' => "single_task_mgr_tasks:$user_idx-$idx"]);
                    }

                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "menu_mgr_tasks:$user_idx"]);
                    $data_edit['reply_markup'] = $inline_keyboard;

                    return Request::editMessageText($data_edit);
                }
                case 'single_task_mgr_tasks':
                {
                    $spec_id = explode('-', $user_idx)[0];
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Прикрепить файлы', 'callback_data' => "add_file_mgr_tasks:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Закрыть задачу', 'callback_data' => "close_mgr_tasks:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "active_mgr_tasks:$spec_id"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Редактирование задачи',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'add_file_mgr_tasks':
                {
                    $exp = explode('-', $user_idx);
                    $user_id = ($user_idx) ? $exp[0] : $message->getFrom()->getId();
                    $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
                    $notes = &$this->conversation->notes;
                    !is_array($notes) && $notes = [];

                    $data = [
                        'chat_id'   => $chat_id,
                    ];

                    if(!isset($notes['awaiting_reply']))
                    {
                        $notes['awaiting_reply'] = $action;
                        $notes['task_id'] = $exp[1];
                        $this->conversation->update();

                        $data_edit = [
                            'chat_id'   => $chat_id,
                            'text' => "Загрузите архив с файлами\nЕсли у задачи уже есть архив, он будет перезаписан\nДля отмены действия отправьте /cancel",
                            'message_id' => $msg_id
                        ];
                        return Request::editMessageText($data_edit);
                    }
                    $message_type = $message->getType();
                    if ($message_type == 'document')
                    {
                        $doc = $message->getDocument();

                        $file_id = $doc->getFileId();
                        $file = Request::getFile(['file_id' => $file_id]);
                        if ($file->isOk() && Request::downloadFile($file->getResult()))
                        {
                            $func = new \Functions();
                            $path = $this->telegram->getDownloadPath() . '/' . $file->getResult()->getFilePath();
                            if($func->SetTaskFile($notes['task_id'], $path))
                            {
                                $data['text'] = 'Файл успешно прикреплен к задаче.';
//                                $send_doc = [
//                                    'chat_id' => $chat_id,
//                                    'document'   => Request::encodeFile($path),
//                                ];
//                                Request::sendDocument($send_doc);
                                $task = $func->GetTaskInfo($notes['task_id']);
                                $task_name = $task['task_name'];
                                $notify = [
                                    'chat_id' => $task['user_id'],
                                    'text' => "К Вашей задаче '$task_name' был прикреплен файл!\nЧтобы скачать файл, используйте /tasks"
                                ];
                                Request::sendMessage($notify);
                                $this->conversation->stop();
                            }
                            else $data['text'] = 'Ошибка изменения файла у задачи, попробуйте снова.';
                        }
                        else $data['text'] = 'Ошибка загрузки файла, попробуйте снова.';
                    }
                    else $data['text'] = 'Неверный тип файла. Фотографии, видео и голосовые сообщения не поддерживаются.';

                    return Request::sendMessage($data);
                }
                case 'close_mgr_tasks':
                {
                    $exp = explode('-', $user_idx);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id
                    ];

                    $func = new \Functions();
                    if($func->CloseTask($exp[1]))
                    {
                        $data_edit['text'] = 'Задача успешно закрыта!';

                        $task = $func->GetTaskInfo($exp[1]);
                        $task_name = $task['task_name'];

                        $notify = [
                            'chat_id' => $task['user_id'],
                            'text' => "Задача '$task_name' закрыта менеджером."
                        ];

                        Request::sendMessage($notify);
                    }
                    else $data_edit['text'] = 'Произошла ошибка при закрытии задачи!';

                    return Request::editMessageText($data_edit);
                }
                case 'old_mgr_tasks':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $data_edit = [
                        'chat_id' => $chat_id,
                        'text' => 'Просмотр завершенных задач',
                        'message_id' => $msg_id
                    ];

                    $func = new \Functions();
                    $tasks = $func->GetManagerTasks($user_idx, false);

                    foreach ($tasks as $task)
                    {
                        $idx = $task['id'];
                        $inline_keyboard->addRow(['text' => $task['task_name'], 'callback_data' => "single_old_task_mgr_tasks:$user_idx-$idx"]);
                    }

                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "menu_mgr_tasks:$user_idx"]);
                    $data_edit['reply_markup'] = $inline_keyboard;

                    return Request::editMessageText($data_edit);
                }
                case 'single_old_task_mgr_tasks':
                {
                    $exp = explode('-', $user_idx);
                    $user_id = $exp[0];
                    $task_id = $exp[1];

                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "old_mgr_tasks:$user_id"]);
                    $data_edit = [
                        'chat_id' => $chat_id,
                        'message_id' => $msg_id,
                        'text' => "Просмотр задачи\n",
                        'reply_markup' => $inline_keyboard
                    ];

                    $func = new \Functions();
                    $task = $func->GetTaskInfo($task_id);

                    $task_name = $task['task_name'];
                    $task_desc = $task['desc'];
                    $task_employee = $func->GetUserInfo($task['user_id']);
                    $task_employee = (strlen($task_employee['last_name']) > 1) ? $task_employee['first_name'].' '.$task_employee['last_name'] : $task_employee['first_name'];
                    $task_deadline = $task['dead_date'];
                    $task_finish = (strlen($task['finish_date']) > 0) ? $task['finish_date'] : "Нет";
                    $task_time = $func->MakeTime($task['total_time']);
                    //$task_time = (strlen($task_time) > 1) ? "Нет" : $task_time;


                    $data_edit['text'] .= "Название задачи: $task_name\n";
                    $data_edit['text'] .= "Описание задачи: $task_name\n";
                    $data_edit['text'] .= "Исполнитель: $task_employee\n";
                    $data_edit['text'] .= "Финальный срок: $task_deadline\n";
                    $data_edit['text'] .= "Завершена: $task_finish\n";
                    $data_edit['text'] .= "Затраченное время: $task_time\n";

                    return Request::editMessageText($data_edit);

                }
                case 'menu_mgr_tasks':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Создать задачу', 'callback_data' => "add_mgr_tasks:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Активные задачи', 'callback_data' => "active_mgr_tasks:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Завершенные задачи', 'callback_data' => "old_mgr_tasks:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Управление задачами',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
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
