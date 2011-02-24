<?php
class ControllerLock extends AppModel {
	var $name = 'ControllerLock';

	function acquire($item) {
		$ds = $this->getDataSource();
		$ds->begin($this);
		$this->query("SELECT id FROM controller_locks WHERE id = {$item} FOR UPDATE");
	}

	function release() {
		$ds = $this->getDataSource();
		$ds->rollback($this);
	}
}
?>
