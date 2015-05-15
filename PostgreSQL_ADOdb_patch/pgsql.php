<?php
itk_include_class('Itk_Db_Connection');

class Itk_Db_PostgreSQL extends Itk_Db_Connection {
    public $_type = 'postgresql';

    /**
    * @see http://www.postgresql.org/docs/current/static/errcodes-appendix.html
    */
    public $sqlcodes = array(
        'NORMAL' => 0,
        'NODATA' => 2, // no_data (02000)
        'NOCONNECTION' => 8003, // connection_does_not_exist (08003)
        'DUPKEY' => 23505, // unique_violation (23505)
        'DUPKEY_AK' => 23505, // unique_violation (23505)
        'NOTABLE' => '42P01' // undefined_table (42P01)
    );

    public $sqlcodes_no_halt = array(0, 2, 23505);

    /**
    * @see http://www.postgresql.org/docs/current/static/datatype.html
    */
    public $datatypes = array('datetime' => 'timestamp');

    function _init_test_db() {
        $this->_username .= '_test';
    }

    function is_pgsql() {
        return true;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/sql-createsequence.html
    */
    function _create_sequence($seqName, $start) {
        return $this->query("CREATE SEQUENCE $seqName MINVALUE $start START WITH $start");
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-sequence.html
    */
    function nextid($seqName, $start = 1) {
        $res = $this->query("SELECT nextval('$seqName') AS nextv");

        if(!$res) {
            if($this->get_error_nr() == $this->sqlcodes['NOTABLE']) {
                $res = $this->_create_sequence($seqName, $start);
                if(!$res) {
                    $this->halt('nextid: Sequence konnte nicht erzeugt werden');
                    return 0;
                } else {
                    $res = $this->query("SELECT nextval('$seqName') AS nextv");
                }
            }
        }

        if($this->next_record()) {
            return $this->f('nextv');
        }
        return 0;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-sequence.html
    * @see http://www.postgresql.org/docs/current/static/sql-dropsequence.html
    */
    function resetid($seqName, $start = 0) {
        $res = $this->query("DROP SEQUENCE $seqName");
        if($res || ($this->get_error_nr() == $this->sqlcodes['NOTABLE'])) {

            $res = $this->_create_sequence($seqName, $start);

            if(!$res) {
                $this->halt('resetid: Sequence konnte nicht erzeugt werden');
                return false;
            } else {
                $res = $this->query("SELECT nextval('$seqName') AS nextv");
            }
        } else {
            $this->halt('resetid');
            return false;
        }
        return true;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-sequence.html
    */
    function currentid($seqName) {
        $this->query("SELECT nextval('$seqName') AS nextv");
        $res = $this->query("SELECT currval('$seqName') AS currv");

        if($res && $this->next_record()) {
            return $this->f('currv');
        }
        return 0;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-datetime.html
    */
    function sql_actual_date() {
        return "DATE '" . act_date() . "'";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-datetime.html
    */
    function sql_actual_time() {
        return "DATE '" . act_time() . "'";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-datetime.html
    */
    function sql_actual_time_exact() {
        return "DATE '" . act_timepoint() . "'";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-string.html
    * @see http://stackoverflow.com/questions/19942824/how-to-concatenate-columns-in-postgresql-select
    */
    function sql_concat($a, $b) {
        return "CONCAT($a, $b)";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-conditional.html#FUNCTIONS-COALESCE-NVL-IFNULL
    * sql_condition_ifnull
    * if $field1 IS NULL return $field2, $field1 otherwise
    *
    * @return string
    */
    function sql_condition_ifnull($field1, $field2) {
        return "COALESCE($field1, $field2)";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-datetime.html
    */
    function sql_datediff_2days($field1, $field2) {
        return "DATE '$field1' - DATE '$field2'";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-datetime.html
    */
    function sql_datediff_from_now2days($field) {
        return "DATE 'now' - DATE '$field'";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-datetime.html
    */
    function sql_date_sub_days($aField, $vDays) {
        return "DATE '$aField' - INTEGER '$vDays'";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-datetime.html
    */
    function sql_datum($datum) {
        return "DATE '" . $datum . "'";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-formatting.html
    */
    function sql_date_year($aField) {
        return  "TO_CHAR($aField, 'YYYY')";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-formatting.html
    */
    function sql_date_month($aField) {
        return  "TO_CHAR($aField, 'MM')";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-formatting.html
    */
    function sql_date_day($aField) {
        return  "TO_CHAR($aField, 'DD')";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-formatting.html
    */
    function sql_date_hour($aField) {
        return  "TO_CHAR($aField, 'HH24')";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-formatting.html
    */
    function sql_date_time($aField) {
        return  "TO_CHAR($aField, 'HH24:MI')";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-formatting.html
    */
    function sql_string_to_date($aField) {
        return  "TO_DATE($aField, 'YYYY-MM-DD')";
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-formatting.html
    */
    function sql_string_to_number($aField) {
        return  "$aField::integer"; // as alternative to function TO_NUMBER($aField, '9999999.99') without format
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/functions-string.html
    */
    function sql_substring($aField, $start, $num) {
        return  "SUBSTR($aField, $start, $num)";
    }

    /*=======================================================================*/
    /*========= DML interface ===============================================*/
    /*=======================================================================*/

    /**
    * @see http://www.postgresql.org/docs/current/static/ddl-alter.html
    */
    function _change_field($table, $field, $field_definition) {
        return sprintf("ALTER TABLE $table ALTER COLUMN $field $field_definition");
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/ddl-alter.html
    */
    function _drop_field($table, $field) {
        return sprintf("ALTER TABLE $table DROP COLUMN $field CASCADE");
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/infoschema-columns.html
    * @see http://stackoverflow.com/questions/9991043/how-can-i-test-if-a-column-exists-in-a-table-using-an-sql-statement
    */
    function column_exists($table_name, $column_name) {
        $query = "SELECT column_name
                    FROM information_schema.columns
                    WHERE table_name = '" . mb_strtolower($table_name) . "'
                        and column_name = '" . mb_strtolower($column_name) . "'";
        if(!$this->query($query)) 
        {
            return false;
        }
        if($this->next_record()) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/infoschema-table-constraints.html
    */
    function constraint_exists($constraint_name, $table_name = '') {
        $query = "SELECT constraint_name
                    FROM information_schema.table_constraints
                    WHERE constraint_name = '" . mb_strtolower($constraint_name) . "'";
        if(!empty($table_name)){
            $query .= " and table_name = '" . mb_strtolower($table_name) . "'";
        }
        if(!$this->query($query)) 
        {
            return false;
        }
        if($this->next_record()) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/sql-altertable.html
    */
    function drop_constraint($table_name, $constraint_name) {
        if(!$this->constraint_exists($constraint_name)) {
            return false;
        }
        return $this->query("ALTER TABLE $table_name DROP CONSTRAINT $constraint_name CASCADE");
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/infoschema-table-constraints.html
    * @see http://www.postgresql.org/docs/current/static/infoschema-constraint-column-usage.html
    * @see http://www.postgresql.org/docs/current/static/infoschema-key-column-usage.html
    * @see http://stackoverflow.com/questions/1152260/postgres-sql-to-list-table-foreign-keys
    */
    function get_foreign_constraint_name($table, $columns, $r_table) {
        $columns_lower = array();
        foreach ($columns as $column) {
            $columns_lower[] = mb_strtolower($column);
        }

        if(!$this->query("
            SELECT tc.constraint_name AS constraint_name, 
                kcu.column_name AS column_name
            FROM information_schema.table_constraints tc 
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY' 
                AND tc.table_name = '" . mb_strtolower($table) . "'
                AND ccu.table_name = '" . mb_strtolower($r_table) . "
            "))
        {
            return false;
        }

        $fks = array();
        while($this->next_record()) {
            $fks[$this->f('constraint_name')][] = $this->f('column_name');
        }

        foreach ($fks as $constraint_name => $c_columns) {
            if(count($columns_lower) != count($c_columns)) {
                continue;
            }
            foreach ($c_columns as $c_column) {
                if(!in_array($c_column, $columns_lower)) {
                    break;
                }
                $found = true;
            }
            if($found) {
                return mb_strtolower($constraint_name);
            }
        }
        return false;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/sql-altertable.html
    */
    function rename_field($table, $orig, $new1, $field_definition = '') {
        if(!$this->column_exists($table, $orig)) {
            return false;
        }

        if (strtolower($orig) === strtolower($new1)) {
            return TRUE;
        }

        $q = sprintf("ALTER TABLE $table RENAME COLUMN $orig TO $new1");
        return $this->query($q);
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/sql-altertable.html
    */
    function rename_table($orig, $new1) {
        if(!$this->table_exists($orig)) {
            return false;
        }
        if($this->table_exists($new1)) {
            return false;
        }
        $q = sprintf("ALTER TABLE $orig RENAME TO $new1");
        $rs = $this->query($q);
        if (!$rs) {
            return false;
        }

        return true;
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/view-pg-tables.html
    */
    function table_exists($table_name) {
        if(!$this->query("
            SELECT tablename
                FROM pg_tables
                WHERE LOWER(tablename) = '" . mb_strtolower($table_name) . "'"
            ))
        {
            return false;
        }
        if($this->next_record()) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/view-pg-views.html
    */
    function view_exists($view_name) {
        if(!$this->query("
            SELECT viewname
                FROM pg_views
                WHERE LOWER(viewname) = '" . mb_strtolower($view_name) . "'"
            )) 
        {
            return false;
        }
        if($this->next_record()) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/view-pg-tables.html
    */
    function table_names() {
        $recordset = $this->query('SELECT tablename, tablespace FROM pg_tables');
        if (!$recordset) {
            return $this->halt('table_names');
        }
        $ary = array();

        $i = 0;
        while ($record = $this->next_record($recordset)) {
            $ary[$i]['table_name'] = mb_strtolower($this->f('tablename'));
            $ary[$i]['tablespace_name'] = mb_strtolower($this->f('tablespace'));
            $ary[$i]['database'] = $this->_database;
            $i++;
        }

        return $ary;
    }
}