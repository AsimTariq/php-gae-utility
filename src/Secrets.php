<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 05/02/2018
 * Time: 16:39
 */

namespace GaeUtil;

use Noodlehaus\Config;

/**
 * This module is a wrapper around the Google Key Management Service and
 * provides a secure way to store secrets on Google App Engine.
 *
 * Class Secrets
 * @package GaeUtil
 */
class Secrets {

    const CONF_PROJECT_ID_NAME = "kms_project_id";
    const CONF_KEYRING_ID_NAME = "kms_keyring_id";
    const CONF_KEY_ID_NAME = "kms_cryptokey_id";
    const CONF_GLOBAL_CIPHER_LOCATION = "global_cipher_location";
    const CONF_GLOBAL_KEY_NAME = "global_key_name";
    const CONF_PROJECT_KEY_NAME = "project_key_name";

    const ARRAY_CIPHER_NAME = "_cipher";
    const ARRAY_KEY_NAME = "_key_name";

    static public function getProjectId() {
        return Conf::get(self::CONF_PROJECT_ID_NAME);
    }

    static public function getKeyRingId() {
        return Conf::get(self::CONF_KEYRING_ID_NAME);
    }

    static public function getCryptoKeyId() {
        return Conf::get(self::CONF_KEY_ID_NAME);
    }

    static public function reverseKmsKey($key_name) {
        $matches = [];
        $required = ["project", "location", "keyRing", "cryptoKey"];
        preg_match_all("/^projects\/(?<project>.*)\/locations\/(?<location>.*)\/keyRings\/(?<keyRing>.*)\/cryptoKeys\/(?<cryptoKey>.*)$/i", $key_name, $matches);
        $output = [];
        foreach ($required as $key) {
            $output[$key] = @$matches[$key][0];
        }
        return $output;
    }

    static public function getKeyName($project, $location, $keyRing, $cryptoKey) {
        // The resource name of the cryptokey.
        return sprintf('projects/%s/locations/%s/keyRings/%s/cryptoKeys/%s',
            $project,
            $location,
            $keyRing,
            $cryptoKey
        );
    }

    /**
     * Support passing the config object directly.
     *
     * @param Config|null $conf
     * @return string
     */
    static public function getDefaultKeyName(Config $conf = null) {
        $locationId = "global";
        if (is_null($conf)) {
            $projectId = self::getProjectId();
            $keyRingId = self::getKeyRingId();
            $cryptoKeyId = self::getCryptoKeyId();
        } else {
            $projectId = $conf->get(self::CONF_PROJECT_ID_NAME);
            $keyRingId = $conf->get(self::CONF_KEYRING_ID_NAME);
            $cryptoKeyId = $conf->get(self::CONF_KEY_ID_NAME);
        }
        return self::getKeyName($projectId, $locationId, $keyRingId, $cryptoKeyId);
    }


    static function config($projectId, $keyRingId, $cryptoKeyId) {
        Conf::getInstance()->set(self::CONF_PROJECT_ID_NAME, $projectId);
        Conf::getInstance()->set(self::CONF_KEYRING_ID_NAME, $keyRingId);
        Conf::getInstance()->set(self::CONF_KEY_ID_NAME, $cryptoKeyId);
    }

    /**
     * @return \Google_Service_CloudKMS
     */
    static public function getService() {
        $client = GoogleApis::getGoogleClient();
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');
        // Create the Cloud KMS client.
        $kms = new \Google_Service_CloudKMS($client);
        return $kms;
    }

    /**
     * Takes an input file and encrypts using the KMS service
     * and puts it to an outputfile.
     *
     * @param $plaintextFileName
     * @param $ciphertextFileName
     * @return bool
     */
    static public function encrypt($plaintextFileName, $ciphertextFileName, $key_name = null) {
        $kms = self::getService();
        if (is_null($key_name)) {
            $key_name = self::getDefaultKeyName();
        }

        // Use the KMS API to encrypt the text.
        $encoded = base64_encode(file_get_contents($plaintextFileName));
        $request = new \Google_Service_CloudKMS_EncryptRequest();
        $request->setPlaintext($encoded);
        $response = $kms->projects_locations_keyRings_cryptoKeys->encrypt(
            $key_name,
            $request
        );

        // Write the encrypted text to a file.
        file_put_contents($ciphertextFileName, base64_decode($response['ciphertext']));
        Util::cmdline("\tSaved encrypted text to $ciphertextFileName with key $key_name");
        return true;
    }


    /**
     * Can receive the config singleton to be able to be used of the config class
     * during initiation of that object.
     *
     * @param $ciphertextFileName
     * @param Conf|null $conf
     * @return bool|string
     */
    static public function decrypt($ciphertextFileName, Config $config = null) {
        // Instantiate the client, authenticate, and add scopes.
        $kms = self::getService();
        $name = self::getDefaultKeyName($config);
        // Use the KMS API to decrypt the text.
        $ciphertext = base64_encode(file_get_contents($ciphertextFileName));
        $request = new \Google_Service_CloudKMS_DecryptRequest();
        $request->setCiphertext($ciphertext);
        $response = $kms->projects_locations_keyRings_cryptoKeys->decrypt(
            $name,
            $request
        );
        return base64_decode($response['plaintext']);
    }

    /**
     * Utility function to decrypt json.
     *
     * @param $ciphertextFileName
     * @param Config|null $config
     * @return mixed
     * @throws \Exception
     */
    static public function decryptJson($ciphertextFileName, Config $config = null) {
        $content = self::decrypt($ciphertextFileName, $config);
        $data = json_decode($content, JSON_OBJECT_AS_ARRAY);
        Util::isArrayOrFail("Encrypted secrets", $data);
        return $data;
    }



    /**
     * Encrypts a string and returns the base64_encoded response from KMS.
     *
     * @param $string
     * @param $key_name
     * @return mixed
     */
    static public function encryptString($plaintext_string, $key_name) {
        $kms = self::getService();
        $base64_encoded_json = base64_encode($plaintext_string);
        $request = new \Google_Service_CloudKMS_EncryptRequest();
        $request->setPlaintext($base64_encoded_json);
        $response = $kms->projects_locations_keyRings_cryptoKeys->encrypt(
            $key_name,
            $request
        );
        return $response['ciphertext'];
    }

    /**
     *
     */
    static public function decryptString($base64_encoded_ciphertext, $key_name) {
        $kms = self::getService();
        $request = new \Google_Service_CloudKMS_DecryptRequest();
        $request->setCiphertext($base64_encoded_ciphertext);
        $response = $kms->projects_locations_keyRings_cryptoKeys->decrypt(
            $key_name,
            $request
        );
        return base64_decode($response['plaintext']);
    }

    /**
     * @param array $array_w_secrets
     * @param $encryption_key_name
     * @return array
     */
    static public function encryptDotSecrets(Array $array_w_secrets, $encryption_key_name) {
        $secrets = [];
        foreach ($array_w_secrets as $key => $value) {
            if ($key[0] == ".") {
                $secrets[$key] = $value;
                unset($array_w_secrets[$key]);
            }
        }
        if (count($secrets)) {
            $array_w_secrets[self::ARRAY_KEY_NAME] = $encryption_key_name;
            $array_w_secrets[self::ARRAY_CIPHER_NAME] = self::encryptString(json_encode($secrets), $encryption_key_name);
            $array_w_secrets["_created_time"] = date("c");
            $array_w_secrets["_created_by"] = self::getService()->getClient()->getClientId();
        }
        return $array_w_secrets;
    }

    /**
     * @param array $array_w_secrets
     * @param null $key_name
     * @return array
     */
    static public function decryptDotSecrets(Array $array_w_secrets, $key_name = null) {
        if (isset($array_w_secrets[self::ARRAY_KEY_NAME])) {
            if (is_null($key_name)) {
                $key_name = $array_w_secrets[self::ARRAY_KEY_NAME];
            }
            unset($array_w_secrets[self::ARRAY_KEY_NAME]);
        }

        if (isset($array_w_secrets[self::ARRAY_CIPHER_NAME])) {
            $json_string = self::decryptString($array_w_secrets[self::ARRAY_CIPHER_NAME], $key_name);
            unset($array_w_secrets[self::ARRAY_CIPHER_NAME]);
            $data = json_decode($json_string, JSON_OBJECT_AS_ARRAY);
            foreach ($data as $key => $value) {
                $array_w_secrets[$key] = $value;
            }
        }
        unset($array_w_secrets["_created_time"]);
        unset($array_w_secrets["_created_by"]);
        return $array_w_secrets;
    }

    /**
     * @param $filename
     * @param null $key_name
     * @return mixed
     */
    static public function decryptDotSecretsFile($filename, $key_name = null) {
        $cache_key = Cached::keymaker(__METHOD__, $filename);
        $cached = new Cached($cache_key);
        if (!$cached->exists()) {
            $content = Files::getJson($filename);
            $decrypted = self::decryptDotSecrets($content, $key_name);
            $cached->set($decrypted);
        }
        return $cached->get();
    }

    static public function encryptDotSecretsFile($filename, $array_with_secrets, $key_name) {
        $data = self::encryptDotSecrets($array_with_secrets, $key_name);
        return Files::putJson($filename, $data);
    }
}