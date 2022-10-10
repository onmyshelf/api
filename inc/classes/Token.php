<?php

class Token
{
    /*
     *  Static methods
    */

    /**
     * Generate a token
     * @param  integer $size Size (optional)
     * @return string  Success
     */
    public static function generate(int $size=30)
    {
        return bin2hex(random_bytes($size));
    }


    /**
     * Revoke token
     * @param  string  $token
     * @return boolean Success
     */
    public static function revoke($token)
    {
        return (new Database)->deleteToken($token);
    }
}
