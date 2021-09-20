<?php

class Visibility
{
    public static function getLevels()
    {
        return [
            0, // public
            1, // authentified
            2, // shared
            3, // owner
            4  // hidden
        ];
    }
}
