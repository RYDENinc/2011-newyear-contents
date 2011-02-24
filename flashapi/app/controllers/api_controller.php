<?php

App::import('Core', 'HttpSocket');
App::import('Vendor', 'oauth', array('file' => 'OAuth'.DS.'oauth_consumer.php'));

class ApiController extends AppController {

	var $name = 'Api';
	var $uses = array('Waiting', 'Visitor', 'Invitation', 'ControllerLock');
	var $helpers = array('Xml');

	function beforeFilter() {
		$this->layout = 'xml';
		header('Content-Type: text/xml');
		Configure::write('debug', 0);
	}

	/**
	 * 待ち人数を返す
	 */
	function get_waiting_count() {
		// GC
		$this->Waiting->gc();
		// 待ち人数取得
		$count = $this->Waiting->find('count', array('recursive' => -1));

		$this->set('count', $count);
	}

	/**
	 * 自分の待ち位置を返す
	 */
	function get_waiting_status() {
		if (!empty($this->params['form']['hash'])) {
			$hash = $this->params['form']['hash'];

			// 待ち人リスト取得
			$items = $this->Waiting->find('all', array(
				'fields' => array('hash'),
				'order' => 'Waiting.id',
				'recursive' => -1,
			));

			// 自分の番号を探す
			$found = false;
			$position = 0;
			foreach ($items as $item) {
				if ($item['Waiting']['hash'] == $hash) {
					$found = true;
					break;
				}
				$position++;
			}

			if ($found === true) {
				$this->Waiting->touch($hash);
				$this->set('count', count($items));
				$this->set('position', $position);
				$this->Waiting->gc();  // GCは最後にやる
				return;
			}
		}
		$this->render('get_waiting_status_error');
	}

	/**
	 * ゲストを登録
	 */
	function register_guest() {
		if (!empty($this->params['form']['name'])) {
			$name = $this->params['form']['name'].'様';

			$data = array(
				'Visitor' => array(
					'name' => $name,
					'client_ip' => env('REMOTE_ADDR'),
				),
				'Waiting' => array(
					'name' => $name,
					'hash' => $this->_new_hash(),
					'last_checked' => date('Y-m-d H:i:s'),
				),
			);
			if ($this->Waiting->saveAll($data)) {
				$this->set('hash', $data['Waiting']['hash']);
				$this->set('name', $name);

				$this->_tweet("ゲスト来訪:{$name} hash:".$data['Waiting']['hash']);
				$this->log("register_guest: hash=".$data['Waiting']['hash']." name=$name", LOG_DEBUG);
				return;
			}
		}

		$this->render('register_guest_error');
	}

	/**
	 * 招待者を登録
	 */
	function register_letter() {
		if (!empty($this->params['form']['serial'])) {
			$serial = $this->params['form']['serial'];

			$invitation = $this->Invitation->find('first', array(
				'fields' => array('name', 'id', 'visited'),
				'conditions' => array('serial' => $serial),
				'recursive' => -1,
			));
			if ($invitation) {
				$data = array(
					'Visitor' => array(
						'name' => $invitation['Invitation']['name'],
						'client_ip' => env('REMOTE_ADDR'),
						'invitation_id' => $invitation['Invitation']['id'],
					),
					'Waiting' => array(
						'name' => $invitation['Invitation']['name'],
						'hash' => $this->_new_hash(),
						'last_checked' => date('Y-m-d H:i:s'),
					),
				);
				if ($this->Waiting->saveAll($data)) {
					$this->set('hash', $data['Waiting']['hash']);
					$this->set('name', $invitation['Invitation']['name']);
					// 招待者名簿を更新
					$this->Invitation->id = $invitation['Invitation']['id'];
					$this->Invitation->saveField('visited', 'Y');

					$this->_tweet("招待者来訪:".$invitation['Invitation']['name']." シリアル:".$serial." hash:".$data['Waiting']['hash']);
					$this->log("register_letter: hash=".$data['Waiting']['hash']." serial=$serial", LOG_DEBUG);
					return;
				}
			}
		}

		$this->render('register_letter_error');
	}

	/**
	 * ハッシュ値発行
	 */
	function _new_hash() {
		return uniqid('', true);
	}

	/**
	 * API CALL本体
	 */
	function _call_api($params = array()) {
		Configure::write('debug', 2);
		$sock =& new HttpSocket(array('timeout'=>120));
		set_time_limit(150);
		$result = $sock->get(API_HOST, $params);
		$this->log($params, LOG_DEBUG);
		$this->log($result, LOG_DEBUG);
	}

	/**
	 * モーター回転！
	 */
	function kick_fortune() {
		if (!empty($this->params['form']['hash']) && !empty($this->params['form']['result'])) {
			$hash = $this->params['form']['hash'];
			$result = $this->params['form']['result'];

			$this->log("kick_fortune: hash=$hash result=$result", LOG_DEBUG);
			if ($this->_validate_result($result) === true && $this->Waiting->isTop($hash)) {
				$this->Waiting->touch($hash);
				//$this->Waiting->commit();
				$waiting = $this->Waiting->find('first', array(
					'recursive' => -1,
					'order' => 'Waiting.id',
				));

				// 排他処理開始
				$this->ControllerLock->acquire(ROTATE_MOTOR);
				$this->ControllerLock->acquire(LED_FLASH_1);
				$this->ControllerLock->acquire(LED_FLASH_2);
				$this->ControllerLock->acquire(STEP_MOTOR);

				// モーターAPI CALL
				$this->_call_api(array(
					'action' => 'fortune',
					'target' => $result,
				));

				// 排他処理終了
				$this->ControllerLock->release();

				$this->_tweet("占い出ました:".$waiting['Waiting']['name']." 結果:{$result} hash:{$hash}");
				return;
			}
		}

		$this->render('kick_fortune_error');
	}

	function kick_event() {
		if (!empty($this->params['form']['hash']) && !empty($this->params['form']['event'])) {
			$hash = $this->params['form']['hash'];
			$event = $this->params['form']['event'];

			$this->log("kick_event: hash=$hash event=$event", LOG_DEBUG);
			if ($this->Waiting->isTop($hash)) {
				$this->Waiting->touch($hash);
				switch ($event) {
				case 'alpaca':
					$this->ControllerLock->acquire(ROTATE_MOTOR);
					// API CALL
					$this->_call_api(array(
						'action' => 'rotate',
						'target' => '1',
					));
					$this->ControllerLock->release();
					return;
					break;
				case 'light1':
					$this->ControllerLock->acquire(LED_FLASH_1);
					// API CALL
					$this->_call_api(array(
						'action' => 'flash',
						'target' => '12',
						'pattern' => 'C',
					));
					$this->ControllerLock->release();
					return;
					break;
				case 'light2':
					$this->ControllerLock->acquire(LED_FLASH_2);
					// API CALL
					$this->_call_api(array(
						'action' => 'flash',
						'target' => '2',
						'pattern' => 'C',
					));
					$this->ControllerLock->release();
					return;
					break;
				}
			}
		}
		$this->render('kick_event_error');
	}

	/**
	 * 占い結果パラメータのチェック
	 */
	function _validate_result($result) {
		if ($result >= 1 && $result <= 5) {
			return true;
		}
		return false;
	}

	/**
	 * 待ちから抜ける
	 */
	function leave() {
		if (!empty($this->params['form']['hash'])) {
			$hash = $this->params['form']['hash'];
			$this->Waiting->deleteAll(array('hash' => $hash), false);
			$this->Waiting->gc();
			$this->log("leave: hash=$hash", LOG_DEBUG);
		}
	}

	/**
	 * Twitter
	 */
	// api consumer key LZHkXLMGIzJMesg8WVZQYg
	// api consumer secret S8mPnKOSfwKSheU29DZlIGO0Hty43ZljK08jSQ
	// token 232265103-bqvL7aTfAhekaBoUjlaYmABKyQcNkjjb1Iqmqdz1
	// secret jVhrhmSRAKr1vNwyCw9VgGcLgjQbn5FKWARVc2I
	function _tweet($str = 'test') {
		$consumer = new OAuth_Consumer('<consumer key>', '<consumer secret>');
		$r = $consumer->post(
			'<token>',
			'<secret>',
			'http://twitter.com/statuses/update.json',
			array('status' => $str)
		);
		$this->log($r, LOG_DEBUG);
	}
	
	/*
	function tweet_test() {
		$this->layout = 'default';
		header('Content-Type: text/html');
		Configure::write('debug', 2);
		$this->_tweet('hello 12');
	}
	*/
}
