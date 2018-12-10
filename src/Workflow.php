<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 20/11/2017
 * Time: 23:03
 */

namespace GaeUtil;

use Psr\Http\Message\RequestInterface;

class Workflow {

    const STATUS_COMPLETED = "COMPLETED";
    const STATUS_ERROR = "ERROR";
    const STATUS_FAILED = "FAILED";
    const STATUS_RUNNING = "RUNNING";

    const CONF_HANDLER = "handler";
    const CONF_PARAMS = "params";
    const CONF_INITIAL_STATE = "initial_state";

    /**
     * Function creates and saves the workflow_config.
     *
     * @param $workflow_config
     * @return mixed
     * @throws \Exception
     */
    static function createWorkflow($workflow_config) {
        self::validateConfig($workflow_config);
        self::validateState($workflow_config[self::CONF_HANDLER], $workflow_config[self::CONF_INITIAL_STATE]);

        if (!isset($workflow_config["name"])) {
            $workflow_config["name"] = $workflow_config[self::CONF_HANDLER] . '(' . implode(",", $workflow_config[self::CONF_PARAMS]) . ")";
        }
        if (!isset($workflow_config["active"])) {
            $workflow_config["active"] = true;
        }
        /**
         * defines the maximum age before a job is considered old.
         * 23 hours are default.
         */
        if (!isset($workflow_config["max_age"])) {
            $workflow_config["max_age"] = Moment::ONEDAY - Moment::ONEHOUR;
        }
        /**
         * Setting some defaults
         */
        $workflow_config["application"] = Util::getApplicationId();
        $workflow_config["service"] = Util::getModuleId();
        $workflow_config["created"] = new \DateTime();
        $workflow_key = self::createWorkflowKeyFromConfig($workflow_config);
        DataStore::saveWorkflow($workflow_key, $workflow_config);
        return $workflow_config;
    }

    static function createWorkflowKeyFromConfig($config) {
        $workflow_key = [
            self::CONF_HANDLER => $config[self::CONF_HANDLER],
            self::CONF_PARAMS => $config[self::CONF_PARAMS],
        ];
        return md5(json_encode($workflow_key));
    }

    static function getWorkflowConfig($workflow_key) {
        $workflowConfig = DataStore::retrieveWorkflow($workflow_key);
        if ($workflowConfig) {
            return $workflowConfig->getData();
        }
        return false;
    }

    /**
     * Returns Workflow_Job_Config
     *
     * @param $workflow_key
     * @return array
     */
    static function runFromKey($workflow_key) {
        $workflow_job_config = [];
        $start_time = microtime(true);
        /**
         * First doing a simple setup... does this work?
         * Nothing get saved at this point errors here is most likely to be
         * a runtime problem. Like locking errorous jobs or that a job is already running.
         */
        try {
            $workflow_config = self::getWorkflowConfig($workflow_key);
            $workflow_job_key = self::createWorkflowJobKey();
            $workflow_job_config = self::createJobConfig($workflow_config);
            $start_state = self::getWorkflowState($workflow_key);
            $workflow_job_config = self::startJob($workflow_job_key, $workflow_job_config, $start_state);
        } catch (\Exception $exception) {
            syslog(LOG_ERR, $exception->getMessage());
            $workflow_job_config["status"] = self::STATUS_ERROR;
            $workflow_job_config["message"] = "Got this error: " . $exception->getMessage();
            $workflow_job_config["runtime"] = microtime(true) - $start_time;
            return $workflow_job_config;
        }
        /**
         * Continues with a working setup and trying to do the config run.
         */
        try {
            ob_start();
            $end_state = self::runFromConfig($workflow_config, $start_state);
            $workflow_job_config["message"] = ob_get_clean();
            $workflow_job_config["runtime"] = microtime(true) - $start_time;
            if ($end_state) {
                return self::endJob($workflow_job_key, $workflow_job_config, $end_state);
            } else {
                return self::failJob($workflow_job_key, $workflow_job_config);
            }
        } catch (\Exception $exception) {
            $workflow_job_config["runtime"] = microtime(true) - $start_time;
            return self::failJob($workflow_job_key, $workflow_job_config, $exception->getMessage());
        }
    }

    /**
     * This does not do any checks towards the database if there is jobs running.
     *
     * @param $workflow_config
     * @param $state
     * @return mixed
     * @throws \Exception
     */
    static function runFromConfig($workflow_config, $workflow_state) {
        $workflow_classname = $workflow_config[self::CONF_HANDLER];
        self::validateConfig($workflow_config);
        self::validateState($workflow_classname, $workflow_state);
        $workflow_params = $workflow_config[self::CONF_PARAMS];
        $workflowClass = new $workflow_classname($workflow_config);
        syslog(LOG_INFO, "Running $workflow_classname with start state " . json_encode($workflow_state));
        call_user_func_array([$workflowClass, "setState"], $workflow_state);
        $end_state = call_user_func_array([$workflowClass, "run"], $workflow_params);
        syslog(LOG_INFO, "Ending $workflow_classname with end state " . json_encode($end_state));
        self::validateState($workflow_config[self::CONF_HANDLER], $end_state);
        return $end_state;
    }

    /**
     * @param $config
     * @return bool
     * @throws \Exception
     */
    static function validateConfig($config) {
        Util::keysExistsOrFail("Workflow config", $config, [
            self::CONF_HANDLER,
            self::CONF_PARAMS,
            self::CONF_INITIAL_STATE
        ]);
        $workflowClassName = $config[self::CONF_HANDLER];
        $workFlowParams = $config[self::CONF_PARAMS];
        if (!class_exists($workflowClassName)) {
            throw new \Exception("Allright! $workflowClassName does not exist! Creation failed.");
        }
        $method = new \ReflectionMethod($workflowClassName, "run");
        $required_number_of_params = $method->getNumberOfParameters();
        $config_number_of_params = count($workFlowParams);
        if ($required_number_of_params != $config_number_of_params) {
            throw new \Exception("$workflowClassName need exactly $required_number_of_params, $config_number_of_params params given.");
        }
        return true;
    }

    /**
     * @param $state
     * @return bool
     * @throws \Exception
     */
    static function validateState($workflowClassName, $workFlowState) {
        $method = new \ReflectionMethod($workflowClassName, "setState");
        $required_number_of_params = $method->getNumberOfParameters();
        $state_number_of_params = count($workFlowState);
        if ($required_number_of_params != $state_number_of_params) {
            throw new \Exception("$workflowClassName state need exactly $required_number_of_params, $state_number_of_params params given.");
        }
        return true;
    }

    static function createWorkflowJobKey() {
        return uniqid();
    }

    /**
     * Checks if its long since last error. This allows us to not spam apis with error for instance.
     *
     * @param $workflow_key
     * @return mixed
     */
    static function isWorkflowInErrorState($workflow_key, $error_ttl) {
        $status = self::STATUS_FAILED;
        $created_after = "-$error_ttl seconds";
        $data = DataStore::retrieveMostCurrentWorkflowJobByAgeAndStatus($workflow_key, $status, $created_after);
        return (bool)$data;
    }

    /**
     * Will check the last successful job and retrive state from it.
     *
     * @param $workflow_key
     * @return mixed
     * @throws \Exception
     */
    static function getWorkflowState($workflow_key) {
        $status = self::STATUS_COMPLETED;
        $created_after = "-20 years";
        $data = DataStore::retrieveMostCurrentWorkflowJobByAgeAndStatus($workflow_key, $status, $created_after);
        if ($data) {
            return $data["end_state"];
        } else {
            $workflow = DataStore::retrieveWorkflow($workflow_key);
            if (!$workflow) {
                throw new \Exception("Error retrieving workflow for key $workflow_key can't determine state.");
            }
            $data = $workflow->getData();
            return $data[self::CONF_INITIAL_STATE];
        }
    }

    /**
     * Will check if there is still a job running
     *
     * @param $workflow_key
     * @return bool
     */
    static function isWorkflowRunning($workflow_key) {
        $status = self::STATUS_RUNNING;
        if (Util::isDevServer()) {
            $created_after = "-60 seconds";
        } else {
            $created_after = "-" . Moment::ONEHOUR . " seconds";
        }
        $data = DataStore::retrieveMostCurrentWorkflowJobByAgeAndStatus($workflow_key, $status, $created_after);
        $result = (bool)$data;
        return $result;
    }

    static function createJobConfig($workflow_config) {
        return [
            self::CONF_HANDLER => $workflow_config[self::CONF_HANDLER],
            self::CONF_PARAMS => $workflow_config[self::CONF_PARAMS],
            "name" => $workflow_config["name"],
            "workflow_key" => self::createWorkflowKeyFromConfig($workflow_config),
            "status" => "CREATED",
            "start_state" => null,
            "end_state" => null,
            "message" => null,
            "created" => new \DateTime()
        ];
    }

    /**
     * Returns the workflow state from previous job.
     * Performs the check on the previous job. Returns the state form the previous job.
     *
     * @param $workflow_job_key
     * @param $workflow_job_config
     * @param $start_state
     * @return array
     * @throws \Exception
     */
    static function startJob($workflow_job_key, $workflow_job_config, $start_state) {
        $workflow_key = self::createWorkflowKeyFromConfig($workflow_job_config);
        $workflow_name = $workflow_job_config["name"];
        /**
         * Checking if a flow is already running
         */
        if (self::isWorkflowRunning($workflow_key)) {
            throw new \Exception("A job for $workflow_name is already running.");
        }
        /**
         *  Check how long its since last job run... we just don't want to spam errors.
         */
        $error_ttl = Util::isDevServer() ? 60 : Moment::ONEDAY;
        if (self::isWorkflowInErrorState($workflow_key, $error_ttl)) {
            throw new \Exception("A job for $workflow_name have failed less than $error_ttl seconds ago, skipping.");
        }

        $workflow_job_config = array_merge($workflow_job_config, [
            "status" => self::STATUS_RUNNING,
            "start_state" => $start_state,
            "end_state" => $start_state,
            "finished" => new \DateTime(),
        ]);
        DataStore::saveWorkflowJob($workflow_job_key, $workflow_job_config);
        return $workflow_job_config;
    }

    /**
     * Returnes a report.
     *
     * @param $workflow_job_key
     * @param $workflow_job_config
     * @param $message
     * @return array
     */
    static function failJob($workflow_job_key, $workflow_job_config, $message) {
        $workflow_job_config = array_merge($workflow_job_config, [
            "status" => self::STATUS_FAILED,
            "message" => $message,
            "finished" => new \DateTime()
        ]);
        DataStore::saveWorkflowJob($workflow_job_key, $workflow_job_config);
        return $workflow_job_config;
    }

    /**
     * Returnes a report.
     *
     * @param $workflow_job_key
     * @param array $end_state
     * @param string $message
     * @return array
     */
    static function endJob($workflow_job_key, $workflow_job_config, $end_state = []) {
        $workflow_job_config = array_merge($workflow_job_config, [
            "status" => self::STATUS_COMPLETED,
            "end_state" => $end_state,
            "finished" => new \DateTime()
        ]);
        DataStore::saveWorkflowJob($workflow_job_key, $workflow_job_config);
        return $workflow_job_config;
    }

    /**
     * Handler that can be use to as an endpoint to perform various.
     *
     * @param RequestInterface $request
     * @return array
     */
    static function endpointHandler(RequestInterface $request) {
        $get_param = "workflow_key";
        $post_params = [];
        $get_params = [];
        parse_str($request->getBody()->getContents(), $post_params);
        parse_str($request->getUri()->getQuery(), $get_params);
        $query_params = array_merge_recursive($post_params, $get_params);
        syslog(LOG_INFO, __METHOD__ . " invoked with " . json_encode($query_params));
        if (isset($query_params[$get_param])) {
            // This is a script run request
            $result = self::runFromKey($query_params[$get_param]);
            return $result;
        } else {
            $workflows = DataStore::retrieveActiveWorkflows();
            $result = [];
            if ($workflows) {
                $url_path = $request->getUri()->getPath();
                foreach ($workflows as $flow) {
                    $flow_key = self::createWorkflowKeyFromConfig($flow);
                    $query_data = [
                        $get_param => $flow_key
                    ];
                    $result[] = Tasks::add($url_path, $query_data);
                }
                Tasks::flush();
            } else {
                syslog(LOG_WARNING, __METHOD__ . " could not find active workflows to schedule.");
            }
            return $result;
        }

    }
}







