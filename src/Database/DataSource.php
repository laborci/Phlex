<?php namespace Phlex\Database;

use App\ServiceManager;

class DataSource{

	/** @var  Access */
	protected $access;
	/** @var string */
	protected $table;
	/** @var  string */
	protected $database;

	public function getAccess():Access { return $this->access; }
	public function getTable():string { return $this->table; }
	public function getDatabase():string { return $this->database; }

	public function __construct($table, $database) {
		$this->access = ServiceManager::get($database);
		$this->database = $database;
		$this->table = $table;
	}

	public function pick(int $id){
		return $this->access->getRowById($this->table, $id);
	}

	public function collect(array $ids){
		return $this->access->getRowsById($this->table, $ids);
	}

	public function insert(array $data){
		return $this->access->insert($this->table, $data);
	}

	public function update(int $id, array $data){
		return $this->access->updateById($this->table, $id, $data);
	}

	public function delete(int $id){
		return $this->access->deleteById($this->table, $id);
	}

}