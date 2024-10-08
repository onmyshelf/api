<?php

class Storage extends GlobalStorage
{
    /**
     * Return media path from URL
     * @param  string $url
     * @return string Real path
     */
    public static function urlToPath($url)
    {
        return preg_replace('/^media:\/\//', MEDIA_DIR.'/', $url);
    }


    /**
     * Return media URL from path
     * @param  string $path
     * @return string Media URL
     */
    public static function pathToUrl($path)
    {
        return preg_replace('/^'.str_repeat('.',strlen(MEDIA_DIR)).'/', 'media:/', $path);
    }


    /**
     * Glob files
     *
     * @param  string $search
     * @return array  Results
     */
    public static function glob($search)
    {
        $medias = [];
        $files = glob(MEDIA_DIR."/$search");
        foreach ($files as $file) {
            $medias[] = self::pathToUrl($file);
        }

        return $medias;
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

        // create thumbnails (ignore errors)
        self::createThumbnails($path);

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

        // move file from system temporary path to our upload folder path
        try {
            set_error_handler(function($errno, $errstr) {
                Logger::error("Storage: Failed to move uploaded file: ".$errstr);
            });

            Logger::debug("Storage: moving temp file: ".$_FILES[$field]['tmp_name']);
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

        // create thumbnails (ignore errors)
        self::createThumbnails($path);

        return 'media://'.$path;
    }


    /**
     * Get thumbnails of a file
     * @param  string $media Media path
     * @return array  Array of thumbnails
     */
    public static function getThumbnails($media)
    {
        // check if media is correct
        if (substr((string)$media, 0, 8) !== 'media://') {
            return [];
        }

        // remove media url prefix
        $media = preg_replace('/^media:\/\//', '', $media);

        // get media information
        $pathinfo = pathinfo($media);

        $thumbnails = [];
        $sizes = [ 'small', 'normal' ];
        foreach ($sizes as $size) {
            if (isset($pathinfo['extension'])) {
                $extension = '.' . $pathinfo['extension'];
            } else {
                $extension = '';
            }

            $thumbnail = $pathinfo['dirname'].'/'.$pathinfo['filename'].'-'.$size.$extension;
            if (file_exists(MEDIA_DIR.'/'.$thumbnail)) {
                $thumbnails[$size] = 'media://'.$thumbnail;
            }
        }

        return $thumbnails;
    }


    /**
     * Create thumbnails
     * @param  string  $path  Image path
     * @return bool    Success
     */
    public static function createThumbnails($media)
    {
        // get media information
        $path = MEDIA_DIR.'/'.$media;
        $pathinfo = pathinfo($path);

        // get type of media
        switch (exif_imagetype($path)) {
            case IMAGETYPE_JPEG:
                $format = 'jpeg';
                break;

            case IMAGETYPE_PNG:
                $format = 'png';
                break;

            default:
                // not an image
                Logger::debug('No thumbnails created for media: '.$media);
                return false;
                break;
        }

        // import image
        $sourceImage = call_user_func('imagecreatefrom'.$format, $path);
        $orgWidth = imagesx($sourceImage);
        $orgHeight = imagesy($sourceImage);

        // generate thumbnails
        $sizes = [ 'small' => 300, 'normal' => 800 ];
        foreach ($sizes as $size => $thumbHeight) {
            // if thumbnail is bigger than original image, keep the original size
            if ($thumbHeight > $orgHeight) {
                $thumbWidth = $orgWidth;
                $thumbHeight = $orgHeight;
            } else {
                // calculates thumbnail width
                $thumbWidth = floor($orgWidth * ($thumbHeight / $orgHeight));
            }

            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

            // add transparency for PNG files (avoid black backgrounds)
            if ($format == 'png') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparentColor = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
                imagefill($thumbnail, 0, 0, $transparentColor);
            }
            imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $orgWidth, $orgHeight);

            if (isset($pathinfo['extension'])) {
                $extension = '.' . $pathinfo['extension'];
            } else {
                $extension = '';
            }

            $thumbnailPath = $pathinfo['dirname'].'/'.$pathinfo['filename'].'-'.$size.$extension;
            call_user_func_array('image'.$format, [$thumbnail, $thumbnailPath]);

            imagedestroy($thumbnail);
        }

        imagedestroy($sourceImage);
        return true;
    }


    public static function unzip($path, $deleteArchive = false)
    {
        if (!$path || strlen($path) == 0) {
            return false;
        }

        // convert URL to path if needed
        $path = self::urlToPath($path);
        $pathinfo = pathinfo($path);

        $destination = $pathinfo['dirname'].'/'.$pathinfo['filename'].'/';

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            Logger::error("Cannot extract ZIP file: $path");
            return false;
        }
        
        $zip->extractTo($destination);
        $zip->close();

        if ($deleteArchive) {
            self::delete($path);
        }

        return $destination;
    }


    /**
     * Move file
     * @param  string $src  Source path
     * @param  string $dest Destination path
     * @return boolean      Success
     */
    public static function move($src, $dest = '')
    {
        if ($dest == '') {
            $dest = self::prepare($src);
            if (!$dest) {
                return false;
            }
        }
        
        if (!rename($src, MEDIA_DIR.'/'.$dest)) {
            Logger::error("Cannot rename $src to $dest");
            return false;
        }

        // create thumbnails (ignore errors)
        self::createThumbnails($dest);

        return $dest;
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

        $path = self::urlToPath($path);

        // delete file/directory
        try {
            set_error_handler(function($errno, $errstr) {
                Logger::error("Storage: Failed to delete path: ".$errstr);
            });

            Logger::debug("Storage: delete $path");

            if (is_dir($path)) {
                self::rmdir($path);
            } else {
                unlink($path);
            }

            restore_error_handler();
        } catch (Throwable $t) {
            Logger::error("Storage: Failed to delete path: ".$path);
            return false;
        }

        // double check if file is deleted
        return !self::exists($path);
    }


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

        // security: remove extensions with a "?"
        // sometimes, files from the web are like ....jpg?v=123456
        $extension = '.' . preg_replace('/\?.*/', '', $extension);

        // security: trunk too long extensions
        if (strlen($extension) > 13) {
            $extension = substr($extension, 0, 13);
        } else {
            if ($extension == '.') {
                $extension = '';
            }
        }

        // generate a random name
        while (true) {
            $file = bin2hex(random_bytes(12)).$extension;

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
     * Recursively delete directory
     *
     * @param string $path
     * @return bool  Success
     */
    private static function rmdir($path) {
        if (!file_exists($path)) {
            return true;
        }

        if (!is_dir($path)) {
            return unlink($path);
        }

        foreach (scandir($path) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!self::rmdir($path . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($path);
    }
}
