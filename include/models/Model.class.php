<?php

/**
 * Model: An abstract class to represent a database Model
 */
abstract class Model
{
    protected $db;
    protected $table;

    public function __construct($db, $table=null){
        $this->db = $db;
        $this->table = $table;
    }

    /**
     * Query the database with any query and return the result
     */
    protected function query($query, array $input_parameters=[]) {
        $statement = $this->db->prepare($query);

        $statement->execute($input_parameters);

        if ( strncasecmp(trim($query), 'select', 6) === 0 )
            // can only do fetchAll for non-select queries
            return $statement->fetchAll(PDO::FETCH_ASSOC);

        return true;
    }
    
    /**
     * Query the database with any query and return only the first row
     * (borrowed from the Cover website)
     */
    protected function query_first($query, array $input_parameters=[]) {
        $result = $this->query($query, $input_parameters);
        
        if (is_string($result)) {
            /* Result is a string, this means an error occurred */
            return $result;
        } elseif (!is_array($result) || count($result) == 0) {
            /* There are no results */
            return null;
        } else {
            /* Return the result */
            return $result[0];
        }
    }

    /**
     * Helper function to format SQL where conditions. Expects an array of arrays
     * of type [fieldname, operator, value], and an array to put fieldname => value
     * pairs in (to use in prepared statement)
     *
     * Returns a correctly formatted string with conditions
     */
    final protected function format_conditions(array $conditions, &$params) {
        $simple_operators = [ 'eq' => '=', 'ne' => '<>', 'lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>=' ];

        $atoms = [];
        foreach ($conditions as $key => $value) {
            $parts = explode('__', $key, 2);
            if (count($parts) > 1){
                $field = $parts[0];
                $operator = $parts[1];
            } else {
                $field = $parts[0];
                $operator = 'eq';
            }

            $format = '';
            $ident = sprintf('where_%s_%s', $field, $operator);

            if (array_key_exists($operator, $simple_operators)) {
                $params[$ident] = $value;
                $format = '`%s` ' . $simple_operators[$operator] . ' :%s';
            } elseif ($operator === 'in') {
                if (!is_array($value))
                    throw new InvalidArgumentException("in-operator in '$field' condition expects an array.");

                if (count($value) === 0) {
                    $format = '`%s` IS NULL';
                    unset($ident);
                } else {
                    // create string like ":where_field_in_0,:where_field_in_1"
                    $ident = implode(',', array_map( function ($n) use ($field) { 
                        return sprintf(':where_%s_in_%s', $field, $n); 
                    }, array_keys($value) ));

                    foreach ($value as $k => $v)
                        $params[sprintf('where_%s_in_%s', $field, $k)] = $v;

                    $format = '`%s` IN (%s)';
                } 
            } elseif ($operator === 'contains') {
                $params[$ident] = '%'. $value .'%';
                $format[] = '`%s` LIKE :%s';
            } elseif ($operator === 'isnull') {
                $format = $value ? '`%s` IS NULL' : '`%s` IS NOT NULL';
                unset($ident);
            } else {
                throw new InvalidArgumentException("Unknown operator '$operator' in '$field' condition.");    
            }

            $atoms[] = isset($ident) ? sprintf($format, $field, $ident) : sprintf($format, $field);
        }

        if (!empty($atoms))
            return 'WHERE ' . implode(' AND ', $atoms);
        return '';
    }

    /**
     * Select data from table
     */
    public function get(array $conditions=[], array $order=[], $get_first=false) {
        if (!$this->table)
            throw new RuntimeException(get_class($this) . '::$table is not set');

        $query = 'SELECT * FROM `' . $this->table . '`';
        $params = [];


        if (!empty($conditions))
            $query .= ' ' . $this->format_conditions($conditions, $params);

        if (!empty($order)) {
            $atoms = [];
            foreach ($order as $field)
                $atoms[] = $field[0] === '-' ? sprintf('%s DESC', substr($field, 1)) : $field;
            $query .= ' ORDER BY ' . implode(',', $atoms);
        }

        if ($get_first)
            return $this->query_first($query, $params);
        return $this->query($query, $params);
    }


    /**
     * Select first entry from table matched by ID
     */
    public function get_by_id($id, $field='id') {
        return $this->get([$field => $id], [], true);
    }


    /**
     * Insert one item into the DB
     */
    public function create(array $values) {
        if (!$this->table)
            throw new RuntimeException(get_class($this) . '::$table is not set');

        $keys = array_keys($values);
        $placeholders = array_map(function ($k) { return ':'.$k; }, $keys);

        $query = 'INSERT INTO `' . $this->table . '` '.
                 '(`' .  implode('`, `', $keys) . '`) ' .
                 'VALUES (' .  implode(', ', $placeholders) . ');';

        $this->query($query, $values);

        return $this->db->lastInsertId(); 
    }


    /**
     * Perform update with data and conditions
     */
    public function update(array $data, array $conditions=[]) {
        if (!$this->table)
            throw new RuntimeException(get_class($this) . '::$table is not set');

        $query = 'UPDATE `' . $this->table . '` SET ';

        $params = [];

        $first = true;
        foreach ($data as $key => $value) {
            if ($first)
                $first = false;
            else
                $query .= ', ';
            $query .= '`' . $key . '` = :set_' . $key;
            $params['set_' . $key] = $value;
        }

        if (!empty($conditions))
            $query .= ' ' . $this->format_conditions($conditions, $params);

        $this->query($query, $params);
    }


    /**
     * Perform update for a specific id
     */
    public function update_by_id($id, array $data, $field='id') {
        $this->update($data, [$field => $id]);
    }


    /**
     * Perform deletion
     */
    public function delete(array $conditions) {
        if (!$this->table)
            throw new RuntimeException(get_class($this) . '::$table is not set');

        $query = 'DELETE FROM `' . $this->table . '`';
        
        $params = [];

        if (!empty($conditions))
            $query .= ' ' . $this->format_conditions($conditions, $params);
        else
            throw new LengthException('Delete without conditions is not allowed!');

        $this->query($query, $params);
    }


    /**
     * Perform deletion for a specific ID
     */
    public function delete_by_id($id, $field='id') {
        $this->delete([$field => $id]);
    }
}
