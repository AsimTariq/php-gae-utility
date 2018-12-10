<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 02/03/2018
 * Time: 11:31
 */

use GaeUtil\Secrets;

require_once "../vendor/autoload.php";

$key_name = "projects/red-operations/locations/global/keyRings/red-operations/cryptoKeys/redperformance";
function test_string_encrypt($key_name) {
    $plaintext_string = json_encode([
        "some" => "string"
    ]);
    $encrypted = Secrets::encryptString($plaintext_string, $key_name);
    print_r($encrypted);
    echo PHP_EOL;
    $decrypted = Secrets::decryptString($encrypted, $key_name);

    print_r($decrypted);
}

function test_dot_secrets($key_name) {
    $array_with_secrets = [
        "name" => "secret-config",
        "description" => "My very secret config system.",
        "type" => "Marathon",
        ".this_is_secret" => "aksdfjlkangadlsfas",
        ".password" => "asøldfhjalkshdflkas jasdf jlkasdfj asjdfkl klsadfj klasdfj "
    ];
    $encrypted = Secrets::encryptDotSecrets($array_with_secrets, $key_name);
    print_r($encrypted);
    $decrypted = Secrets::decryptDotSecrets($encrypted, $key_name);
    echo PHP_EOL;
    print_r($decrypted);
}

/*

$filename = "gs://%/some_secret_file.json";
$array_with_secrets = [
    "name" => "secret-config",
    "description"=>"My very secret config system.",
    "type" => "Test",
    ".this_is_secret" => "aksdfjlkangadlsfas",
    ".password"=>"asøldfhjalkshdflkas jasdf jlkasdfj asjdfkl klsadfj klasdfj "
];

$result = Secrets::encrypt_dot_secrets_file($filename, $array_with_secrets, $key_name);
var_dump($result);
*/
$test_filename = "gs://redperformance/marathon_api_redp.json";
$result = Secrets::decryptDotSecretsFile($test_filename);
print_r($result);