<?php
	require_once 'include/data/DataModel.php';

	class DataIterPhoto extends DataIter
	{
		const EXIF_ORIENTATION_180 = 3;
		const EXIF_ORIENTATION_90_RIGHT = 6;
		const EXIF_ORIENTATION_90_LEFT = 8;

		const LANDSCAPE = 'landscape';
		const PORTRAIT = 'portrait';
		const SQUARE = 'square';

		public function get_size()
		{
			return array($this->get('width'), $this->get('height'));
		}

		public function get_scaled_size($max_width = null, $max_height = null)
		{
			$size = $this->get_size();

			if ($max_width) {
				$width = $max_width;
				$height = round($max_width * ($size[1] / $size[0]));
			}
			
			if (!$max_width || ($max_height && $height > $max_height)) {
				$height = $max_height;
				$width = round($max_height * ($size[0] / $size[1]));
			}

			return array($width, $height, $width / $size[0]);
		}

		public function get_url($width = null, $height = null)
		{
			$url = get_config_value('url_to_cover').'fotoboek.php';

			$params = array(
				'view' => 'scaled',
				'photo' => $this->get_id()
			);

			if ($width)
				$params['width'] = (int) $width;

			if ($height)
				$params['height'] = (int) $height;

			return edit_url($url, $params);
		}
	}

	class DataIterPhotobook extends DataIter
	{
		//
	}

	/**
	  * A class implementing photo data
	  */
	class DataModelFotoboek extends DataModel
	{
		const VISIBILITY_PUBLIC = 0;
		const VISIBILITY_MEMBERS = 1;
		const VISIBILITY_ACTIVE_MEMBERS = 2;
		const VISIBILITY_PHOTOCEE = 3;

		public $dataiter = 'DataIterPhoto';

		public function __construct($db)
		{
			parent::__construct($db, 'fotos');
		}
		
		/**
		  * Get a photo book
		  * @id the id of the book
		  *
		  * @result a #DataIter
		  */
		public function get_book($id)
		{
			$q = sprintf("
					SELECT 
						f_b.*,
						COUNT(DISTINCT f.id) as num_photos,
						COUNT(DISTINCT c_f_b.id) as num_books,
						(TRIM(to_char(DATE_PART('day', f_b.date), '00')) || '-' ||
						 TRIM(to_char(DATE_PART('month', f_b.date), '00')) || '-' ||
						 DATE_PART('year', f_b.date)) AS datum
					FROM 
						foto_boeken f_b
					LEFT JOIN fotos f ON
						f.boek = f_b.id
					LEFT JOIN foto_boeken c_f_b ON
						c_f_b.parent_id = f_b.id
					WHERE 
						f_b.id = %d
					GROUP BY
						f_b.id
					", $id);
			
			$row = $this->db->query_first($q);

			if ($row === null)
				throw new DataIterNotFoundException($id, $this);

			return $this->_row_to_iter($row, 'DataIterPhotobook');
		}

		/**
		  * Get a random photo book
		  * @count the number of latest photo books to choose from
		  *
		  * @result a #DataIter
		  */
		public function get_random_book($count = 10)
		{
			$q = sprintf("
				SELECT 
					c.id
				FROM 
					foto_boeken c
				LEFT JOIN
					fotos f
					ON f.boek = c.id AND f.hidden = 'f'
				WHERE
					c.visibility <= %d
					AND c.date IS NOT NULL
				GROUP BY
					c.id
				HAVING
					COUNT(f.id) > 3
				ORDER BY
					c.date DESC
				LIMIT %d",
				self::VISIBILITY_PUBLIC,
				intval($count));

			// Select the last $count books
			$rows = $this->db->query($q);

			// Pick a random fotoboek
			$book = $rows[rand(0, count($rows) - 1)];

			return $this->get_book($book['id']);
		}

		/**
		  * Get photos in a book
		  * @book a #DataIter representing a book
		  * @max optional; the maximum number of photos to get (specify
		  * 0 for no maximum)
		  * @random optional; whether to order the photos randomly
		  *
		  * @result an array of #DataIter
		  */
		public function get_photos(DataIterPhotobook $book)
		{
			$query = "
				SELECT
					id,
					width,
					height,
					beschrijving
				FROM
					fotos
				WHERE
					boek = {$book->get_id()}
					AND hidden = 'f'
				ORDER BY
					sort_index ASC NULLS FIRST,
					created_on ASC,
					added_on ASC";

			$rows = $this->db->query($query);
			
			return $this->_rows_to_iters($rows);
		}
	}
