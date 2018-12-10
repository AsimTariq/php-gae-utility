<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 15/11/2017
 * Time: 16:42
 */

namespace GaeUtil;

use google\appengine\api\app_identity\AppIdentityService;
use google\appengine\api\users\UserService;

class Auth {

    const PROVIDER_GOOGLE = "google";

    static function getUserDataFromGoogleClient(\Google_Client $client) {
        $service = new \Google_Service_Oauth2($client);
        $user_info = $service->userinfo_v2_me->get();
        $user_data = [
            "id" => $user_info->getId(),
            "name" => $user_info->getName(),
            "given_name" => $user_info->getGivenName(),
            "family_name" => $user_info->getFamilyName(),
            "email" => $user_info->getEmail(),
            "verified_email" => $user_info->getVerifiedEmail(),
            "gender" => $user_info->getGender(),
            "picture" => $user_info->getPicture(),
            "locale" => $user_info->getLocale(),
            "scopes" => $client->getScopes(),
            "signup_application" => Util::getApplicationId(),
            "signup_service" => Util::getModuleId()
        ];
        $access_token = $client->getAccessToken();
        foreach (["access_token", "token_type", "expires_in", "refresh_token", "created"] as $key) {
            $user_data[$key] = $access_token[$key];
        }
        return $user_data;
    }

    static function getCallbackUrl() {
        return Util::getHomeUrl() . Conf::get("auth_callback_url", getenv('AUTH_CALLBACK_URL'));
    }

    static function getAuthRedirectUrl($email = false) {
        $client = self::getGoogleClientByEmail($email);
        return $client->createAuthUrl();
    }

    static function getConfScopes() {
        $scopes = Conf::get("scopes", []);
        /**
         * Adding some default scopes. We probably should always know who the client is
         */
        $scopes[] = 'https://www.googleapis.com/auth/userinfo.email';
        $scopes[] = "https://www.googleapis.com/auth/userinfo.profile";
        $scopes = array_unique($scopes);
        return $scopes;
    }

    /**
     * @return \Google_Client
     */
    static protected function getGoogleClient() {
        $client = GoogleApis::getGoogleClient("GaeUtil Auth");
        $client->addScope(self::getConfScopes());
        $client_json_path = Conf::getConfFilepath('client_secret.json');
        $client->setAuthConfig($client_json_path);
        $client->useApplicationDefaultCredentials(false);
        return $client;
    }

    /**
     *
     * @return \Google_Client
     */
    static function getGoogleClientByEmail($email = false) {
        $client = self::getGoogleClient();
        $client->setRedirectUri(self::getCallbackUrl());
        $client->setAccessType('offline');        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth
        $client->setApprovalPrompt('force');
        if ($email) {
            $client->setLoginHint($email);
            $user_data = DataStore::retriveTokenByUserEmail($email);
            if ($user_data && isset($user_data["access_token"])) {
                $client = self::refreshTokenIfExpired($user_data, $client);
            }
        }
        return $client;
    }

    static function getGoogleClientForCurrentUser() {
        $user_email = self::getCurrentUserEmail();
        return self::getGoogleClientByEmail($user_email);
    }

    /**
     * @param $scope
     * @return \Google_Client[]
     */
    static function getGoogleClientsByScope($scope, $domain = null) {
        $data = DataStore::retriveTokensByScope($scope, $domain);
        $clients = [];
        foreach ($data as $i => $user_data_content) {
            try {
                $clients[] = Auth::refreshTokenIfExpired($user_data_content);
            } catch (\Exception $e) {
                syslog(LOG_WARNING, $e->getMessage());
            }
        }
        return $clients;
    }

    static public function refreshTokenIfExpired($user_data_content, \Google_Client $client = null) {
        if (is_null($client)) {
            $client = self::getGoogleClient();
        }
        $client->setAccessToken($user_data_content);
        $client->useApplicationDefaultCredentials(false);
        $user_email = $user_data_content["email"];
        if ($client->isAccessTokenExpired()) {
            Syslog(LOG_INFO, "Refreshing token for $user_email.");
            $new_token = $client->fetchAccessTokenWithRefreshToken();
            if ($new_token) {
                foreach (["access_token", "token_type", "expires_in", "refresh_token", "created"] as $key) {
                    $user_data_content[$key] = $new_token[$key];
                }
                DataStore::saveToken($user_email, $user_data_content);
                $client->setAccessToken($user_data_content);
            } else {
                syslog(LOG_WARNING, "Token refresh failed for $user_email .");
            }
        }
        return $client;
    }
    static function createSimpleLoginURL($redirect_to){
        if (Util::isDevServer()) {
            $root = Util::getHomeUrl();
        } else {
            $root = "";
        }
        $login_url = $root . UserService::createLoginURL($redirect_to);
        return $login_url;
    }
    static function createLoginURL($extra_provider = null) {
        if (Util::isDevServer()) {
            $root = Util::getHomeUrl();
        } else {
            $root = "";
        }
        if (!is_null($extra_provider)) {
            $provider_param = "?next=" + $extra_provider;
        } else {
            $provider_param = "";
        }
        $login_url = $root . UserService::createLoginURL(self::getCallbackUrl() . $provider_param);
        return $login_url;
    }

    static function createLogoutURL($path = "/") {
        if (Util::isDevServer()) {
            return Util::getHomeUrl() . UserService::createLogoutURL($path);
        } else {
            return UserService::createLogoutURL($path);
        }
    }

    /**
     * Simple method created for very simple session method for backend frontend communication.
     *
     * @param string $return_to
     * @return array
     */
    static function getSimpleSessionData($return_to = "/") {

        $data = [];
        $current_user = UserService::getCurrentUser();
        $data["logout_url"] = self::createLogoutURL();
        $data["login_url"] = self::createLoginURL($return_to);
        $data["google_login_url"] = self::getAuthRedirectUrl();
        $data["logged_in"] = (bool)$current_user;
        $data["user_is_admin"] = self::isCurrentUserAdmin();
        $data["user_email"] = self::getCurrentUserEmail();
        $data["user_google_id"] = $current_user->getUserId();
        $data["user_name"] = $current_user->getNickname();
        $data["is_devserver"] = Util::isDevServer();
        $data["have_google_token"] = false;
        $data["picture"] = Util::getGravatar($data["user_email"]);
        if ($current_user) {
            $google_client = self::getGoogleClientForCurrentUser();
            $token = $google_client->getAccessToken();
            if ($token) {
                $data["have_google_token"] = true;
            }
        }
        return $data;

    }

    static function getCurrentUserSessionData($authorized_domains = [], $extra_provider = null) {
        $data = [];
        $data["logged_in"] = false;
        $data["is_admin"] = false;
        $data["user_id"] = null;
        $data["user_email"] = null;
        $data["user_nick"] = null;
        $data["access_token"] = null;
        $data["user_domain"] = null;
        $data["logout_url"] = self::createLogoutURL();
        $data["login_url"] = self::createLoginURL($extra_provider);

        $current_user = UserService::getCurrentUser();
        /**
         * First check if current user is logged in.
         */
        if ($current_user) {
            $user_email = $current_user->getEmail();
            $user_is_admin = UserService::isCurrentUserAdmin();
            $data["is_admin"] = $user_is_admin;
            $data["user_id"] = $current_user->getUserId();
            $data["user_email"] = $user_email;
            $data["user_nick"] = $current_user->getNickname();
            $user_domain = Util::getDomainFromEmail($user_email);
            $data["user_domain"] = $user_domain;
            if (!$authorized_domains ||
                in_array($user_domain, $authorized_domains) ||
                $user_is_admin) {
                $data["logged_in"] = true;
                $data["jwt_token"] = JWT::getExternalToken($user_email);

                /**
                 * Should we do another auth cycle?
                 */
                if ($extra_provider == self::PROVIDER_GOOGLE) {
                    /**
                     * Getting data from the Google Client
                     */
                    $client = self::getGoogleClientByEmail($user_email);
                    $access_token = $client->getAccessToken();
                    if (is_null($access_token["access_token"])) {
                        $data["logged_in"] = false;
                        $data["login_url"] = Auth::getAuthRedirectUrl();
                    }

                    /**
                     * Getting previous stored data from DataStore
                     */
                    $user_data = DataStore::retriveTokenByUserEmail($user_email);
                    if ($user_data) {
                        foreach (["family_name", "given_name", "name", "gender", "picture", "name", "locale", "verified_email"] as $key) {
                            if (isset($user_data[$key])) {
                                $data[$key] = $user_data[$key];
                            } else {
                                $data[$key] = null;
                            }
                        }
                    }
                }
            } else {
                syslog(LOG_WARNING, "trying to access application with invalid credentials.");
                /** @TODO Here should 401 be handled. */
            }

        }
        return $data;
    }

    /**
     * @param $code
     * @return mixed
     */
    static function fetchAndSaveTokenByCode($code) {
        $client = self::getGoogleClientByEmail();
        $client->fetchAccessTokenWithAuthCode($code);
        $user_data = self::getUserDataFromGoogleClient($client);
        $user_email = $user_data["email"];
        DataStore::saveToken($user_email, $user_data);
        return $user_data;
    }

    /**
     * Returns the current user email, or false if the user is not logged in.
     *
     * @return bool|string
     */
    static function getCurrentUserEmail() {
        $current_user = UserService::getCurrentUser();
        if ($current_user) {
            return $current_user->getEmail();
        } else {
            return false;
        }
    }

    /**
     * Returns the current user email, or false if the user is not logged in.
     *
     * @return bool|string
     */
    static function isCurrentUserAdmin() {
        return UserService::isCurrentUserAdmin();
    }

    static function getCurrentUserEmailDomain() {
        $email = self::getCurrentUserEmail();
        return Util::getDomainFromEmail($email);
    }

    static function callbackHandler($get_request) {
        try {
            /**
             * Accepting multiple auth cycles.
             * Wrapping this all into a try catch.
             */
            if (isset($get_request["next"])) {
                switch ($get_request["next"]) {
                    case self::PROVIDER_GOOGLE:
                        Util::redirect(Auth::getAuthRedirectUrl());
                        break;
                    default:
                        echo "Invalid Provider";
                        break;
                }
            } elseif (isset($get_request["code"])) {
                $code = $get_request["code"];
                $user_data = Auth::fetchAndSaveTokenByCode($code);
                $current_user_email = Auth::getCurrentUserEmail();
                if ($user_data) {
                    /**
                     * Checking if user is logged in with same user as autenticated. Problem on dev servers.
                     * And when user logging in with another account.
                     */
                    if ($user_data["email"] != $current_user_email) {
                        Util::redirect(Auth::getAuthRedirectUrl());
                    } else {
                        $redirect_back_to_front = Conf::get("frontend_url", "/");
                        Util::redirect($redirect_back_to_front);
                    }
                } else {
                    Util::cmdline("Error saving token");
                }
            } elseif (isset($get_request["error"])) {
                switch ($get_request["error"]) {
                    case "access_denied":
                        echo "Access Denied. ";
                        break;
                    default:
                        echo "Something Went Wrong. ";
                        break;
                }
                echo Util::link(Auth::getAuthRedirectUrl(), "RETRY");
            } else {
                Util::redirect(Auth::getAuthRedirectUrl());
            }
        } catch (\Exception $e) {
            Util::cmdline($e->getMessage());
            syslog(LOG_ALERT, $e->getMessage());
        }

    }

    /**
     * Retrives the info about the current service account.
     */
    static function getCurrentServiceAccountInfo() {
        $access_token = AppIdentityService::getAccessToken([]);
        $url = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . $access_token["access_token"];
        $json = file_get_contents($url);
        $data = json_decode($json);
        return $data;
    }
}