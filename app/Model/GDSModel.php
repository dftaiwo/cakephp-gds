<?php

class GDSModel {

	public $gds = false;
	public $gdsStore = false;
	public $name = 'unnamedModel';
	public $id = null;
	public $strict = false;

	public $findMethods = array(
		'all' => true, 'first' => true, 'count' => true,
		'neighbors' => true, 'list' => true, 'threaded' => true
	);

	
	function __construct() {


		require_once(ROOT . '/vendors/php-gds/vendor/autoload.php');
		$this->name = get_class($this);

		$this->gds = (new GDS\Schema($this->name));

		// The Store accepts a Schema object or Kind name as its first parameter
		$this->gdsStore = new GDS\Store($this->name);
	}

	function create($data=array()) {

		$this->id = null;
		if(isset($data['id'])){
			unset($data['id']);
		}
	}

	function save($data) {

		if ($this->id || isset($data['id'])) {
			$entity = $this->prepareForUpdate($data);
		} else {
			$entity = $this->prepareForCreate($data);
		}

		try {
			$this->gdsStore->upsert($entity);
			$this->id = $entity->getKeyId();
			return $this->id;
		} catch (Exception $e) {
			syslog(LOG_ERR, $e->getMessage());
		}
		return false;
	}

	function delete($id = null) {
		
		$entity = $this->readEntity(null,$id);
		
		if(!$entity){
			return false;
		}
		
		try {
			return $this->gdsStore->delete($entity);
		} catch (Exception $ex) {
			syslog(LOG_ERR, $ex->getMessage());
		}
		return false;
	}

	function readEntity($fields = null, $recordId = 0) {

		$entity = $this->gdsStore->fetchById($recordId);
		if (!$entity)
			return false;
		return $entity;
	}

	function read($fields = null, $recordId = 0) {

		$entity = $this->gdsStore->fetchById($recordId);
		if (!$entity)
			return false;
		return $entity;
	}

	private function prepareForUpdate($data) {
		
		if(isset($data['id']) && !isset($this->id)){
			$this->id = $data['id'];
		}
		
		$entity = $this->readEntity(null, $this->id);

		if (!$entity) {
			return $this->prepareForCreate($data);
		}

		if (!isset($data['modified'])) {
			$data['modified'] = $this->_now();
		}

		foreach ($data as $field => $value) {
			$entity->{$field} = $value;
		}


		return $entity;
	}

	function _now() {
		return date('Y-m-d H:i:s');
	}

	private function prepareForCreate($data) {

		$now = $this->_now();

		if (!isset($data['created'])) {
			$data['created'] = $now;
		}

		if (!isset($data['modified'])) {
			$data['modified'] = $now;
		}

		return $this->gdsStore->createEntity($data);
	}

	
	function find($type,$query){
		$query = array_merge(
			array(
				'conditions' => null, 'fields' => null, 'joins' => array(), 'limit' => null,
				'offset' => null, 'order' => null, 'page' => 1, 'group' => null, 'callbacks' => true,
			),
			(array)$query
		);

		if ($this->findMethods[$type] === true) {
			$query = $this->{'_find' . ucfirst($type)}($query);
		}

		if (!is_numeric($query['page']) || (int)$query['page'] < 1) {
			$query['page'] = 1;
		}

		if ($query['page'] > 1 && !empty($query['limit'])) {
			$query['offset'] = ($query['page'] - 1) * $query['limit'];
		}

		if ($query['order'] === null && $this->order !== null) {
			$query['order'] = $this->order;
		}

		$query['order'] = (array)$query['order'];

pr($query);
exit;
	}
	
	protected function _findFirst($query){
		
	}
}
