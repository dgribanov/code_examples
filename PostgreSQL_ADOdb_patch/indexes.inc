<?php
itk_include_class('Itk_Db_Indexes');

class Itk_Db_Pgsql_Indexes extends Itk_Db_Indexes {

    /**
     * Rename an index to a new name
     *
     * @access public
     * @see http://www.postgresql.org/docs/current/static/sql-alterindex.html
     * @param string $table_name
     * @param string $index_name
     * @param string $new_index_name
     */
     function rename_index($table_name, $index_name, $new_index_name) {
        $index_name = strtolower($index_name);
        $new_index_name = strtolower($new_index_name);
        if (!$this->index_name_exists($table_name, $index_name)) return TRUE;
        return $this->db->query("ALTER INDEX $index_name RENAME TO $new_index_name");
    }

    /*
    * Drop index
    * 
    * @protected
    * @see http://www.postgresql.org/docs/current/static/sql-dropindex.html
    */
    function drop_index($tableName, $indexName) {
        $tableName = strtolower($tableName);
        $indexName = strtolower($indexName);
        return $this->db->query("DROP INDEX $indexName");
    }

    /**
    * @see http://www.postgresql.org/docs/current/static/view-pg-indexes.html
    */
    function index_name_exists($tableName, $indexName) {
        $indexName = strtolower($indexName);
        if(!$this->db->query("SELECT indexname
                            FROM pg_indexes
                            WHERE indexname = '$indexName'")) {
            return true;
        }
        if($this->db->next_record()) {
            return true;
        }
        return false;
    }

    /**
    * @see http://stackoverflow.com/questions/2204058/list-columns-with-indexes-in-postgresql
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-class.html
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-index.html
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-attribute.html
    * @see http://www.postgresql.org/docs/current/static/catalog-pg-constraint.html
    */
    function _get_index($tableName) {
        $idxs = array();
        $tableName = strtolower($tableName);

        if(!$this->db->query("
                SELECT
                    i.relname AS index_name,
                    a.attname AS column_name,
                    ix.indisunique AS uniqueness,
                    c.conname AS constraint_name,
                    c.contype AS constraint_type
                FROM pg_class t
                JOIN pg_index ix ON t.oid = ix.indrelid
                JOIN pg_class i ON i.oid = ix.indexrelid
                JOIN pg_attribute a ON a.attrelid = t.oid
                JOIN pg_constraint c ON c.conindid = ix.indexrelid
                WHERE t.relkind = 'r'::char
                    AND t.relname = '$tableName'
                    AND a.attnum = ANY(ix.indkey)
            ")
        ) {
            return $idxs;
        }

        $index = itk_new('Itk_Db_Pgsql_Index');
        while($this->db->next_record()) {
            $index = &$this->get_index_from_array($idxs, $this->db->f('index_name'));

            if(!is_object($index)) {
                $index = itk_new('Itk_Db_Pgsql_Index');
                $index->set_name($this->db->f('index_name'));
                $index->set_type($this->db->f('uniqueness'), $this->db->f('constraint_name'), $this->db->f('constraint_type'));
                $index->add_column($this->db->f('column_name'));
                $idxs[] = $index;
            } else {
                $index->add_column($this->db->f('column_name'));
            }
        }
        return $idxs;
    }

    function &get_index_from_array(&$idxs, $indexName) {
        foreach ($idxs as $key => $index) {
            if($index->get_name() == $indexName) {
                return $idxs[$key];
            }
        }
        return false;
    }
}
?>