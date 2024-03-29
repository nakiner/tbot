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

use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * Пользовательская команда "/help"
 *
 * Команда, которая осуществляет вывод всех команд из всех секций с учет прав доступа.
 */
class HelpCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'help';

    /**
     * @var string
     */
    protected $description = 'Вывод доступных команд бота';

    /**
     * @var string
     */
    protected $usage = '/help или /help <command>';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $message     = $this->getMessage();
        $chat_id     = $message->getChat()->getId();
        $command_str = trim($message->getText(true));

        $safe_to_show = $message->getChat()->isPrivateChat();

        $data = [
            'chat_id'    => $chat_id,
            'parse_mode' => 'markdown',
        ];

        list($all_commands, $user_commands, $admin_commands) = $this->getUserAdminCommands();

        if ($command_str === '')
        {

            $func = new \Functions();
            $config = new \Config();
            $is_manager = $func->IsManager($message->getFrom()->getId());

            $data['text'] = '*Команды пользователей*:' . PHP_EOL;
            foreach ($user_commands as $user_command)
            {
                if(in_array($user_command->getName(), $config->manager_commands)) continue;
                $data['text'] .= '/' . $user_command->getName() . ' - ' . $user_command->getDescription() . PHP_EOL;
            }

            if($is_manager)
            {
                $data['text'] .= PHP_EOL . '*Команды менеджеров*:' . PHP_EOL;
                foreach ($user_commands as $user_command) {
                    if(!in_array($user_command->getName(), $config->manager_commands)) continue;
                    $data['text'] .= '/' . $user_command->getName() . ' - ' . $user_command->getDescription() . PHP_EOL;
                }
            }

            if ($safe_to_show && count($admin_commands) > 0) {
                $data['text'] .= PHP_EOL . '*Команды администраторов*:' . PHP_EOL;
                foreach ($admin_commands as $admin_command) {
                    $data['text'] .= '/' . $admin_command->getName() . ' - ' . $admin_command->getDescription() . PHP_EOL;
                }
            }

            $data['text'] .= PHP_EOL . 'Чтобы узнать детальную информацию, используйте: /help <command>';

            return Request::sendMessage($data);
        }

        $command_str = str_replace('/', '', $command_str);
        if (isset($all_commands[$command_str]) && ($safe_to_show || !$all_commands[$command_str]->isAdminCommand())) {
            $command      = $all_commands[$command_str];
            $data['text'] = sprintf(
                'Команда: %s (v%s)' . PHP_EOL .
                'Описание: %s' . PHP_EOL .
                'Использование: %s',
                $command->getName(),
                $command->getVersion(),
                $command->getDescription(),
                $command->getUsage()
            );

            return Request::sendMessage($data);
        }

        $data['text'] = 'Помощь недоступна: Команда /' . $command_str . ' не найдена';

        return Request::sendMessage($data);
    }

    /**
     * Получить список команд
     *
     * @throws TelegramException
     * @return Command[][]
     */
    protected function getUserAdminCommands()
    {
        // Only get enabled Admin and User commands that are allowed to be shown.
        /** @var Command[] $commands */
        $commands = array_filter($this->telegram->getCommandsList(), function ($command) {
            /** @var Command $command */
            return !$command->isSystemCommand() && $command->showInHelp() && $command->isEnabled();
        });

        $user_commands = array_filter($commands, function ($command) {
            /** @var Command $command */
            return $command->isUserCommand();
        });

        $admin_commands = array_filter($commands, function ($command) {
            /** @var Command $command */
            return $command->isAdminCommand();
        });

        ksort($commands);
        ksort($user_commands);
        ksort($admin_commands);

        return [$commands, $user_commands, $admin_commands];
    }
}
