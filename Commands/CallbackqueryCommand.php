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
     * @throws \Longman\TelegramBot\Exception\TelegramException
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
                return $this->user_callback($callback_query, $data[1], $data[0]);
            }
        }

        return Request::answerCallbackQuery(['callback_query_id' => $callback_query->getId()]);
    }
    /**
     * Обработка нажатия кнопки "Добавить пользователя"
     *
     * @param \Longman\TelegramBot\Entities\CallbackQuery $callback_query
     * @param string $user_id
     * @param string $action
     *
     * @throws \Longman\TelegramBot\Exception\TelegramException
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
            //echo $e;
        }
    }
}
