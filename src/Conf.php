<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 15/11/2017
 * Time: 17:42
 */

namespace GaeUtil;

use Noodlehaus\Config;

class Conf {

    const GAEUTIL_FILENAME = "gaeutil.json";
    const GAEDEV_FILENAME = "gaedev.json";
    const CONF_GLOBAL_CONFIG_FILENAME = "global_config_location";
    const CONFIG_DIR = "config";

    /**
     * @return Config
     */
    static function getInstance() {
        static $instance;

        if (is_null($instance)) {
            /**
             * Reads the default config-path into the config instance.
             * Trying from several locations. Fallback to liberary.
             */
            $alternative_paths = [
                self::getConfFilepath(self::GAEUTIL_FILENAME),
                self::getConfFilepath("app_config.json"),
                Util::resolveFilePath(dirname(__FILE__), "..", self::GAEUTIL_FILENAME)
            ];
            foreach ($alternative_paths as $config_file_path) {
                if (file_exists($config_file_path)) {
                    $instance = new Config($config_file_path);
                    break;
                }
            }


            $cached = new Cached(self::getCacheKey(), Util::isCli());
            if (!$cached->exists()) {
                $secret_data = [
                    "global_config_is_loaded" => false
                ];
                /**
                 * Fetching encoded microservice secrets and storing them in cache.
                 */
                $global_config_file = $instance->get(self::CONF_GLOBAL_CONFIG_FILENAME, false);
                try {
                    if ($global_config_file) {
                        syslog(LOG_INFO, "Trying to fetch global config: " . $global_config_file);
                        $array_w_secrets = Files::getJson($global_config_file, false);
                        if ($array_w_secrets) {
                            $data = Secrets::decryptDotSecrets($array_w_secrets);
                            $secret_data = array_merge_recursive($secret_data, $data);
                        }
                    }
                    /**
                     * Creating internal secret for service to frontend communication.
                     */
                    $secret_data[JWT::CONF_EXTERNAL_SECRET_NAME] = JWT::generateSecret();
                    $cached->set($secret_data);
                } catch (\Exception $e) {
                    syslog(LOG_WARNING, "Decryption of $global_config_file failed with message: " . $e->getMessage());
                }
            }
            if ($cached->exists()) {
                foreach ($cached->get() as $key => $value) {
                    $key = trim($key, ".");
                    $instance->set($key, $value);
                }
            }
            if (Util::isDevServer() || Util::isCli()) {
                self::addConfigFile(self::GAEDEV_FILENAME);
            }
        }
        return $instance;
    }

    static function get($key, $default = null) {
        $env_var = getenv(strtoupper($key));
        if ($env_var) {
            return $env_var;
        } else {
            $instance = self::getInstance();

            return $instance->get($key, $default);
        }
    }

    static function addConfigFile($filename) {
        $filepath = self::getConfFilepath($filename);
        try {
            $json_content = Files::getJson($filepath);
            if ($json_content) {
                foreach ($json_content as $key => $value) {
                    self::getInstance()->set($key, $value);
                }
                return true;
            }
        } catch (\Exception $exception) {
            syslog(LOG_WARNING, "Error adding $filename from config dir. " . $exception->getMessage());
        }
        return false;
    }

    /**
     * @return string
     */
    static function getCacheKey() {
        return Cached::keymaker(__METHOD__, Util::getModuleId());
    }

    static function getConfFilepath($filename) {
        $vendorDir = Composer::getVendorDir();
        $conf_filepath_real = Util::resolveFilePath($vendorDir, "..", self::CONFIG_DIR, $filename);
        if (!file_exists($conf_filepath_real)) {
            $conf_filepath_real = Util::resolveFilePath(dirname(__FILE__), "..", self::CONFIG_DIR, $filename);
        }
        return $conf_filepath_real;
    }

    static function getGaeUtilJsonPath($project_directory) {
        return Util::resolveFilePath($project_directory, Conf::CONFIG_DIR, Conf::GAEUTIL_FILENAME);
    }

    static function getConfFolderPath($project_directory) {
        return Util::resolveFilePath($project_directory, Conf::CONFIG_DIR);
    }
}