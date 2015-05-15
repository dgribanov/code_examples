<?php
itk_include_class('Itk_Db_Constraints');

class Itk_Db_Pgsql_Constraints extends Itk_Db_Constraints {

    /**
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-class.html
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-attribute.html
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-constraint.html
    */
    function &get_primary_constraint($tableName) {
        $tableName = strtolower($tableName);
        if(!$this->db->query("
            SELECT pg_class.relname AS table_name,
                pg_attribute.attname AS column_name,
                pg_constraint.conname AS constraint_name,
                pg_constraint.contype AS constraint_type
            FROM pg_class 
            JOIN pg_attribute ON pg_class.oid = pg_attribute.attrelid 
                AND pg_attribute.attnum > 0 
            JOIN pg_constraint ON pg_constraint.contype = 'p'::char 
                AND pg_constraint.conrelid = pg_class.oid 
                AND (pg_attribute.attnum = ANY (pg_constraint.conkey))
            WHERE pg_class.relkind = 'r'::char 
                AND pg_class.relname = '$tableName'
            ")
        ) {
            return false;
        }

        while($this->db->next_record()) {
            if(!is_object($constraint)) {
                $constraint = itk_new('Itk_Db_Constraint');
            }
            $constraint->set_name($this->db->f('constraint_name'));
            $constraint->set_type($this->db->f('constraint_type'));
            $constraint->set_table($this->db->f('table_name'));
            $constraint->add_column($this->db->f('column_name'));
        }
        return $constraint;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-class.html
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-attribute.html
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-constraint.html
    */
    function &get_check_constraints($tableName, $columnName) {
        $tableName = strtolower($tableName);
        if(!$this->db->query("
            SELECT pg_class.relname AS table_name,
                pg_attribute.attname AS column_name,
                pg_constraint.conname AS constraint_name,
                pg_constraint.contype AS constraint_type,
                pg_constraint.consrc  AS search_condition
            FROM pg_class 
            JOIN pg_attribute ON pg_class.oid = pg_attribute.attrelid 
                AND pg_attribute.attnum > 0 
            JOIN pg_constraint ON pg_constraint.contype = 'c'::char 
                AND pg_constraint.conrelid = pg_class.oid 
                AND (pg_attribute.attnum = ANY (pg_constraint.conkey))
            WHERE pg_class.relkind = 'r'::char 
                AND pg_class.relname = '$tableName'
            ")
        ) {
            return false;
        }

        $constraints = array();
        $constraint = itk_new('Itk_Db_CheckConstraint');
        while($this->db->next_record()) {
            $constraint = &$this->get_constraint_from_array($constraints,
            $this->db->f('constraint_name'));
            if(!is_object($constraint)) {
                $constraint = itk_new('Itk_Db_CheckConstraint');
                $constraint->set_name($this->db->f('constraint_name'));
                $constraint->set_table(strtolower($tableName));
                $constraint->set_condition($this->db->f('search_condition'));
                $constraint->add_column($this->db->f('column_name'));
                $constraints[] = $constraint;
            } else {
                $constraint->add_column($this->db->f('column_name'));
            }
        }

        $consts = array();
        foreach ($constraints as $id => $constraint) {
            if($constraint->is_on_column(strtolower($columnName))) {
                $consts[] = $constraint;
            }
        }

        return $consts;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/ddl-alter.html#DDL-ALTER-REMOVING-A-CONSTRAINT
    */
    function drop_check_constraint($tableName, $column) {
        $constraints = $this->get_check_constraints($tableName, $column);

        foreach ($constraints as $constraint) {
            $query = "ALTER TABLE $tableName DROP CONSTRAINT " . $constraint->get_name();
            if(!$this->db->query($query)) {
                return false;
            }
        }
        return true;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-constraint.html
    */
    function constraint_name_exists($tableName, $constraintName) {
        $tableName = strtolower($tableName);
        $constraintName = strtolower($constraintName);
        if(!$this->db->query("
            SELECT pg_constraint.conname
            FROM pg_class,
            JOIN pg_constraint ON pg_constraint.conrelid = pg_class.oid
            WHERE pg_class.relname = '$tableName'
                AND pg_constraint.conname = '$constraintName'
        ")) {
            return true;
        }
        if($this->db->next_record()) {
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $tableName
     * @param array $columns
     * @param string $refTableName
     * @param array $refColumns We may not know refcolumns - in that case
     * @return Itk_Db_Constraint or FALSE on error
     */
    function get_foreign_constraint($tableName, $columns, $refTableName, $refColumns = NULL) {
        $aConstraints = $this->get_foreign_constraints($tableName, $columns, $refTableName, $refColumns);
        if (!empty($aConstraints)) return $aConstraints[0];
        else return FALSE;
    }

    /**
     * Same as get_foreign_constraint but gets all available FK-s depending on parameters given
     *
     * @access public
     * @param string $tableName
     * @param array $columns
     * @param string $refTableName
     * @param array $refColumns We may not know refcolumns - in that case
     * @return array array of Itk_Db_Constraint
     */
    function get_foreign_constraints($tableName, $columns = NULL, $refTableName, $refColumns = NULL) {
        $columns_upper = array();
        foreach ($columns as $column) {
            $columns_upper[] = strtolower($column);
        }
        $tableName = strtolower($tableName);
        $refTableName = strtolower($refTableName);

        if(!$this->db->query("
            SELECT cl.relname AS table_name,
                rcl.relname AS r_table_name,
                attr.attname AS column_name,
                con.conname AS constraint_name,
                con.contype AS constraint_type,
                rcon.conname AS r_constraint_name
            FROM pg_class cl
            JOIN pg_attribute attr ON cl.oid = attr.attrelid 
                AND attr.attnum > 0 
            JOIN pg_constraint con ON con.contype = 'f'::char 
                AND con.conrelid = cl.oid 
                AND attr.attnum = ANY(con.conkey)
            JOIN pg_class rcl ON rcl.oid = con.confrelid
                AND rcl.relname = '$refTableName'
            JOIN pg_constraint rcon ON rcon.contype = 'p'::char
                AND rcon.conrelid = rcl.oid
            WHERE cl.relkind = 'r'::char 
                AND cl.relname = '$tableName'
            ")
        ) {
            return false;
        }

        $constraints = array();
        $constraint = itk_new('Itk_Db_Constraint');
        while($this->db->next_record()) {
            $constraint = &$this->get_constraint_from_array($constraints,
            $this->db->f('constraint_name'));
            if(!is_object($constraint)) {
                $constraint = itk_new('Itk_Db_Constraint');
                $constraint->set_name($this->db->f('constraint_name'));
                $constraint->set_type($this->db->f('constraint_type'));
                $constraint->set_table($this->db->f('table_name'));
                $constraint->set_ref_table($this->db->f('r_table_name'));
                $constraint->set_ref_constraint_name($this->db->f('r_constraint_name'));
                $constraint->add_column($this->db->f('column_name'));
                $constraints[] = $constraint;
            } else {
                $constraint->add_column($this->db->f('column_name'));
            }
        }

        foreach ($constraints as $const) {
            if($const->is_on_columns($columns) && $const->is_on_ref_columns($refColumns)) {
                return $const;
            }
        }
        return false;
    }

}

?>