<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 20/11/2017
 * Time: 23:25
 */

namespace GaeUtil;

use google\appengine\api\app_identity\AppIdentityService;
use google\appengine\api\users\UserService;

class State {

    static function isDevServer() {
        return (strpos(getenv('SERVER_SOFTWARE'), 'Development') === 0);
    }

    static function status($links = []) {
        $data = [
            "application_id" => Util::getApplicationId(),
            "service" => Util::getModuleId(),
            "is_dev" => self::isDevServer(),
            "default_hostname" => AppIdentityService::getDefaultVersionHostname(),
            "is_admin" => false,
            "user" => false,
        ];
        $user = UserService::getCurrentUser();
        if ($user) {
            $data["user"] = $user;
            $data["logout"] = Auth::createLogoutURL();
        } else {
            $data["login"] = Auth::createLoginURL();
        }

        $data["links"] = $links;

        if (UserService::isCurrentUserAdmin()) {
            $data["is_admin"] = true;

            $data["errors"] = [];
            if (JWT::internalSecretIsConfigured()) {
                $data["internal_token"] = "Bearer " . JWT::getInternalToken();
            } else {
                $data["internal_token"] = false;
                $data["errors"][] = [
                    "message" => "Internal secret is not configured. Add jwt_internal_secret to a configuration file."
                ];
            }
            $data["external_token"] = "Bearer " . JWT::getExternalToken(Auth::getCurrentUserEmail(), Moment::ONEDAY);
            $data["composer"] = Composer::getComposerData();
        }
        return $data;
    }
}