<?php
class Visitor extends AppModel {
	var $name = 'Visitor';
	var $displayField = 'name';
	//The Associations below have been created with all possible keys, those that are not needed can be removed

	var $belongsTo = array(
		'Invitation' => array(
			'className' => 'Invitation',
			'foreignKey' => 'invitation_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);
}
?>