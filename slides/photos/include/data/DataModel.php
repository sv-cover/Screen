<?php
	require_once 'include/data/DataIter.php';

	class DataIterNotFoundException extends NotFoundException
	{
		public function __construct($id, DataModel $source = null)
		{
			parent::__construct(sprintf('%s with id %d was not found',
				$source
					? substr(get_class($source), strlen('DataModel'))
					: 'DataIter',
				$id));
		}
	}

	/**
	  * This class provides a base class for accessing data. This class can
	  * be used for very simple one-to-one, model-to-table type mappings.
	  * More complex models should inherit from this base class and implement
	  * their own insert, update, delete, get and get_iter functions 
	  */
	class DataModel
	{
		public $db; /** The database backend */
		public $table; /** The table to model */
		public $id;
		public $dataiter = 'DataIter';
		public $fields = array();
		protected $auto_increment;
		
		/**
		  * Create a new DataModel
		  * @param DatabasePgsql|DatabaseMysql $db the database backend to use (#DatabasePgsql or #DatabaseMysql)
		  * @param string|null $table the table to model 
		  * @param string $id the field name to use as unique id
		  */
		public function __construct($db, $table = null, $id = 'id')
		{
			$this->db = $db;
			$this->table = $table;
			$this->id = $id;

			if ($this->auto_increment === null)
				$this->auto_increment = $this->id == 'id';
		}
		
		/**
		  * Generate a id = value string
		  * @value the id value
		  *
		  * @result a id = value string
		  */
		protected function _id_string($value, $table = null)
		{
			$result = $this->id . ' = ';

			if ($table)
				$reslt = $table . '.' . $result;
			elseif ($this->table)
				$result = $this->table . '.' . $result;
			
			if ($this->id == 'id')
				return $result . intval($value);
			else
				return $result . "'" . $this->db->escape_string($value) . "'";
		}


		/**
		  * Create a #DataIter from data
		  * @row an array containing the data
		  *
		  * @result a #DataIter
		  */
		/*protected*/ public function _row_to_iter($row, $dataiter = null)
		{
			if (!$dataiter)
				$dataiter = $this->dataiter;

			if ($row)
				return new $dataiter($this, isset($row[$this->id]) ? $row[$this->id] : null, $row);
			else
				return $row;
		}
		
		/**
		  * Create array of #DataIter from array of data
		  * @rows an array containing arrays of data
		  *
		  * @result an array of #DataIter
		  */
		/*protected*/ public function _rows_to_iters($rows, $dataiter = null)
		{
			return array_map(function ($row) use ($dataiter) {
				return $this->_row_to_iter($row, $dataiter);
			}, $rows);
		}

		protected function _rows_to_table($rows, $key_field, $value_field)
		{
			

			if (is_array($value_field))
				$create_value = function($row) use ($value_field) {
					return array_map(function($field) use ($row) {
						return $row[$field];
					}, $value_field);
				};
			else
				$create_value = function($row) use ($value_field) {
					return $row[$value_field]; 
				};

			return array_combine(
				array_map(function($row) use ($key_field) { return $row[$key_field]; }, $rows),
				array_map($create_value, $rows));
		}
		
		/**
		  * Get all rows in the model
		  *
		  * @result an array of #DataIter
		  */
		public function get()
		{
			return $this->find('');
		}

		/**
		 * Get all rows in the model that satisfy the conditions.
		 * @conditions the SQL 'where' clause that needs to be satisfied
		 *
		 * @result an array of #DataIter
		 */
		public function find($conditions)
		{
			$query = $this->_generate_query($conditions);

			$rows = $this->db->query($query);
			
			return $this->_rows_to_iters($rows);			
		}

		public function find_one($conditions)
		{
			$results = $this->find($conditions);

			if (count($results) !== 1)
				return null;

			return $results[0];
		}
		
		/**
		  * Get a specific row in the model
		  * @id the id of the row
		  *
		  * @result a #DataIter representing the row
		  */
		public function get_iter($id)
		{
			$data = $this->db->query_first($this->_generate_query($this->_id_string($id)));

			if ($data === null)
				throw new DataIterNotFoundException($id, $this);

			return $this->_row_to_iter($data);
		}

		protected function _generate_conditions_from_array($conditions)
		{
			$atoms = [];

			foreach ($conditions as $key => $value)
			{
				if (preg_match('/^(.+?)__(eq|ne|gt|lt|contains|isnull)$/', $key, $match)) {
					$field = $match[1];
					$operator = $match[2];
				} else {
					$field = $key;
					$operator = 'eq';
				}

				switch ($operator)
				{
					case 'lt':
						$format = "%s < '%s'";
						break;

					case 'gt':
						$format = "%s > '%s'";
						break;

					case 'contains':
						$format = "%s LIKE '%%%s%%'";
						break;

					case 'isnull':
						$format = $value ? '%s IS NULL' : '%s IS NOT NULL';
						unset($value);
						break;

					case 'ne':
						$format = "%s <> '%s'";
						break;

					default:
					case 'eq':
						$format = '%s = %s';
						break;
				}

				$atoms[] = isset($value)
					? sprintf($format, $field, $this->db->escape_string($value))
					: sprintf($format, $field);
			}

			return implode(' AND ', $atoms);
		}
		
		protected function _generate_query($where)
		{
			if (is_array($where))
				$where = $this->_generate_conditions_from_array($where);

			return "SELECT * FROM {$this->table}" . ($where ? " WHERE {$where}" : "");
		}
	}
