<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 29/01/2018
 * Time: 12:02
 */

namespace GaeUtil;

use GuzzleHttp\Client;

class GoogleApis {

    static function analyticsReadonly() {

    }

    static function webmastersReadonly() {
        $scope = \Google_Service_Webmasters::WEBMASTERS_READONLY;
        $clients = Auth::getGoogleClientsByScope($scope);
        $accounts = [];
        foreach ($clients as $client) {
            $token = $client->getAccessToken();
            $sites = SearchConsole::getVerifiedSitesFromClient($client);
            foreach ($sites as $site) {
                $siteUrl = $site["siteUrl"];
                $accounts[$siteUrl]["siteUrl"] = $siteUrl;
                $accounts[$siteUrl]["access"][] = [
                    "access_token" => $token["access_token"],
                    "created" => $token["created"],
                    "expires_in" => $token["expires_in"],
                    "email" => $token["email"],
                    "picture" => $token["picture"],
                    "permissionLevel" => $site["permissionLevel"],
                ];
            }
        }
        return array_values($accounts);
    }

    /**
     * Return a client that uses default credentials.
     * See Auth::getGoogleClient for a version that works with user credentials.
     *
     * @return \Google_Client
     */
    static function getGoogleClient($logger_name = null) {
        if (is_null($logger_name)) {
            $logger_name = "Google_Client at " . Util::getModuleId();
        }
        $client = new \Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->setApplicationName(Util::getModuleId() . "@" . Util::getApplicationId());
        $client->setLogger(Logger::create($logger_name));
        if (Util::isDevServer()) {
            $http = self::getWindowsCompliantHttpClient();
            $client->setHttpClient($http);
        }
        return $client;
    }

    /**
     * @param $base_path
     * @return Client
     */
    static function getWindowsCompliantHttpClient($options = []) {
        // guzzle 6
        $default_options = [
            'exceptions' => false,
            'sink' => Files::getTempFilename()
        ];
        $options = array_merge_recursive($default_options);
        return new Client($options);
    }
}