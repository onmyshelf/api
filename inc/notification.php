<?php

class Notification
{
    /**
     * Save notification
     * @param  string $text
     * @param  string $type (optionnal)
     * @return void
     */
    public static function notify(string $text, $type='INFO')
    {
        (new Database)->addNotification($type, $text);
    }
}
