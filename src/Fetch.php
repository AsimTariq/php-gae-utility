<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 07/02/2018
 * Time: 19:28
 */

namespace GaeUtil;

class Fetch {

    /**
     * Fetching an url secured by the Internal accesstoken.
     * Should be using guzzle just like other Google Api stuff
     *
     * @param $url
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    static public function secureUrl($url, $params = []) {
        if (is_array($url)) {
            $url = implode("/", $url);
        }
        $headers = [
            "Authorization: Bearer " . JWT::getInternalToken()
        ];
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => implode("\r\n", $headers)
            ],
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            )
        ];
        $stream_context = stream_context_create($opts);
        if (count($params)) {
            $url = $url . "?" . http_build_query($params);
        }
        syslog(LOG_INFO, __METHOD__ . " fetching: " . $url);
        $content = file_get_contents($url, false, $stream_context);
        $result = json_decode($content, JSON_OBJECT_AS_ARRAY);
        return $result;
    }

    /**
     * Wrapper that caches hits towards an service. Internal or otherwise.
     *
     * @param $url
     * @param array $params
     * @return mixed|\the
     * @throws \Exception
     */
    static public function secureUrlCached($url, $params = []) {
        if (is_array($url)) {
            $url = implode("/", $url);
        }
        $cacheKey = Cached::keymaker(__METHOD__, $url, $params);
        $cached = new Cached($cacheKey, false);
        if (!$cached->exists()) {
            $result = self::secureUrl($url, $params);
            syslog(LOG_INFO, "Returned " . count($result) . " rows.");
            $cached->set($result);
        }
        return $cached->get();
    }

    /**
     * For internal service on Google App Engine. Basically just expands application and service
     * to the correct domain on App Engine. This url is the fastest url to use internally on
     * App engine, so this method is prefered for service to service communication.
     *
     * @param $application_id
     * @param $service
     * @param $path
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    static public function internalService($application_id, $service, $path, $params = []) {
        $url = "https://$service-dot-$application_id.appspot.com" . $path;
        return self::secureUrl($url, $params);
    }

    /**
     * Wrapper for the Fetch::internal_service method that caches.
     *
     * @param $application_id
     * @param $service
     * @param $path
     * @param array $params
     * @return mixed|\the
     * @throws \Exception
     */
    static public function internalServiceCached($application_id, $service, $path, $params = []) {
        $url = "https://$service-dot-$application_id.appspot.com" . $path;
        return self::secureUrlCached($url, $params);
    }
}