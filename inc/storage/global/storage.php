<?php

abstract class GlobalStorage
{
    abstract public static function urlToPath($url);
    abstract public static function pathToUrl($path);
    abstract public static function glob($search);
    abstract public static function copy($url);
    abstract public static function download($url);
    abstract public static function moveUploadedFile($field);
    abstract public static function getThumbnails($media);
    abstract public static function createThumbnails($media);
    abstract public static function unzip($path, $deleteArchive);
    abstract public static function move($src, $dest);
    abstract public static function delete($path);
}
