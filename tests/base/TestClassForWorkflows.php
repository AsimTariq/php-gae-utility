<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 24/01/2018
 * Time: 14:38
 */

use GaeUtil\Moment;

class TestClassForWorkflows {

    public $start_date;

    /**
     * Returns end state.
     *
     * @param $param1
     * @param $param2
     * @return array
     */
    public function run($param1, $param2) {
        return [Moment::dateAfter($this->start_date)];
    }

    public function setState($start_date) {
        $this->start_date = $start_date;
    }
}