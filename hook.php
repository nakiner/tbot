<?php
/**
 * README
 * This configuration file is intended to run the bot with the webhook method.
 * Uncommented parameters must be filled
 *
 * Please note that if you open this file with your browser you'll get the "Input is empty!" Exception.
 * This is a normal behaviour because this address has to be reached only by the Telegram servers.
 */

// Настройка логгирования корневых ошибок

error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "bot-php.txt");

// Загрузка библиотек и функций
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Functions.php';
use Longman\TelegramBot\Telegram;

$config = new Config();
$func = new Functions();

try
{
    // Создание объекта класса
    $telegram = new Telegram($config->bot_api_key, $config->bot_username);

    // Установка путей для поиска команд
    $telegram->addCommandsPaths($config->commands_paths);

    // Импорт пользователей с привлегиями администраторов
    $telegram->enableAdmins($func->FetchAdmins());

    // Добавление подключения MySQL
    $telegram->enableMySql($config->mysql_credentials);

    // Логгирование ошибок
    //Longman\TelegramBot\TelegramLog::initErrorLog(__DIR__ . "/{$bot_username}_error.log");
    //Longman\TelegramBot\TelegramLog::initDebugLog(__DIR__ . "/{$bot_username}_debug.log");
    //Longman\TelegramBot\TelegramLog::initUpdateLog(__DIR__ . "/{$bot_username}_update.log");

    // Установка путей для заказчки и выгрузки файлов
    $telegram->setDownloadPath($config->download_path);
    $telegram->setUploadPath($config->upload_path);

    // Установка ограничений на количество отправляемых данных на сервера Telegram
    $telegram->enableLimiter();

    // Запуск процедуры проверки пользователя и обслуживание запроса
    $valid = $func->CheckUserAuth();
    if($valid) $telegram->handle();

}
catch (Longman\TelegramBot\Exception\TelegramException $e)
{
    // Логгирование ошибок телеграма
    //echo $e;
    //Longman\TelegramBot\TelegramLog::error($e);
}
catch (Longman\TelegramBot\Exception\TelegramLogException $e)
{
    // Логгирование ошибок запуска приожения
    //echo $e;
}
