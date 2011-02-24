<?php
class Waiting extends AppModel {
	var $name = 'Waiting';
	//The Associations below have been created with all possible keys, those that are not needed can be removed

	var $belongsTo = array(
		'Visitor' => array(
			'className' => 'Visitor',
			'foreignKey' => 'visitor_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);

	/**
	 * 待ち行列のガベージコレクト
	 */
	function gc() {
		// とりあえず5分間なんのアクセスもなかったらGCする
		// 5*60 = 300
		$time = date('Y-m-d H:i:s', time() - GC_TIMEOUT);

		$results = $this->find('all', array(
			'recursive' => -1,
			'conditions' => array('last_checked <' => $time),
		));
		foreach ($results as $result) {
			$this->log('GC1:'
				.' id='.$result['Waiting']['id']
				.' hash='.$result['Waiting']['hash']
				.' name='.$result['Waiting']['name']
				.' created='.$result['Waiting']['created']
				.' last_checked='.$result['Waiting']['last_checked']
			, LOG_DEBUG);
			$this->delete($result['Waiting']['id'], false);
		}

		// ログイン後10分経過していたらGC
		$time = date('Y-m-d H:i:s', time() - 600);
		$results = $this->find('all', array(
			'recursive' => -1,
			'conditions' => array('created <' => $time),
		));
		foreach ($results as $result) {
			$this->log('GC2:'
				.' id='.$result['Waiting']['id']
				.' hash='.$result['Waiting']['hash']
				.' name='.$result['Waiting']['name']
				.' created='.$result['Waiting']['created']
				.' last_checked='.$result['Waiting']['last_checked']
			, LOG_DEBUG);
			$this->delete($result['Waiting']['id'], false);
		}
	}

	/**
	 * 最終チェック日時を更新
	 */
	function touch($hash = false) {
		$result = $this->find('first', array(
			'recursive' => -1,
			'conditions' => array('hash' => $hash),
			'fields' => array('id'),
		));
		if ($result) {
			$this->id = $result['Waiting']['id'];
			$this->saveField('last_checked', date('Y-m-d H:i:s'));
		}
	}

	/**
	 * リストの先頭(=操作可能)かどうか
	 */
	function isTop($hash = false) {
		$waiting = $this->find('first', array(
			'recursive' => -1,
			'order' => 'Waiting.id',
		));

		// リストの先頭だったら処理開始
		if (!empty($waiting['Waiting']['hash']) && $waiting['Waiting']['hash'] == $hash) {
			return true;
		}
		return false;
	}

	function commit() {
		$ds = $this->getDataSource();
		$ds->commit($this);
	}

}
