<?php
	require_once 'include/data/DataModel.php';
	require_once 'include/models/DataModelMember.php';
	
	class DataIterCommissie extends DataIter
	{
		public function get_members()
		{
			return $this->model->get_members($this);
		}

		public function has_vacancies()
		{
			if (empty($this->get('vacancies')))
				return false; 

			$end_date = new DateTime($this->get('vacancies'));

			$now = new DateTime();

			return $end_date > $now;
		}
	}

	/**
	  * A class implementing the Commissie data
	  */
	class DataModelCommissie extends DataModel
	{
		const TYPE_COMMITTEE = 1;
		const TYPE_WORKING_GROUP = 2;

		public $type = null;
		
		public $dataiter = 'DataIterCommissie';

		public $fields = array(
			'id',
			'type',
			'naam',
			'login',
			'website',
			'nocaps',
			'page_id',
			'hidden',
			'vacancies');

		public function __construct($db)
		{
			parent::__construct($db, 'commissies');
		}

		protected function _generate_query($conditions)
		{
			return parent::_generate_query($conditions) . ' ORDER BY naam ASC';
		}
		
		/**
		  * Get all commissies (optionally leaving out bestuur)
		  * @include_bestuur optional; whether or not to include
		  * bestuur
		  *
		  * @result an array of #DataIter
		  */
		public function get($include_hidden = true)
		{
			$conditions = [];

			if (!$include_hidden)
				$conditions['hidden__ne'] = 1;

			if ($this->type !== null)
				$conditions['type'] = $this->type;

			return $this->find($conditions);
		}
		
		public function get_functies()
		{
			static $functies = array(
				'Voorzitter' => 5,
				'Secretaris' => 4,
				'Penningmeester' => 3,
				'Commissaris Intern' => 2,
				'Commissaris Extern' => 1,
				'Algemeen Lid' => 0);
			
			return $functies;
		}

		protected function _get_functie($functie)
		{
			$functies = array_combine(
				array_map('strtolower', array_keys($this->get_functies())),
				array_values($this->get_functies()));

			$functie = strtolower($functie);
			
			return isset($functies[$functie]) ? $functies[$functie] : 0;
		}
		
		protected function _sort_leden($a, $b)
		{
			$pattern = '/\s*[,\/]\s*/';

			$afunctie = max(array_map(array($this, '_get_functie'), preg_split($pattern, $a->get('functie'))));
			$bfunctie = max(array_map(array($this, '_get_functie'), preg_split($pattern, $b->get('functie'))));
			
			return $afunctie == $bfunctie ? 0 : $afunctie < $bfunctie ? 1 : -1;
		}
		
		/**
		  * Get all members of a specific commissie
		  * @id the commissie id
		  *
		  * @result an array of #DataIter
		  */
		public function get_members(DataIterCommissie $committee)
		{
			$member_model = get_model('DataModelMember');

			$rows = $this->db->query('SELECT member_id, functie FROM committee_members WHERE committee_id = ' . $committee->get_id());

			if (count($rows) === 0)
				return array();

			$ids = array_map(function($row) { return $row['member_id']; }, $rows);

			$members = $member_model->find('leden.id IN (' . implode(', ', $ids) . ')');

			$positions = array_combine($ids, array_map(function($row) { return $row['functie']; }, $rows));
			
			// Attach the committee positions to all its members
			// Not using 'set' here because that would mess up the DataIter::get_changes()
			foreach ($members as $member)
				$member->data['functie'] = $positions[$member->get_id()];

			/* Sort by function */
			usort($members, array(&$this, '_sort_leden'));

			return $members;
		}

		public function get_random()
		{
			$conditions = "c.hidden <> 1";

			if ($this->type !== null)
				$conditions .= sprintf(" AND type = %d", $this->type);

			$row = $this->db->query_first("SELECT c.* 
					FROM commissies c
					LEFT JOIN committee_members c_m ON
						c_m.committee_id = c.id
					WHERE $conditions
					GROUP BY c.id
					HAVING COUNT(c_m.id) > 0
					ORDER BY RANDOM()
					LIMIT 1");
					
			return $this->_row_to_iter($row);
		}
	}
