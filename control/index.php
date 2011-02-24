<?php

/*
  前提: Ch-Aがオブジェ回転用モーター、Ch-Bがステッピングモーター
 `mode com3: BAUD=9600 PARITY=N data=8 stop=1 xon=off`;

  TODO: 排他処理
*/

require('common.php');
set_time_limit(120);

/**
 * オブジェ回転
 */
function do_rotate() {
	if (!isset($_REQUEST['target'])) {
		echo "target required.";
		return;
	}

	switch ($_REQUEST['target']) {
	      case '1':
		rotate_target(1); /* 1 = Ch-1 L */
		break;
	      case '2':
		rotate_target(3); /* 3 = Ch-2 L */
		break;
	      default:
		echo "target unknown.";
	}
}

function rotate_target($target = 1) {
	/* 5Vの60% = 3Vで駆動 */
//	$data = array(255, AGB_ID, 3, 30, $target, 60);
	/* 5V * 30% = 1.5V */
	//$data = array(255, AGB_ID, 3, 30, $target, 30);
	$data = array(255, AGB_ID, 3, 30, $target, 40);
	send_data(PORT_AGB, $data);

	sleep(10);  /* 10秒クルクルする */

	/* 停止 */
	//$data = array(255, AGB_ID, 3, 30, $target, 0);
	//send_data(PORT_AGB, $data);
	_stop();
}


/**
 * モーターコントローラー初期化
 */
function do_reset() {
	_stop();
}

/**
 * ステッピングモーター初期化
 */
function setup_step() {
	/* 速度設定 */
	$start_step = 20;	// 開始
	$accel_step = 80;	// 加速
	$max_step   = 180;	// 最高
	_setup_step($start_step, $accel_step, $max_step);
}

/**
 * COMポート初期化
 */
function setup_port() {
	echo "<pre>";
	system("mode ".PORT_AGB." BAUD=9600 PARITY=N DATA=8 STOP=1 xon=off");
	system("mode ".PORT_ARD1." BAUD=9600 PARITY=N DATA=8 STOP=1 xon=off");
	system("mode ".PORT_ARD2." BAUD=9600 PARITY=N DATA=8 STOP=1 xon=off");
	echo "</pre>";
}


/**
 * 
 */

function do_fortune() {
	if (!isset($_REQUEST['target'])) {
		echo "target required.";
		return;
	}

	$target = intval($_REQUEST['target']);
	if ($target < 1 || $target > 4) {
		echo "unknown target.";
		return;
	}

	/* 回転開始 */
	/* RSモーターは200ステップで１回転(二相励起)、400ステップで１回転（ハーフステップ）
	 * 400/5 = 80
	 */
	/*
	 * 回転開始時はゼロ点にいるという前提で、回転し、１０秒待って、ゼロ点に戻す
	 */
	//$rotate = 800;
	//$mode = 3;
	$rotate = 400;
	$mode = 1;

	$step = intval(($target * $rotate) / 5);
	$restore_step = $rotate - $step;  // 元位置に戻すためのステップ数
	$step = $step + ($rotate * 2);  // 演出のために2回転する
	$restore_step = $restore_step + ($rotate * 2);
	
	// 回転
	_rotate_step($step, $mode);

	// チカチカ
	send_data(PORT_ARD1, array(ord('A')));
	send_data(PORT_ARD2, array(ord('A')));

	/* ここから結果が出るまで6秒弱 */

	sleep(8);  // 大体８秒くらいで出る

	/* 結果が出たー */
	// アルパカ
	$data = array(255, AGB_ID, 3, 30, 1, 40);
	send_data(PORT_AGB, $data);

	// チカチカ
	send_data(PORT_ARD1, array(ord('B')));
	send_data(PORT_ARD2, array(ord('B')));

	sleep(15);

	// アルパカSTOP
	_stop();
	sleep(1);

	/* チカチカやめ */
	send_data(PORT_ARD1, array(ord('S')));
	send_data(PORT_ARD2, array(ord('S')));

	/* 元の状態に戻す */
	_rotate_step($restore_step, $mode);
	sleep(10);
}

function do_flash() {
	if (!isset($_REQUEST['target']) || !isset($_REQUEST['pattern'])) {
		echo "target,pattern required.";
		return;
	}

	$target = intval($_REQUEST['target']);
	$pattern = $_REQUEST['pattern'];
	//echo "pattern=[$pattern]";

	if ($target == 1) {
		send_data(PORT_ARD1, array(ord($pattern)));
	} elseif ($target == 2) {
		send_data(PORT_ARD2, array(ord($pattern)));
	} else {
		send_data(PORT_ARD1, array(ord($pattern)));
		send_data(PORT_ARD2, array(ord($pattern)));
	}
}

function do_step() {
	if (!isset($_REQUEST['step']) || !isset($_REQUEST['mode'])) {
		echo "step,mode required.";
		return;
	}

	$step = $_REQUEST['step'];
	$mode = $_REQUEST['mode'];
	_rotate_step($step, $mode);
}

function do_stepset() {
	if (!isset($_REQUEST['start']) || !isset($_REQUEST['accel']) || !isset($_REQUEST['max'])) {
		echo "parameter required.";
		return;
	}
	_setup_step(intval($_REQUEST['start']), intval($_REQUEST['accel']), intval($_REQUEST['max']));
}

/**
 * main
 */

if (!isset($_REQUEST['action'])) {
	echo "action required.";
	exit;
}

switch ($_REQUEST['action']) {
      case 'reset':  // 初期設定
	setup_port();
	do_reset();
	setup_step();
	break;
      case 'fortune':  // 占い
	do_fortune();
	break;
      case 'rotate':  // アルパカ回転
	do_rotate();
	break;
      case 'flash':  // LEDチカチカ
	do_flash();
	break;
      case 'step':  // 障害対応用ちょっとずらし
	do_step();
	break;
      case 'stepset':
	do_stepset();
	break;
      default:
	echo "action unknown.";
	exit;
}

echo " //done.";
