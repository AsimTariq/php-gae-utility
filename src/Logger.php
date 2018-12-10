<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 21/11/2017
 * Time: 16:40
 */

namespace GaeUtil;

use Monolog\Handler\SyslogHandler;

class Logger {

    /**
     * @param $name
     * @return \Monolog\Logger
     */
    static function create($name) {
        $logger = new \Monolog\Logger($name);
        $logger->pushHandler(new SyslogHandler($name));
        return $logger;
    }
}
