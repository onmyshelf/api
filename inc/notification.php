<?php

class Notification
{
    /**
     * Save notification
     * @param  string $text
     * @param  string $type (optionnal)
     * @param  string $userId (optionnal)
     * @return void
     */
    public static function notify($text, $type='INFO', $userId=null)
    {
        if (is_null($userId))
            $userId = $GLOBALS['currentUserID'];

        return (new Database)->addNotification($userId, $type, $text);
    }
}
