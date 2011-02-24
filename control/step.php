<?php

require('common.php');
set_time_limit(0);
echo "<pre>\n";

$start_step = 20;
$accel_step = 80;
$max_step   = 180;

$step       = 800;
$loop       = 100;

_setup_step($start_step, $accel_step, $max_step);
echo "\n";

for ($i = 0; $i < $loop; $i++) {
	_rotate_step($step);
	echo " $i\n";
	flush();
	sleep(6);
}

echo "done";
