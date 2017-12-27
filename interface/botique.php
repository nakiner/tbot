<?php

use Telegram\Bot\Api;

require('../vendor/autoload.php');

define('BASE', dirname(__DIR__));
define('ROOT','/interface');

$telegram = new Api('325378485:AAEFKcg0d6IJV8n3BVXiTjBOoOx7pq_nLck', true);
$telegram->addCommand(app\commands\StartCommand::class);
$telegram->addCommand(app\commands\HelpCommand::class);
$telegram->addCommand(app\commands\WorkCommand::class);
$update = $telegram->commandsHandler(true);