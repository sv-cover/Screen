<?php
require_once 'include/models/Model.class.php';

class Slide extends Model
{
    public static $fit_options = [
        'cover' => 'Zoom to fit',
        'contain' => 'Letterboxed',
    ];
    public static $type_options = [
        'image' => 'Image',
        'web' => 'Web page',
    ];

    public function __construct($db) {
        parent::__construct($db, 'slide');
    }

    public function get_next_order() {
        $query = "SELECT MAX(`order`) AS `order` FROM `$this->table`;";
        return ($this->query_first($query)['order'] ?? -1) + 1;
    }

    public function get_slides() {
        $query = "
            SELECT *
              FROM `$this->table` s
             ORDER BY s.order, s.id;
        ";
        return $this->query($query);
    }

    public function get_active_slides() {
        $query = "
            SELECT *
              FROM `$this->table` s
             WHERE 1=1
               AND s.is_active
               AND s.start < NOW()
               AND (s.end IS NULL OR s.end > NOW())
             ORDER BY s.order, s.id;
        ";
        return $this->query($query);
    }

    public function sanitize_data($data) {
        // Convert booleans to tinyints
        $data['is_active'] = empty($data['is_active']) ? 0 : 1;

        // Convert datetime to strings
        if ($data['start'] instanceof DateTime)
            $data['start'] = $data['start']->format('Y-m-d H:i');
        if (!empty($data['end']) && $data['end'] instanceof DateTime)
            $data['end'] = $data['end']->format('Y-m-d H:i');

        return $data;
    }
}
