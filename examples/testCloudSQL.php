<?php

use GaeUtil\CloudSQL;

require_once "../vendor/autoload.php";


CloudSQL::cloneProdDatabase("red-tools", "redperformance","nexus","red-nexus.appspot.com");