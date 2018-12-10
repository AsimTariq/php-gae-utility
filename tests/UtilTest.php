<?php

use GaeUtil\DataStore;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase {

    public function setUp() {
        DataStore::deleteAll("someSchema");
    }

    function testDatastore() {
        $kind_schema = "someSchema";
        $input_data = [
            "name" => "Hello world",
            "array" => ["with", "values"]
        ];
        DataStore::upsert($kind_schema, __METHOD__, $input_data);
        $actual = DataStore::fetchAll($kind_schema);
       $this->assertEquals([$input_data], $actual);
    }
}