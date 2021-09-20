<?php

class Config
{
    /**
    * Get config value
    * @param  string $param Parameter name
    * @return mixed         Parameter value
    */
    public static function get(string $param)
    {
        return (new Database())->getConfig($param);
    }


    /**
     * Set config value
     * @param string $param Parameter name
     * @param mixed $value  Parameter value
     * @return bool         Success
     */
    public static function set(string $param, $value)
    {
        return (new Database())->setConfig($param, $value);
    }


    /**
     * Compare 2 versions numbers
     * @param  [type] $version1 [description]
     * @param  [type] $version2 [description]
     * @return bool           TRUE if version 1 > version 2
     */
    public static function compareVersions($version1, $version2)
    {
        // compare versions (use semantic versionning; ignore builds)
        $v1 = explode('.', preg_replace('/\+.*/', '', $version1));
        $v2 = explode('.', preg_replace('/\+.*/', '', $version2));

        // check major version
        if ($v1[0] < $v2[0]) {
            return false;
        }

        if ($v1[0] == $v2[0]) {
            // check minor version
            if ($v1[1] < $v2[1]) {
                return false;
            }

            if ($v1[1] == $v2[1]) {
                // check bugfix version

                // split z-{beta}...
                $p1 = explode('-', $v1[2]);
                $p2 = explode('-', $v2[2]);

                if ($p1[0] < $p2[0]) {
                    return false;
                } elseif ($p1[0] == $p2[0]) {
                    if (count($p1) > count($p2)) {
                        return false;
                    }
                    if (count($p1) == count($p2)) {
                        // bugfix are identical
                        if (count($p1) < 2) {
                            return false;
                        }

                        // compare pre-releases
                        $order = ['alpha','beta','rc'];

                        $p1[1] = array_search($p1[1], $order);
                        $p2[1] = array_search($p2[1], $order);

                        if ($p1[1] < $p2[1]) {
                            return false;
                        }
                        if ($p1[1] == $p2[1]) {
                            // compare pre-release numbers
                            if ($v1[3] <= $v2[3]) {
                                return false;
                            }
                        }
                    }
                }
            }
        }

        return true;
    }
}
