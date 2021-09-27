<?php

class Storage
{
    /**
     * Check if path exists in media
     * @param  string $path Path (relative to media directory)
     * @return bool   File/directory exists
     */
    private static function exists($path)
    {
        return file_exists(MEDIA_DIR.'/'.$path);
    }


    /**
     * Prepare to store a media
     * @param  string $filename
     * @return string Path (relative to media directory), FALSE if error
     */
    private static function prepare($filename)
    {
        if (strlen($filename) == 0) {
            return false;
        }

        // get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        // security: trunk too long extensions
        if (strlen($extension) > 12) {
            $extension = substr($extension, 0, 12);
        }

        // generate a random name
        while (true) {
            $file = bin2hex(random_bytes(12)).'.'.$extension;

            // put it in a subdir with the first character
            $dir = substr($file, 0, 1);
            $path = $dir.'/'.$file;

            if (!self::exists($path)) {
                break;
            }
        }

        try {
            set_error_handler(function($errno, $errstr) {
                Logger::error("Storage: Failed to create directory: ".$errstr);
            });

            // create directory if not exists
            if (!self::exists($dir)) {
                mkdir(MEDIA_DIR.'/'.$dir);
            }

            restore_error_handler();
        } catch (Throwable $t) {
            Logger::error("Storage: Failed to create directory ".$dir);
            return false;
        }

        // double check if directory OK
        if (!self::exists($dir)) {
            return false;
        }

        return $path;
    }


    /**
     * Return media path
     * @param  string $url
     * @return string Real path
     */
    public static function path($url)
    {
        return preg_replace('/^media:\/\//', MEDIA_DIR.'/', $url);
    }


    /**
     * Copy a file to media directory
     * @param  string $url URL of file
     * @return string Media URL, FALSE if error
     */
    public static function copy($url)
    {
        // prepare destination path
        $path = self::prepare($url);
        if (!$path) {
            return false;
        }

        try {
            set_error_handler(function($errno, $errstr) {
                Logger::error("Storage: Failed to copy: ".$errstr);
            });

            // copy file to media
            copy($url, MEDIA_DIR.'/'.$path);

            restore_error_handler();
        } catch (Throwable $t) {
            Logger::error("Storage: Failed to copy from: ".$url." to: ".$path);
            return false;
        }

        // double check if file OK
        if (!self::exists($path)) {
            return false;
        }

        return 'media://'.$path;
    }


    /**
     * Move uploaded file into media directory
     * @param  string $field Key of the $_FILES array
     * @return string Media URL, FALSE if error
     */
    public static function moveUploadedFile($field = 'file')
    {
        if (!isset($_FILES[$field])) {
            return false;
        }

        $filename = $_FILES[$field]['name'];

        $path = self::prepare($filename);
        if (!$path) {
            return false;
        }

        // check file size '5MB'
        if ($_FILES[$field]['size'] > 5000000) {
            Logger::error("Storage: file too big: ".$filename);
            return false;
        }

        // move file from system temporary path to our upload folder path
        try {
            set_error_handler(function($errno, $errstr) {
                Logger::error("Storage: Failed to move uploaded file: ".$errstr);
            });

            move_uploaded_file($_FILES[$field]['tmp_name'], MEDIA_DIR.'/'.$path);

            restore_error_handler();
        } catch (Throwable $t) {
            Logger::error("Storage: Failed to move uploaded file from: ".$filename." to: ".$path);
            return false;
        }

        // double check if file OK
        if (!self::exists($path)) {
            return false;
        }

        return 'media://'.$path;
    }


    /**
     * Delete media file
     * @param  string $path Path of the file, relative to media directory
     * @return boolean      Success
     */
    public static function delete($path)
    {
        if (!$path || strlen($path) == 0) {
            return false;
        }

        // delete file
        try {
            set_error_handler(function($errno, $errstr) {
                Logger::error("Storage: Failed to delete path: ".$errstr);
            });

            unlink(MEDIA_DIR.'/'.$path);

            restore_error_handler();
        } catch (Throwable $t) {
            Logger::error("Storage: Failed to delete path: ".$path);
            return false;
        }

        // double check if file is deleted
        return !self::exists($path);
    }
}
