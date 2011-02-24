<?php

define(PORT_AGB,  'COM3:');	// AGB65_SAC
define(PORT_ARD1, 'COM9:');	// Arduino#1 赤色(Due)
define(PORT_ARD2, 'COM8:');	// Arduino#2 残り(UNO)

define(AGB_ID, 20);

function pack_array($v, $a) {
	return call_user_func_array(pack, array_merge(array($v), (array)$a));
}

function send_data($port, $data = array()) {
	$bindata = pack_array('C*', $data);
	echo bin2hex($bindata);
	$fp = fopen($port, 'wb+');
	fwrite($fp, $bindata);
	fflush($fp);
	fclose($fp);
}

function _setup_step($start_step, $accel_step, $max_step) {
	$start_step_h = intval($start_step / 256);
	$start_step_l = $start_step % 256;
	$accel_step_h = intval($accel_step / 256);
	$accel_step_l = $accel_step % 256;
	$max_step_h   = intval($max_step / 256);
	$max_step_l   = $max_step % 256;

	$data = array(255, AGB_ID, 8, 95,
		      1, /* Bch */
		      $start_step_h, $start_step_l,
		      $accel_step_h, $accel_step_l,
		      $max_step_h, $max_step_l);
	send_data(PORT_AGB, $data);
}

function _rotate_step($step, $mode = 1) {
	$step_h = intval($step / 256);
	$step_l = $step % 256;
	$data = array(255, AGB_ID, 6, 105,
		      1, /* 1=Bch */
		      $mode, /* 1=ハーフステップ */
		      1, /* 1=正転 */
		      $step_h, $step_l);
	send_data(PORT_AGB, $data);
}

function _stop() {
	$data = array(255, AGB_ID, 1, 0);  // 全モーターOFF
	send_data(PORT_AGB, $data);
}
