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
use Longman\TelegramBot\DB;
use Longman\TelegramBot\Entities\Chat;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\CallbackQuery;

/**
 * Admin "/user" command
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
    protected $version = '1.3.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Conversation Object
     *
     * @var \Longman\TelegramBot\Conversation
     */
    protected $conversation;

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

            if (strlen($text) < 4)
            {
                $data['text'] = 'Введите имя пользователя, например user123';
            }
            else
            {
                $func = new \Functions();
                if($notes['awaiting_reply'] == 'add_user')
                {
                    if($func->AddAuth($text))
                    {
                        $data['text'] = 'Пользователь успешно добавлен';
                        $this->conversation->stop();
                    }
                    else $data['text'] = 'Ошибка добавления, попробуйте еще раз';
                }
                else if($notes['awaiting_reply'] == 'delete_user')
                {
                    if($func->RevokeAuth($text))
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

            $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
            $notes = &$this->conversation->notes;
            !is_array($notes) && $notes = [];

            $data = [
                'chat_id'   => $chat_id,
            ];

            $notes['awaiting_reply'] = $action;
            $this->conversation->update();

            if($user_idx) return true;

            if (strlen($text) < 4)
            {
                $data['text'] = 'Введите имя пользователя, например user123';
            }
            else
            {
                $func = new \Functions();
                if($notes['awaiting_reply'] == 'add_user')
                {
                    if($func->AddAuth($text))
                    {
                        $data['text'] = 'Пользователь успешно добавлен';
                        $this->conversation->stop();
                    }
                    else $data['text'] = 'Ошибка добавления, попробуйте еще раз';
                }
                else if($notes['awaiting_reply'] == 'delete_user')
                {
                    if($func->RevokeAuth($text))
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
}
