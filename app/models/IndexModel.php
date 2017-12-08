<?php

namespace app\models;
use app\core\Model;

class IndexModel
{
    public static function getMenuItems()
    {
        return Model::object()->Get('menu');
    }

    public static function getNews($id = 0)
    {
        if($id > 0) return Model::object()->query("SELECT * FROM news WHERE id = '$id'");
        return Model::object()->query("SELECT * FROM news ORDER BY itemDate DESC");
    }

    public static function addNews($title, $text)
    {
        $now = Model::object()->query("SELECT NOW()")->fetch_assoc()['NOW()'];
        $user = $_SESSION['AUTH']['email'];
        return Model::object()->query("INSERT INTO news (itemTitle, itemContent, itemDate, itemBy) VALUES ('$title', '$text', '$now', '$user')");
    }
}
