<?php

abstract class GlobalStorage
{
    abstract public static function urlToPath($url);
    abstract public static function pathToUrl($path);
    abstract public static function glob($search);
    abstract public static function copy($url);

    /**
     * Download a file to media directory
     * @param  string $url URL of file
     * @return string Media URL, FALSE if error
     */
    public static function download($url)
    {
        // if already in media library, do nothing
        if (substr($url, 0, 8) == 'media://') {
            return $url;
        }

        return self::copy($url);
    }

    abstract public static function moveUploadedFile($field);
    abstract public static function getThumbnails($media);
    abstract public static function createThumbnails($media);
    abstract public static function unzip($path, $deleteArchive);
    abstract public static function move($src, $dest);
    abstract public static function delete($path);
}
