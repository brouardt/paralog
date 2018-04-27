<?php

if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
/**
 * Classe commune à Paralog
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Table extends WP_List_Table
{
    protected $table;
    protected $primary_key;

    /**
     * @param string $table
     * @return $this
     */
    protected function setTable($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @return string
     */
    protected function getTable() {
        return Paralog::table_name($this->table);
    }

    /**
     * @param string $primary_key
     * @return $this
     */
    protected function setPrimary($primary_key)
    {
        $this->primary_key = $primary_key;

        return $this;
    }

    /**
     * @return string
     */
    protected function getPrimary() 
    {
        return $this->primary_key;
    }

    /**
     * @return string
     */
    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="id[]" value="%s" />', $item[$this->getPrimary()]);
    }

    protected function get_bulk_actions()
    {
        if (current_user_can('delete_others_posts')) {
            $bulk_actions = array(
                'delete' => __('Supprimer', PL_DOMAIN),
            );
        } else {
            $bulk_actions = array();
        }

        return $bulk_actions;
    }

    public function process_bulk_action()
    {
        global $wpdb;

        $table = $this->getTable();
        $primary = $this->getPrimary();

        if('delete' == $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (!empty($ids)) {
                $query = '';
                if (is_array($ids)){
                    $ids = implode(',', $ids);
                    $query = "UPDATE $table SET deleted = 1 WHERE $primary IN($ids)";
                } else {
                    $is_author = $this->is_id_belong_to_user($ids);
                    if( $is_author ) 
                    {
                        $query = "UPDATE $table SET deleted = 1 WHERE $primary = $ids";
                    }
                }
                if( $query)
                {
                    $wpdb->query($query);
                }
            }
        }
    }

    /**
     * @param integer $id
     * @return boolean
     */
    protected function is_id_belong_to_user($id) 
    {
        global $wpdb;
        
        $query = sprintf(
            'SELECT TRUE FROM %s WHERE user_id = %d AND %s = %d', 
            $this->getTable(), 
            get_current_user_id(), 
            $this->getPrimary(), 
            $id
        );

        $result = $wpdb->get_var($query);

        return $result ? true : false;
    }
}