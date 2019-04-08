<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__) . '/nockscheckout.php');

$nockscheckout = new nockscheckout();

$handle = fopen('php://input','r');
$jsonInput = fgets($handle);

fclose($handle);
error_log($jsonInput);

$nockscheckout->processTransaction($jsonInput);
