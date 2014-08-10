<?php
require_once('Jmullan/Sessions/vendor/autoload.php');
$handler = new \Jmullan\Sessions\Handler();
$handler->setDbDetails($session_dsn, 'username', 'password');
$handler->setDBTable('sessions');
session_set_save_handler($handler, true);
session_start();

