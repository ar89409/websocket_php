#!/usr/bin/env php
<?php

require_once('./websockets.php');

class testServer extends WebSocketServer {
}

$testserver = new testServer("0.0.0.0","8000");

try {
  $testserver->run();
}
catch (Exception $e) {
  $testserver->stdout($e->getMessage());
}
