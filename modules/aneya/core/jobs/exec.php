#!/usr/bin/php
<?php
chdir (dirname (__FILE__));

require_once "../../../aneya.php";

use aneya\Core\Environment\Scheduler;

$tag = $argv[1];
$task = Scheduler::getTask ($tag);
$task->execute ();

exit (0);