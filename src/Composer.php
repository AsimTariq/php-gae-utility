<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 22/11/2017
 * Time: 17:53
 */

namespace GaeUtil;
use Composer\Autoload\ClassLoader;

class Composer {

    static function getVendorDir() {
        if (defined("COMPOSER_VENDOR_DIR")) {
            $vendorDir = COMPOSER_VENDOR_DIR;
        } else {
            $reflection = new \ReflectionClass(ClassLoader::class);
            $vendorDir = dirname(dirname($reflection->getFileName()));
        }
        return $vendorDir;
    }

    static function getComposerFilePath() {
        return realpath(self::getVendorDir() . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "composer.json");
    }

    static function getComposerData() {
        $content = file_get_contents(self::getComposerFilePath());
        return json_decode($content);
    }
}