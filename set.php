<?php
/**
 * README
 * This file is intended to set the webhook.
 * Uncommented parameters must be filled
 */

// Load composer
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Config.php';

$config = new Config();

try
{
    $telegram = new Longman\TelegramBot\Telegram($config->bot_api_key, $config->bot_username);
    $result = $telegram->setWebhook($config->url);

    // Для самоподписанных сертификатов
    //$certificate_path = '/path/to/cert.crt';
    //$result = $telegram->setWebhook($config->url, ['certificate' => $certificate_path]);

    if ($result->isOk()) echo $result->getDescription();
}
catch (Longman\TelegramBot\Exception\TelegramException $e)
{
    echo $e->getMessage();
}
