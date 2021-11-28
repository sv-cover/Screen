<?php
	require_once 'include/data/DataModel.php';

	class DataIterMember extends DataIter
	{
		//
	}

	class DataModelMember extends DataModel
	{
		const VISIBLE_TO_NONE = 0;
		const VISIBLE_TO_MEMBERS = 1;
		const VISIBLE_TO_EVERYONE = 7;

		public $dataiter = 'DataIterMember';

		protected $auto_increment = false;

		public function __construct($db)
		{
			parent::__construct($db, 'leden');
		}

		public function get_iter($id)
		{
			$iter = parent::get_iter($id);

			$iter->data['committees'] = $this->get_commissies($id);

			return $iter;
		}

		public function get_commissies($memberid)
		{
			$rows = $this->db->query("SELECT committee_id
					FROM committee_members
					WHERE member_id = " . intval($memberid));

			$commissies = array();

			if (!$rows)
				return $commissies;

			foreach ($rows as $row)
				$commissies[] = $row['committee_id'];

			return $commissies;
		}
	}
