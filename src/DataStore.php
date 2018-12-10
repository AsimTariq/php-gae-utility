<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 15/11/2017
 * Time: 11:57
 */

namespace GaeUtil;

use GDS\Entity;
use GDS\Gateway;
use GDS\Gateway\RESTv1;
use GDS\Store;

class DataStore {

    const DEFAULT_TOKEN_KIND = "GoogleAccessTokens";
    const DEFAULT_WORKFLOW_KIND = "GaeUtilWorkflows";

    const CONF_WORKFLOW_KIND_KEY = "datastore_workflow_kind";

    protected static $obj_gateway;

    static function setGateway(Gateway $obj_gateway) {
        self::$obj_gateway = $obj_gateway;
    }

    /**
     * @param $kind_schema
     * @return Store
     * @throws \Exception
     */
    static function store($kind_schema) {
        return new Store($kind_schema, self::$obj_gateway);
    }

    static function getGoogleAccessTokenKind() {
        return Conf::get("datastore_kind", self::DEFAULT_TOKEN_KIND);
    }

    static function getWorkflowKind() {
        return Conf::get(self::CONF_WORKFLOW_KIND_KEY, self::DEFAULT_WORKFLOW_KIND);
    }

    static function getWorkflowJobKind() {
        return self::getWorkflowKind() . "Jobs";
    }

    static function deleteAll($kind_schema) {
        if (Util::isDevServer() || Util::isCli()) {
            $store = self::store($kind_schema);
            $entities = $store->fetchAll();
            syslog(LOG_INFO, "Found " . count($entities) . " records from $kind_schema, deleting them ALL.");
            $store->delete($entities);
        } else {
            throw new \Exception("GaeUtil refuse to run delete all in production.");
        }
    }

    static function deleteWorkflowJobs() {
        return self::deleteAll(self::getWorkflowJobKind());
    }

    static function saveToken($user_email, $user_data) {
        $kind_schema = self::getGoogleAccessTokenKind();
        $user_data["domain"] = Util::getDomainFromEmail($user_email);
        self::upsert($kind_schema, $user_email, $user_data);
    }

    static function retriveTokenByUserEmail($user_email) {
        $kind_schema = self::getGoogleAccessTokenKind();
        $store = self::store($kind_schema);
        $input_row = $store->fetchByName($user_email);
        return self::flattenRow($input_row);
    }

    /**
     * Function that retrives users and tokens based on URL. used for background processing in bulk.
     */
    static function retriveTokensByScope($scope, $domain = null) {
        $kind_schema = self::getGoogleAccessTokenKind();
        $str_query = "SELECT * FROM $kind_schema WHERE scopes='$scope'";
        if (!is_null($domain)) {
            $str_query = $str_query . " AND domain='$domain'";
        }
        return self::fetchAll($kind_schema, $str_query);
    }

    static function fetchAll($kind_schema, $str_query = null) {
        $store = self::store($kind_schema);
        $result = $store->fetchAll($str_query);
        return self::flattenAll($result);

    }

    static function flattenAll($input_rows) {
        $output = [];
        foreach ($input_rows as $row) {
            $output[] = self::flattenRow($row);
        }
        return $output;
    }

    static function flattenRow($input_row) {
        if (is_a($input_row, Entity::class)) {
            $output_row = $input_row->getData();
            foreach ($output_row as $key => $data) {
                if (is_a($data, \DateTime::class)) {
                    $output_row[$key] = $data->format("c");
                }
            }
            return $output_row;
        } else {
            $output_row = $input_row;
        }
        return $output_row;
    }

    static function saveWorkflow($key, $workflow_config) {
        $kind_schema = self::getWorkflowKind();
        self::upsert($kind_schema, $key, $workflow_config);
    }

    static function upsert($kind_schema, $key, $data) {
        $store = self::store($kind_schema);
        $entity = $store->createEntity($data);
        $entity->setKeyName($key);
        $store->upsert($entity);
        syslog(LOG_INFO, "Saving $key at kind $kind_schema.");
    }

    static function retrieveWorkflow($workflow_key) {
        $kind_schema = self::getWorkflowKind();
        $store = self::store($kind_schema);
        return $store->fetchByName($workflow_key);
    }

    static function retrieveWorkflowJobs() {
        $kind_schema = self::getWorkflowJobKind();
        return self::fetchAll($kind_schema);
    }

    static function retrieveWorkflowJob($workflow_job_key) {
        $kind_schema = self::getWorkflowJobKind();
        $cached = new Cached($kind_schema . "/" . $workflow_job_key);
        if ($cached->exists()) {
            return $cached->get();
        }
        $store = self::store($kind_schema);
        $result = $store->fetchByName($workflow_job_key);
        if ($result) {
            return $result->getData();
        } else {
            return false;
        }
    }

    static function saveWorkflowJob($workflow_job_key, $workflow_job_data) {
        $kind_schema = self::getWorkflowJobKind();
        $cached = new Cached($kind_schema . "/" . $workflow_job_key);
        self::upsert($kind_schema, $workflow_job_key, $workflow_job_data);
        $cached->set($workflow_job_data, 5);
        // caching data just to force this shit to work on the deveserver.
    }

    /**
     * @param $workflow_key
     * @return array
     */
    static function retrieveMostCurrentWorkflowJob($workflow_key) {
        $kind_schema = self::getWorkflowJobKind();
        $store = self::store($kind_schema);
        $where = [
            "workflow_key" => $workflow_key
        ];
        $result = $store->fetchOne("SELECT * FROM $kind_schema WHERE workflow_key = @workflow_key ORDER BY created DESC", $where);
        return $result->getData();
    }

    /**
     * @param $workflow_key
     * @return array
     */
    static function retrieveActiveWorkflows() {
        $kind_schema = self::getWorkflowKind();
        $store = self::store($kind_schema);
        $where = [
            "active" => true
        ];
        $result = $store->fetchAll("SELECT * FROM $kind_schema WHERE active = @active", $where);
        return self::flattenAll($result);

    }

    /**
     * Used to check if job is running and getting last successful job run to retrieve state.
     *
     * @param $workflow_key
     * @param $status
     * @param $created_after
     * @return array|bool
     * @throws \Exception
     */
    static function retrieveMostCurrentWorkflowJobByAgeAndStatus($workflow_key, $status, $created_after) {
        $kind_schema = self::getWorkflowJobKind();
        $store = self::store($kind_schema);
        $obsolete_time = new \DateTime($created_after);
        $where = [
            "workflow_key" => $workflow_key,
            "status" => $status,
            "obsolete_time" => $obsolete_time
        ];
        syslog(LOG_INFO, __METHOD__ . " with vars " . json_encode($where));
        $str_query = "SELECT * FROM $kind_schema 
        WHERE workflow_key = @workflow_key AND status = @status AND created > @obsolete_time ORDER BY created DESC";
        $result = $store->fetchOne($str_query, $where);
        if ($result) {
            return $result->getData();
        } else {
            return false;
        }
    }

    static function changeToTestMode() {
        $str_project_id = getenv("DATASTORE_PROJECT_ID");
        Util::envReplace("::1", "localhost", "DATASTORE_EMULATOR_HOST");
        Util::envReplace("::1", "localhost", "DATASTORE_EMULATOR_HOST_PATH");
        Util::envReplace("::1", "localhost", "DATASTORE_HOST");
        self::setGateway(new RESTv1($str_project_id));
    }

}



