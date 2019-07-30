<?php
/**
 * README
 * This file is intended to unset the webhook.
 * Uncommented parameters must be filled
 */

// Load composer
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Config.php';

$config = new Config();

try
{
    $telegram = new Longman\TelegramBot\Telegram($config->bot_api_key, $config->bot_username);

    Longman\TelegramBot\Request::setClient(new \GuzzleHttp\Client([
        'base_uri' => 'https://api.telegram.org',
        'proxy'    => $config->proxy_path,
    ]));

    $result = $telegram->deleteWebhook();
    if ($result->isOk()) echo $result->getDescription();
}
catch (Longman\TelegramBot\Exception\TelegramException $e)
{
    echo $e->getMessage();
}
