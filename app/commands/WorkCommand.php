<?php

namespace app\commands;
use Telegram\Bot\Commands\Command;

class WorkCommand extends Command
{
    protected $name = 'work';
    protected $description = 'Запуск работы';

    public function handle($arguments)
    {
        $this->replyWithMessage(['text' => 'Функция будет реализована в следующем обновлении.']);
    }
}
