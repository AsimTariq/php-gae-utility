<?php

namespace GaeUtil;

use Composer\Autoload\ClassLoader;
use google\appengine\api\app_identity\AppIdentityService;
use google\appengine\util\StringUtil;
use Psr\Http\Message\RequestInterface;

class Util {

    static public function quoteArray($array) {
        $quotedvalues = [];
        foreach ($array as $value) {
            $quotedvalues[] = "'" . $value . "'";
        }
        return implode(",", $quotedvalues);
    }

    /**
     * Used to inspect shit
     *
     * @param string $filename
     */
    static function saveIncludedFiles($filename, $fromFile) {
        if (file_exists($filename)) {
            $all_included_files = json_decode(file_get_contents($filename), JSON_OBJECT_AS_ARRAY);
        } else {
            $all_included_files = array();
        }
        foreach (get_included_files() as $includedFile) {
            if (!isset($all_included_files[$includedFile])) {
                $all_included_files[$includedFile] = array();
            }
            if (!in_array($fromFile, $all_included_files[$includedFile])) {
                array_push($all_included_files[$includedFile], $fromFile);
            }
        }
        file_put_contents($filename, json_encode($all_included_files));
    }

    static function dump($variable) {
        echo("<pre>");
        print_r($variable);
        echo("</pre>");
    }

    static function getProtocol() {
        return "http" . ($_SERVER["HTTPS"] === "on" ? "s" : "") . "://";
    }

    static function getHomeUrl() {
        return self::getProtocol() . $_SERVER["HTTP_HOST"];
    }

    static function getFullPath($path, $query = "") {
        if (is_array($query)) {
            $query = "?" . http_build_query($query);
        }
        return self::getHomeUrl() . $path . $query;
    }

    static function offset_array($data, $offset, $limit) {
        $record_count = count($data);
        $offset_data = [];
        for ($i = 0; $i <= $record_count; $i++) {
            if ($i >= $offset && count($offset_data) < $limit && isset($data[$i])) {
                $offset_data[] = $data[$i];
            }
        }
        return $offset_data;
    }

    static function sortByKey(&$data, $key) {
        if ($key[0] === "-") {
            $asc = false;
            $key = ltrim($key, '-');
        } else {
            $asc = true;
        }
        return usort($data, function ($a, $b) use ($key, $asc) {
            if ($asc) {
                return strcmp($a[$key], $b[$key]);
            } else {
                return strcmp($b[$key], $a[$key]);
            }
        });
    }

    /**
     * StartsWith
     * Tests if a text starts with an given string.
     *
     * @param     string
     * @param     string
     * @return    bool
     */
    static function startsWith($haystack, $needle) {
        return strpos($haystack, $needle) === 0;
    }

    static function cmdline($message) {
        echo $message . PHP_EOL;
    }

    static function getRandomArrayElements($input_array, $min = 1, $max = 3) {
        $number_for_client = rand($min, $max);
        $random = array_rand($input_array, $number_for_client);
        $output_array = [];
        if ($number_for_client === 1) {
            $output_array[] = $input_array[$random];
        } else {
            foreach ($random as $key) {
                $output_array[] = $input_array[$key];
            }
        }
        return $output_array;
    }

    static function getRootDomain($siteurl) {
        $url_host = parse_url($siteurl, PHP_URL_HOST);
        $parts = explode(".", $url_host);
        return $parts[count($parts) - 2];
    }

    static function getVendorDir() {
        if (defined("COMPOSER_VENDOR_DIR")) {
            $vendorDir = COMPOSER_VENDOR_DIR;
        } else {
            $reflection = new \ReflectionClass(ClassLoader::class);
            $vendorDir = dirname(dirname($reflection->getFileName()));
        }
        return $vendorDir;
    }

    static function redirect($url) {
        header('Location: ' . $url);
        exit;
    }

    static function link($url, $text) {
        return "<a href='$url'>$text</a>";
    }

    /**
     * @return bool|string
     */
    static function resolveFilePath() {
        $filepath = implode(DIRECTORY_SEPARATOR, func_get_args());
        $filepath_real = realpath($filepath);
        return $filepath_real;
    }

    static function getModuleId() {
        return getenv("CURRENT_MODULE_ID");
    }

    static function getApplicationId() {
        return AppIdentityService::getApplicationId();

    }

    /**
     * @param $array_name
     * @param $array
     * @param $required_keys
     * @throws \Exception
     */
    static function keysExistsOrFail($array_name, $array, $required_keys) {
        if (is_array($required_keys)) {
            foreach ($required_keys as $key) {
                self::keysExistsOrFail($array_name, $array, $key);
            }
        } else {
            if (!isset($array[$required_keys])) {
                throw new \Exception("$array_name need the '$required_keys' parameter");
            }
        }

    }

    static function isArrayOrFail($array_name, $array) {
        if (!is_array($array)) {
            throw new \Exception("$array_name should be an array.");
        }
    }

    /**
     * @return bool|string
     */
    static function pathmaker() {
        $path_parts = [];
        foreach (func_get_args() as $string) {
            $string = str_replace("\\", "/", $string);
            $parts = explode("/", $string);
            $path_parts = array_merge($path_parts, $parts);
        }
        $path = implode(DIRECTORY_SEPARATOR, $path_parts);
        $path = realpath($path);
        return $path;
    }

    static function getDomainFromEmail($email) {
        // make sure we've got a valid email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // split on @ and return last value of array (the domain)
            $parts = explode('@', $email);
            return array_pop($parts);
        } else {
            return false;
        }
    }

    static function getHostFromURL($url) {
        return parse_url($url, PHP_URL_HOST);
    }

    static function getLocalPartFromEmail($email) {
        // make sure we've got a valid email
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // split on @ and return last value of array (the domain)
            $parts = explode('@', $email);
            return array_shift($parts);
        } else {
            return false;
        }
    }

    static function print_pre($mixed) {
        echo("<pre>" . print_r($mixed, 1) . "</pre>");
    }

    static function envReplace($searh, $replace, $varname) {
        $value = getenv($varname);
        $value = str_replace($searh, $replace, $value);
        putenv("$varname=$value");
    }

    /**
     * Check if we are on the Dev server
     *
     * @return bool
     */
    static function isDevServer() {
        return (strpos(getenv('SERVER_SOFTWARE'), 'Development') === 0);
    }

    /**
     * Check if we are running on CLI
     *
     * @return bool
     */
    static function isCli() {
        if (!self::onAppEngine() && php_sapi_name() === "cli") {
            return true;
        }
        return false;
    }

    /**
     * Determines if this an App Engine instance, by accessing the
     * SERVER_SOFTWARE environment variable (prod) or the APPENGINE_RUNTIME
     * environment variable (dev).
     *
     * Copied from App Engine functions
     *
     * @return true if this an App Engine Instance, false otherwise
     */
    public static function onAppEngine() {
        $appEngineProduction = isset($_SERVER['SERVER_SOFTWARE']) &&
            0 === strpos($_SERVER['SERVER_SOFTWARE'], 'Google App Engine');
        if ($appEngineProduction) {
            return true;
        }
        $appEngineDevAppServer = isset($_SERVER['APPENGINE_RUNTIME']) &&
            $_SERVER['APPENGINE_RUNTIME'] == 'php';
        if ($appEngineDevAppServer) {
            return true;
        }
        return false;
    }

    /**
     * Are we running in Google AppEngine?
     * return bool
     */
    public static function isAppEngine() {
        return (isset($_SERVER['SERVER_SOFTWARE']) &&
            strpos($_SERVER['SERVER_SOFTWARE'], 'Google App Engine') !== false);
    }

    public static function getParamsFromRequest(RequestInterface $request) {
        $post_params = [];
        $get_params = [];
        parse_str($request->getBody()->getContents(), $post_params);
        parse_str($request->getUri()->getQuery(), $get_params);
        $query_params = array_merge_recursive($post_params, $get_params);
        return $query_params;
    }

    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param boole $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    public static function getGravatar($email, $s = 80, $d = 'mm', $r = 'g', $img = false, $atts = array()) {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val)
                $url .= ' ' . $key . '="' . $val . '"';
            $url .= ' />';
        }
        return $url;
    }

    public static function base64UrlEncode($input) {
        $encoded = StringUtil::base64UrlEncode($input);
        return trim($encoded, ",");

    }

    public static function base64UrlDecode($input) {
        return StringUtil::base64UrlDecode($input);
    }
}


