<?php

class Config
{
    /**
     * API Ключ
     *
     * @var string
     */
    public $bot_api_key;

    /**
     * Имя бота
     *
     * @var string
     */
    public $bot_username;

    /**
     * Данные для подключения к СУБД
     *
     * @var array
     */
    public $mysql_credentials;

    /**
     * Массив путей команд
     *
     * @var array
     */
    public $commands_paths;

    /**
     * Путь для скачивания файлов
     *
     * @var string
     */
    public $download_path;

    /**
     * Путь для выгрузки файлов
     *
     * @var string
     */
    public $upload_path;

    /**
     * Массив для данных из файла
     *
     * @var array
     */
    public $config_vars;

    /**
     * Массив для хранения команд менеджеров
     *
     * @var array
     */
    public $manager_commands;

    function __construct()
    {
        $this->read_config();

        $this->bot_api_key = $this->config_vars['bot_api_key'];
        $this->bot_username = $this->config_vars['bot_username'];

        $this->mysql_credentials = [
            'host'     => $this->config_vars['db_host'],
            'user'     => $this->config_vars['db_user'],
            'password' => $this->config_vars['db_password'],
            'database' => $this->config_vars['db_name'],
        ];

        $this->manager_commands = [
            'manage'
        ];

        $this->commands_paths = [
            __DIR__ . '/Commands/',
        ];

        $this->download_path = __DIR__.$this->config_vars['download_path'];
        $this->upload_path = __DIR__.$this->config_vars['upload_path'];
    }

    /**
     * Осуществляет чтение конфигурационного файла
     */
    function read_config()
    {
        $data = file_get_contents("settings.cfg");

        $lines = explode("\r\n", $data);
        $lines = (count($lines) > 1) ? $lines : explode("\n", $data);

        foreach ($lines as $line)
        {
            if(strpos($line, '=') === false) continue;
            $key = explode('=', $line)[0];
            $value = explode('=', $line)[1];
            $this->config_vars[$key] = $value;
        }
    }

    /**
     * Осуществляет запись параметров
     * в файл конфигурации
     */
    function write_config($array)
    {
//        $data = (file_exists('settings.cfg')) ? file_get_contents("settings.cfg") : rename('settings_default.cfg', 'settings.cfg');
//        $lines = explode("\r\n", $data);
    }

}