<?php

namespace app\commands;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{

    protected $name = 'start';

    protected $description = 'Запуск бота';

    public function handle($arguments)
    {
        $commands = $this->telegram->getCommands();
        $this->replyWithMessage(['text' => 'Здравствуй']);
    }
}
