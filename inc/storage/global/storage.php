<?php

abstract class GlobalStorage
{
    abstract public static function path($url);
    abstract public static function copy($url);
    abstract public static function moveUploadedFile($field);
    abstract public static function getThumbnails($media);
    abstract public static function createThumbnails($media);
    abstract public static function unzip($path, $deleteArchive);
    abstract public static function move($src, $dest);
    abstract public static function delete($path);
}
