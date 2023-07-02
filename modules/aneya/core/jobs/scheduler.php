#!/usr/bin/php
<?php
chdir (dirname (__FILE__));

require_once '../../../aneya.php';

use aneya\Core\Environment\Scheduler;

Scheduler::run ();

exit (0);
?>