<?php
require_once 'include/models/Model.class.php';

class Slide extends Model
{
    public static $fit_options = [
        'cover' => 'Cover',
        'contain' => 'Contain',
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
}
