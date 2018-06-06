<?php
/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Written by Jack'lul <jacklul@jacklul.com>
 */

namespace Longman\TelegramBot\Commands\AdminCommands;

use Longman\TelegramBot\Commands\AdminCommand;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Conversation;

/**
 * Команда администратора "/user"
 */
class UserCommand extends AdminCommand
{
    /**
     * @var string
     */
    protected $name = 'user';

    /**
     * @var string
     */
    protected $description = 'Управление пользователями системы';

    /**
     * @var string
     */
    protected $usage = '/user';

    /**
     * @var string
     */
    protected $version = '0.1.1';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Объект диалога
     *
     * @var \Longman\TelegramBot\Conversation
     */
    protected $conversation;

    /**
     * @var string
     */
    protected $private_only = true;

    /**
     * Запуск команды и обработка уже запущенных процедур
     * обработки входящих параметров
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
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
                case 'add_user':
                case 'delete_user':
                {
                    return $this->user_action($this->getMessage(), null, $this->conversation->notes['awaiting_reply']);
                }
                case 'delete_token':
                {
                    return $this->token_action($this->getMessage(), null, $this->conversation->notes['awaiting_reply']);
                }
                case 'add_admin':
                {
                    return $this->admin_action($this->getMessage(), null, $this->conversation->notes['awaiting_reply']);
                }
                case 'add_managers':
                {
                    return $this->managers_action($this->getMessage(), null, $this->conversation->notes['awaiting_reply']);
                }
                default:
                {
                    return false;
                }
            }

        }
        else
        {
            $inline_keyboard = new InlineKeyboard
            (
                [
                    ['text' => 'Добавить пользователя', 'callback_data' => "add_user:$user_id"],
                    ['text' => 'Удалить пользователя', 'callback_data' => "delete_user:$user_id"]
                ],
                [
                    ['text' => 'Добавить токен', 'callback_data' => "add_token:$user_id"],
                    ['text' => 'Удалить токен', 'callback_data' => "delete_token:$user_id"]
                ],
                [
                    ['text' => 'Администраторы', 'callback_data' => "menu_admin:$user_id"],
                    ['text' => 'Менеджеры', 'callback_data' => "menu_managers:$user_id"]
                ]

            );

            $data = [
                'chat_id'   => $chat_id,
                'reply_markup' => $inline_keyboard,
                'text' => 'Управление пользователями и доступом'
            ];

            return Request::sendMessage($data);
        }
    }

    /**
     * Обработка добавления и удаления авторизации
     *
     * @param \Longman\TelegramBot\Entities\Message $message
     * @param string $user_idx
     * @param string $action
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
     */
    public function user_action($message, $user_idx = null, $action)
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
                'chat_id'   => $chat_id,
            ];

            $notes['awaiting_reply'] = $action;
            $this->conversation->update();

            if($user_idx) return true;

            if (strlen($text) < 4) $data['text'] = 'Введите имя пользователя, например user123';
            else
            {
                $func = new \Functions();
                if($notes['awaiting_reply'] == 'add_user')
                {
                    if($func->AddAuth($text) > 0)
                    {
                        $data['text'] = 'Пользователь успешно добавлен';
                        $this->conversation->stop();
                    }
                    else $data['text'] = 'Ошибка добавления, попробуйте еще раз';
                }
                else if($notes['awaiting_reply'] == 'delete_user')
                {
                    if($func->RevokeAuth($text) > 0)
                    {
                        $data['text'] = 'Пользователь успешно удален';
                        $this->conversation->stop();
                    }
                    else $data['text'] = 'Пользователь не найден, попробуйте еще раз';
                }
            }
            return Request::sendMessage($data);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
        return true;
    }

    /**
     * Обработка добавления и удаления токенов
     *
     * @param \Longman\TelegramBot\Entities\Message $message
     * @param string $user_idx
     * @param string $action
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
     */
    public function token_action($message, $user_idx = null, $action)
    {
        try
        {
            $chat_id = $message->getChat()->getId();
            $user_id = ($user_idx) ? $user_idx : $message->getFrom()->getId();
            $text = trim($message->getText(true));
            $func = new \Functions();

            $data = [
                'chat_id'   => $chat_id,
            ];

            if($action == 'add_token')
            {
                $token = $func->AddToken();
                if($token) $data['text'] = "Токен создан.\nДля активации отправьте боту:\n/start $token";
                else $data['text'] = 'Ошибка создания токена, попробуте снова';
                return Request::sendMessage($data);
            }

            $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
            $notes = &$this->conversation->notes;
            !is_array($notes) && $notes = [];

            $notes['awaiting_reply'] = $action;
            $this->conversation->update();

            if($user_idx) return true;

            if (strlen($text) != 10) $data['text'] = 'Введите токен для удаления(10 символов) , например abcdef1230';
            else
            {
                if($func->RevokeToken($text) > 0)
                {
                    $data['text'] = 'Токен успешно удален';
                    $this->conversation->stop();
                }
                else $data['text'] = 'Токен не найден, попробуйте еще раз';

            }
            return Request::sendMessage($data);
        }
        catch(TelegramException $e)
        {
            //echo $e;
        }
        return true;
    }

    /**
     * Обработка действий с администраторами
     *
     * @param \Longman\TelegramBot\Entities\Message $message
     * @param string $user_idx
     * @param string $action
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
     */
    public function admin_action($message, $user_idx = null, $action)
    {
        try
        {
            $chat_id = $message->getChat()->getId();
            $msg_id = $message->getMessageId();

            switch($action)
            {
                case 'menu_admin':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Список администраторов', 'callback_data' => "show_admin:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Добавить администратора', 'callback_data' => "add_admin:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "exit_menu_admin:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Управление администраторами',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'show_admin':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $func = new \Functions();
                    $admins = $func->FetchAdmins();
                    foreach ($admins as $admin)
                    {
                        $info = $func->GetUserInfo($admin);
                        $info = (strlen($info['last_name']) > 2) ? $info['first_name'].' '.$info['last_name'] : $info['first_name'];
                        $inline_keyboard->addRow(['text' => $info, 'callback_data' => "info_admin:$admin"]);
                    }
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "menu_admin:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Список администраторов',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'info_admin':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Удалить', 'callback_data' => "delete_admin:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "show_admin:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Удаление администратора',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'delete_admin':
                {
                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id
                    ];
                    $func = new \Functions();
                    if($func->RevokeAdmin($user_idx) > 0) $data_edit['text'] = 'Администратор удален';
                    else $data_edit['text'] = 'Ошибка удаления, попробуйте снова';
                    return Request::editMessageText($data_edit);
                }
                case 'add_admin':
                {
                    $user_id = ($user_idx) ? $user_idx : $message->getFrom()->getId();
                    $text = trim($message->getText(true));

                    $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
                    $notes = &$this->conversation->notes;
                    !is_array($notes) && $notes = [];

                    $data = [
                        'chat_id'   => $chat_id,
                    ];

                    $notes['awaiting_reply'] = $action;
                    $this->conversation->update();

                    if($user_idx) return true;

                    if (strlen($text) < 4) $data['text'] = 'Введите ID пользователя, например 123456';
                    else
                    {
                        $func = new \Functions();
                        if($func->AddAdmin($text) > 0)
                        {
                            $tell_user = [
                                'text' => "Вы были назначены администратором.\nСписок доступных команд /help",
                                'chat_id' => $func->GetChatID($text)[0]
                            ];
                            Request::sendMessage($tell_user);
                            $data['text'] = 'Администратор добавлен';
                            $this->conversation->stop();
                        }
                        else $data['text'] = 'Ошибка добавления, попробуйте еще раз';
                    }
                    return Request::sendMessage($data);
                }
                case 'exit_menu_admin':
                {
                    $inline_keyboard = new InlineKeyboard
                    (
                        [
                            ['text' => 'Добавить пользователя', 'callback_data' => "add_user:$user_idx"],
                            ['text' => 'Удалить пользователя', 'callback_data' => "delete_user:$user_idx"]
                        ],
                        [
                            ['text' => 'Добавить токен', 'callback_data' => "add_token:$user_idx"],
                            ['text' => 'Удалить токен', 'callback_data' => "delete_token:$user_idx"]
                        ],
                        [
                            ['text' => 'Администраторы', 'callback_data' => "menu_admin:$user_idx"],
                            ['text' => 'Менеджеры', 'callback_data' => "menu_managers:$user_idx"],
                        ]

                    );

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Управление пользователями и доступом',
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

    /**
     * Обработка действий с менеджерами
     *
     * @param \Longman\TelegramBot\Entities\Message $message
     * @param string $user_idx
     * @param string $action
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse | false
     */
    public function managers_action($message, $user_idx = null, $action)
    {
        try
        {
            $chat_id = $message->getChat()->getId();
            $msg_id = $message->getMessageId();

            switch($action)
            {
                case 'menu_managers':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Список менеджеров', 'callback_data' => "show_managers:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Добавить менеджера', 'callback_data' => "add_managers:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Возврат в меню', 'callback_data' => "exit_menu_managers:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Управление менеджерами',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'show_managers':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $func = new \Functions();
                    $managers = $func->FetchManagers();
                    foreach ($managers as $manager)
                    {
                        $info = $func->GetUserInfo($manager);
                        $info = (strlen($info['last_name']) > 2) ? $info['first_name'].' '.$info['last_name'] : $info['first_name'];
                        $inline_keyboard->addRow(['text' => $info, 'callback_data' => "info_managers:$manager"]);
                    }
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "menu_managers:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Список менеджеров',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'info_managers':
                {
                    $inline_keyboard = new InlineKeyboard([]);
                    $inline_keyboard->addRow(['text' => 'Удалить', 'callback_data' => "delete_managers:$user_idx"]);
                    $inline_keyboard->addRow(['text' => 'Вернуться назад..', 'callback_data' => "show_managers:$user_idx"]);

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Удаление менеджера',
                        'message_id' => $msg_id,
                        'reply_markup' => $inline_keyboard,
                    ];

                    return Request::editMessageText($data_edit);
                }
                case 'add_managers':
                {
                    $user_id = ($user_idx) ? $user_idx : $message->getFrom()->getId();
                    $text = trim($message->getText(true));

                    $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
                    $notes = &$this->conversation->notes;
                    !is_array($notes) && $notes = [];

                    $data = [
                        'chat_id'   => $chat_id,
                    ];

                    $notes['awaiting_reply'] = $action;
                    $this->conversation->update();

                    if($user_idx) return true;

                    if (strlen($text) < 4) $data['text'] = 'Введите ID пользователя, например 123456';
                    else
                    {
                        $func = new \Functions();
                        if($func->AddManager($text) > 0)
                        {
                            $tell_user = [
                                'text' => "Вы были назначены менеджером.\nСписок доступных команд /help",
                                'chat_id' => $func->GetChatID($text)[0]
                            ];
                            Request::sendMessage($tell_user);
                            $data['text'] = 'Менеджер добавлен';
                            $this->conversation->stop();
                        }
                        else $data['text'] = 'Ошибка добавления, попробуйте еще раз';
                    }
                    return Request::sendMessage($data);
                }
                case 'delete_managers':
                {
                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'message_id' => $msg_id
                    ];
                    $func = new \Functions();
                    if($func->RevokeManager($user_idx) > 0) $data_edit['text'] = 'Менеджер удален';
                    else $data_edit['text'] = 'Ошибка удаления, попробуйте снова';
                    return Request::editMessageText($data_edit);
                }
                case 'exit_menu_managers':
                {
                    $inline_keyboard = new InlineKeyboard
                    (
                        [
                            ['text' => 'Добавить пользователя', 'callback_data' => "add_user:$user_idx"],
                            ['text' => 'Удалить пользователя', 'callback_data' => "delete_user:$user_idx"]
                        ],
                        [
                            ['text' => 'Добавить токен', 'callback_data' => "add_token:$user_idx"],
                            ['text' => 'Удалить токен', 'callback_data' => "delete_token:$user_idx"]
                        ],
                        [
                            ['text' => 'Администраторы', 'callback_data' => "menu_admin:$user_idx"],
                            ['text' => 'Менеджеры', 'callback_data' => "menu_managers:$user_idx"],
                        ]

                    );

                    $data_edit = [
                        'chat_id'   => $chat_id,
                        'text' => 'Управление пользователями и доступом',
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
