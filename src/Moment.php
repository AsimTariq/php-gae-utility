<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 24/11/2017
 * Time: 11:55
 */

namespace GaeUtil;

class Moment {

    const MYSQL_DATE_FORMAT = 'Y-m-d';
    const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';
    const ONEDAY = 86400;
    const ONEHOUR = 3600;
    const ONEYEAR = 31536000;

    static function mysqlDatetime($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        return date(self::MYSQL_DATETIME_FORMAT, $time);
    }

    static function mysqlDate($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        return date(self::MYSQL_DATE_FORMAT, $time);
    }

    /**
     * Finner neste timestamp basert på input tid.
     * @param type $time
     * @return type
     */
    static public function nextSameWeekday($time) {
        $originalTime = strtotime($time);
        $nextWeekdayText = date("l");
        $nextWeekday = strtotime("next " . $nextWeekdayText);
        return strtotime(date("Y-m-d", $nextWeekday) . " " . date("H:i:s", $originalTime));
    }

    static function year($timestamp) {
        return date("Y", $timestamp);
    }

    static function ymdDate($time = null) {
        return date("Y-m-d", $time);
    }

    static function strtoYdate($string) {
        return self::ymdDate(strtotime($string));
    }

    static function todayYmd() {
        return date("Y-m-d");
    }

    static function dayId($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        return (int)date("Ymd", $time);
    }

    static function yesterday() {
        return self::dateBefore(self::todayYmd());
    }

    static function strtodate($str) {
        return date("Y-m-d", strtotime($str));
    }

    static function timetodate($time) {
        return date("Y-m-d", $time);
    }

    static function dateBefore($date) {
        return date("Y-m-d", strtotime($date . " -1 day"));
    }

    static function dateAfter($date) {
        return date("Y-m-d", strtotime($date . " +1 day"));
    }

    static function getLastDay($first_period, $length) {
        if (is_string($first_period)) {
            $thisDateTime = new \DateTime($first_period);
        } else {
            $thisDateTime = $first_period;
        }
        $thisDateTime->add(new \DateInterval("P" . $length . "M"));
        $thisDateTime->sub(new \DateInterval("P1D"));
        return $thisDateTime;
    }

    static function getPeriods($first_period, $length) {
        $first_month = new \DateTime($first_period);
        $output = array();
        for ($i = 1; $i <= $length; $i++) {
            $output[] = clone $first_month;
            $first_month->add(new \DateInterval("P1M"));
        }
        return $output;
    }

    static function monthIdToDateTime($month_id) {
        $year = substr($month_id, 0, 4);
        $month = substr($month_id, 4, 2);
        $date = implode("-", [$year, $month]);
        return new \DateTime($date);
    }

    static function timeToMonthId($time) {
        return (int)date("Ym", $time);
    }

    static function getPeriodsFromMonthIds($start_month_id, $end_month_id) {
        $start_date = self::monthIdToDateTime($start_month_id);
        $end_date = self::monthIdToDateTime($end_month_id);
        $output = [];
        while ($start_date < $end_date) {
            $output[] = clone $start_date;
            $start_date->add(new \DateInterval("P1M"));
        }
        $output[] = $end_date;
        return $output;
    }

    static function shortdate($DateTime) {
        if (!is_a($DateTime, "DateTime")) {
            $DateTime = new \DateTime($DateTime);
        }
        $month = $DateTime->format("n");
        $nor = [
            1 => "JAN",
            2 => "FEB",
            3 => "MAR",
            4 => "APR",
            5 => "MAI",
            6 => "JUN",
            7 => "JUL",
            8 => "AUG",
            9 => "SEP",
            10 => "OKT",
            11 => "NOV",
            12 => "DES",
        ];
        return "'" . $nor[$month] . " " . $DateTime->format("y");
    }
}










