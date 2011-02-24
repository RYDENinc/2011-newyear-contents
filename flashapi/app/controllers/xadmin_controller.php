<?php

class XadminController extends AppController {

	var $name = 'Xadmin';
	var $uses = array('Waiting');

	function beforeFilter() {
		Configure::write('debug', 2);
	}

	function index() {
		// 待ち人リスト取得
		$items = $this->Waiting->find('all', array(
			'order' => 'Waiting.id',
			'recursive' => -1,
		));
		$this->set('items', $items);
	}

	function ban() {
		if (!empty($this->params['named']['hash'])) {
			$hash = $this->params['named']['hash'];
			$waiting = $this->Waiting->find('first', array(
				'fields' => 'id',
				'order' => 'Waiting.id',
				'conditions' => array('Waiting.hash' => $hash),
				'recursive' => -1,
			));
			pr($waiting);
			if ($waiting) {
				$r = $this->Waiting->delete($waiting['Waiting']['id'], false);
				pr($r);
			}
		}
		$this->autoRender = false;
	}
}
