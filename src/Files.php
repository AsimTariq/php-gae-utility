<?php

namespace GaeUtil;

use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use Google\Cloud\Storage\StorageClient;

/**
 * Description of GaeUtil
 *
 * @author michael
 */
class Files {

    /**
     * Downloads file and returns the temporary filename.
     *
     * @param $url
     * @return bool|string
     */
    static function downloadToTempfile($url) {
        $download_path = Files::getTempFilename();
        $fp = fopen($download_path, 'w+');
        fwrite($fp, file_get_contents($url));
        fclose($fp);
        return $download_path;
    }

    /**
     * @return StorageClient
     */
    static function getStorageClient() {
        $options = [];
        if (Util::isDevServer()) {
            $httpClient = GoogleApis::getWindowsCompliantHttpClient();
            $options["httpHandler"] = new Guzzle6HttpHandler($httpClient);
        }
        $storageClient = new StorageClient($options);
        return $storageClient;
    }

    static function isStorageFilename($filename) {
        $scheme = parse_url($filename, PHP_URL_SCHEME);
        return ($scheme === "gs");
    }

    static function ensureStreamwrappersRegistered($filename) {
        if (self::isStorageFilename($filename) && !in_array('gs', stream_get_wrappers())) {
            $client = self::getStorageClient();
            $client->registerStreamWrapper();
        }
    }

    /**
     * Objects are the individual pieces of data that you store in Google Cloud Storage.
     * This function let you fetch object from Cloud Storage from the devserver.
     *
     * @param $filename
     * @return bool|\Google\Cloud\Storage\StorageObject
     */
    static function getStorageObject($filename) {
        $scheme = parse_url($filename, PHP_URL_SCHEME);
        $bucket = parse_url($filename, PHP_URL_HOST);
        $path = parse_url($filename, PHP_URL_PATH);
        $object_name = trim($path, "/");
        if ($scheme == "gs") {
            $client = self::getStorageClient();
            $bucket = $client->bucket($bucket);
            $object = $bucket->object($object_name);
            return $object;
        } else {
            syslog(LOG_WARNING, "Trying to get storage object on invalid url: $filename");
            return false;
        }
    }

    static function getStorageJson($filename, $default = null) {
        $object = self::getStorageObject($filename);
        if ($object) {
            $json_string = $object->downloadAsString();
            $array_with_content = json_decode($json_string, JSON_OBJECT_AS_ARRAY);
            return $array_with_content;
        } else {
            return $default;
        }
    }

    static function ensureDirectory($directory) {
        if (!file_exists($directory)) {
            mkdir($directory);
            syslog(LOG_INFO, "Created new directory: $directory");
        }
    }

    static function getJson($filename, $default = null) {
        self::ensureStreamwrappersRegistered($filename);
        if (Util::isDevServer() && self::isStorageFilename($filename)) {
            $data = self::getStorageJson($filename, $default);
        }
          else if (file_exists($filename)) {
            $json = file_get_contents($filename);
            $data = json_decode($json, JSON_OBJECT_AS_ARRAY);
        } else {
            syslog(LOG_INFO, __METHOD__ . " using default value for $filename");
            $data = $default;
        }
       return $data;
    }

    static function putJson($filename, $data) {
        self::ensureStreamwrappersRegistered($filename);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    static function getFilenames($from_directory) {
        $filenames = [];
        foreach (scandir($from_directory) as $filename) {
            $full_path = $from_directory . DIRECTORY_SEPARATOR . $filename;
            if (is_file($full_path)) {
                $filenames[] = $filename;
            }
        }
        return $filenames;
    }

    static function getTempFilename() {
        $module = Util::getModuleId();
        return tempnam(sys_get_temp_dir(), $module);
    }
}
