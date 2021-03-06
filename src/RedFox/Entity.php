<?php namespace Phlex\RedFox;

use Phlex\RedFox\Relation\BackReference;

/**
 * Class Entity
 * @package Phlex\RedFox
 * @property-read int $id
 */
abstract class Entity implements \JsonSerializable {

	/** @var  Record */
	private $record;
	private $deleted = false;
	private $repository = null;

	/** @return \Phlex\RedFox\Repository */
	public static function repository() { return static::model()->repository(); }

	/** @return \Phlex\RedFox\Model */
	public static function model() {
		static $model;
		if (is_null($model)) {
			$class = get_called_class() . 'Model';
			$model = $class::instance(get_called_class());
		}
		return $model;
	}

	public function isExists(): bool { return (bool)$this->record->get('id'); }
	public function isDeleted(): bool { return $this->deleted; }

	public function delete() {
		if ($this->isExists()) {
			if ($this->onBeforeDelete() === false)
				return false;
			$this->repository->delete($this);
			$this->deleted = true;
			$this->onDelete();
		}
		return true;
	}

	public function save() {
		if ($this->deleted) {
			$this->record->set('id', null);
		}
		if ($this->isExists()) {
			return $this->update();
		} else {
			return $this->insert();
		}
	}

	private function update() {
		if ($this->onBeforeUpdate() === false)
			return false;
		$this->repository->update($this);
		$this->onUpdate();
		return true;
	}

	private function insert() {
		if ($this->onBeforeInsert() === false)
			return false;
		$id = $this->repository->insert($this);
		$this->record->set('id', $id);
		$this->onInsert();
		return $this->id;
	}

	public function getRawData() { return $this->record->getRawData(); }

	public function __construct($data = null, Repository $repository = null) {
		$this->repository = is_null($repository) ? static::repository() : $repository;
		$this->record = new Record($this->model(), $data);
		if (is_null($data)) {
			static::model()->setDefaults($this);
		}
	}

	public function setRepository(Repository $repository = null, $keepId = false) {
		if (!$keepId)
			$this->record->set('id', null);
		$this->repository = is_null($repository) ? static::repository() : $repository;
	}

	#region Evenet Handlers
	public function onBeforeInsert() { return true; }
	public function onBeforeUpdate() { return true; }
	public function onBeforeDelete() { return true; }
	public function onInsert() { }
	public function onUpdate() { }
	public function onDelete() { }
	#endregion

	private $attachmentManagers = [];

	/**
	 * @param $group
	 * @return \Phlex\RedFox\Attachment\AttachmentManager
	 */
	public function getAttachmentManager($group) {
		if (static::model()->isAttachmentGroupExists($group)) {
			return static::model()->getAttachmentManager($group, $this);
		} else {
			return null;
		}
	}

	public function __get($name) {
		if (method_exists($this, $method = '__get' . ucfirst($name))) {
			return $this->$method();
		} else if (static::model()->fieldExists($name)) {
			return $this->record->get($name);
		} else if (static::model()->isRelationExists($name)) {
			return static::model()->getRelation($name)($this);
		} else if (array_key_exists($name, $this->attachmentManagers)) {
			return $this->attachmentManagers[ $name ];
		} else if (static::model()->isAttachmentGroupExists($name)) {
			return static::model()->getAttachmentManager($name, $this);
		}
		return null;
	}

	public function __call($name, $arguments) {
		if (static::model()->isRelationExists($name)) {
			/** @var BackReference $relation */
			$relation = static::model()->getRelation($name);
			if ($relation instanceof BackReference) {
				list($order, $limit, $offset) = $arguments;
				return $relation($this, $order, $limit, $offset);
			}
		}
		trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
	}

	public function __set($name, $value) {
		if (method_exists($this, $method = '__set' . ucfirst($name))) {
			$this->$method($value);
		} else if (static::model()->fieldWritable($name)) {
			$this->record->set($name, $value);
		}
	}

	function __toString() { return $this->id; }
	function jsonSerialize() { return $this->getRawData(); }

}