<?php

require_once(ROOT . '/vendors/php-gds/vendor/autoload.php');

class GoogleDataStoreSource extends DataSource {

	/**
	 * An optional description of your datasource
	 */
	public $description = 'Google Data Store - GCP';
	public $gds = false;
	public $gdsStore = false;
	public $modelName = false;

	/**
	 * Our default config options. These options will be customized in our
	 * ``app/Config/database.php`` and will be merged in the ``__construct()``.
	 */
	public $config = array();

	/**
	 * If we want to create() or update() we need to specify the fields
	 * available. We use the same array keys as we do with CakeSchema, eg.
	 * fixtures and schema migrations.
	 */
	public $_schema = array(
			  'id' => array(
						 'type' => 'integer',
						 'null' => false,
						 'key' => 'primary',
						 'length' => 11,
			  ),
			  'name' => array(
						 'type' => 'string',
						 'null' => true,
						 'length' => 255,
			  ),
			  'message' => array(
						 'type' => 'text',
						 'null' => true,
			  ),
	);

	/**
	 * Create our HttpSocket and handle any config tweaks.
	 */
	public function __construct($config) {
		parent::__construct($config);
	}

	public function read(Model $model, $queryData = array(), $recursive = null) {
//		$obj_store = new GDS\Store('Articlexe');
//		foreach ($obj_store->fetchAll() as $obj_book) {
//			echo "Title: {$obj_book->title}, ISBN: {$obj_book->isbn} <br />", PHP_EOL;
//		}
//		return [[]];
		try {
			$this->_initializeSchema($model);

			$sqlParts = [];

			$sqlParts[] = "SELECT * FROM {$this->modelName}  ";

			$sqlParts[] = $this->_generateWhereClause($queryData['conditions']);

			$sqlParts[] = $this->_generateOrderBy($queryData['order']);

			$query = join(" ", $sqlParts);
//@TODO use names params for filters siimilar to
			/**
			 * 
			 * $obj_book_store->fetchOne("SELECT * FROM Book WHERE isbn = @isbnNumber", [
			  'isbnNumber' => '1853260304'
			  ]
			 * );
			 */
			$entities = $this->gdsStore->fetchAll($query);
//pr($query);
			if ($queryData['fields'] === 'COUNT') {
				return array(array(array('count' => count($entities))));
			}

			if (!$entities)
				return [];
			$result = [];
			foreach ($entities as $entity) {
				$row = array_merge(['id' => $entity->getKeyId()], $entity->getData());
				$result[] = [$model->alias => $row];
			}
			return $result;
		} catch (Exception $e) {
			throw new CakeException($e->getMessage());
		}
	}

	function _initializeSchema($model) {
//		if ($this->modelName)
//			return; //worried if this might be persistent
		try {
			$this->modelName = $model->name;
			$this->gds = (new GDS\Schema($this->modelName));
// The Store accepts a Schema object or Kind name as its first parameter
			$this->gdsStore = new GDS\Store($this->modelName);
		} catch (Exception $e) {
			pr($e->getMessage());
		}
	}

	function _stripModelName($field) {
		$fieldParts = explode(".", $field);
		$cleanField = array_pop($fieldParts);
		return $cleanField;
	}

	function _generateOrderBy($orderSettings) {
		if (!$orderSettings) {
			return NULL;
		}
		$orderBys = [];

		foreach ($orderSettings as $orderField => $orderDirection) {
			if (!$orderDirection) {
				continue;
			}
			if (is_numeric($orderField)) {
				$orderField = $orderDirection;
				$orderDirection = 'ASC';
			}
			$orderField = $this->_stripModelName($orderField);

			$orderBys[] = "$orderField $orderDirection";
		}
		if (!$orderBys) {
			return NULL;
		}

		return "ORDER BY " . join(", ", $orderBys);
	}

	function _generateWhereClause($conditions) {
		if (!$conditions) {
			return NULL;
		}

		$joinedConditions = [];
//@TODO - support > < BETWEEN etc
		foreach ($conditions as $field => $filterVal) {

			$field = $this->_stripModelName($field);
			$joinedConditions[] = "$field='$filterVal'";
		}


		if (!$joinedConditions) {
			return NULL;
		}

		return "WHERE " . join(" AND ", $joinedConditions);
	}

	function isConnected() {
		return true;
	}

	/**
	 * Since datasources normally connect to a database there are a few things
	 * we must change to get them to work without a database.
	 */

	/**
	 * listSources() is for caching. You'll likely want to implement caching in
	 * your own way with a custom datasource. So just ``return null``.
	 */
	public function listSources($data = null) {
		return null;
	}

	/**
	 * describe() tells the model your schema for ``Model::save()``.
	 *
	 * You may want a different schema for each model but still use a single
	 * datasource. If this is your case then set a ``schema`` property on your
	 * models and simply return ``$model->schema`` here instead.
	 */
	public function describe($model) {

		$this->_initializeSchema($model);

		return $this->_schema;
	}

	/**
	 * calculate() is for determining how we will count the records and is
	 * required to get ``update()`` and ``delete()`` to work.
	 *
	 * We don't count the records here but return a string to be passed to
	 * ``read()`` which will do the actual counting. The easiest way is to just
	 * return the string 'COUNT' and check for it in ``read()`` where
	 * ``$data['fields'] === 'COUNT'``.
	 */
	public function calculate(Model $model, $func, $params = array()) {
		return 'COUNT';
	}

	/**
	 * Implement the C in CRUD. Calls to ``Model::save()`` without $model->id
	 * set arrive here.
	 */
	public function create(Model $model, $fields = null, $values = null) {

		$data = array_combine($fields, $values);

		if ($model->id || isset($data['id'])) {
			if ($data['id']) {
				$model->id = $data['id'];
			}
			$entity = $this->prepareForUpdate($model->id, $data);
		} else {
			$entity = $this->prepareForCreate($data);
		}

		try {

			$this->gdsStore->upsert($entity);
			$this->id = $entity->getKeyId();
			if(!isset($entity->id) || !$entity->id){
				//got to resave but with id field
				$entity->id = $this->id;
				$this->gdsStore->upsert($entity);
			}
			return $this->id;
		} catch (Exception $e) {
			throw new CakeException($e->getMessage());
		}
		return false;
	}

	/**
	 * Implement the U in CRUD. Calls to ``Model::save()`` with $Model->id
	 * set arrive here. Depending on the remote source you can just call
	 * ``$this->create()``.
	 */
	public function update(Model $model, $fields = null, $values = null, $conditions = null) {

		return $this->create($model, $fields, $values);
	}

	private function prepareForUpdate($data) {

		if (isset($data['id']) && !isset($this->id)) {
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

	/**
	 * Implement the D in CRUD. Calls to ``Model::delete()`` arrive here.
	 */
	public function delete(Model $model, $id = null) {

		$this->_initializeSchema($model);

		$entity = $this->readEntity(null, $id);

		if (!$entity) {

			return false;
		}

		try {
			return $this->gdsStore->delete($entity);
		} catch (Exception $ex) {
			syslog(LOG_ERR, $ex->getMessage());
		}
		
		return false;
	}

}
