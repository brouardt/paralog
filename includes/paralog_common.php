<?php

if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

/**
 * Description of paralog_common
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
trait Paralog_Common
{
    protected function is_id_belong_to_user($table, $primary_key, $id) 
    {
        global $wpdb;
        
        $query = sprintf(
            'SELECT TRUE FROM %s WHERE user_id = %d AND %s = %d', 
            $table, 
            get_current_user_id(), 
            $primary_key, 
            $id
        );

        $result = $wpdb->get_var($query);

        return $result ? true : false;
    }

    public function process_bulk_action()
    {
        global $wpdb;

        $table = Paralog::table_name($this->name);
        $clef_primaire = $this->primary_key;

        if('delete'==$this->current_action()){
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (!empty($ids)) {
                $query = '';
                if (is_array($ids)){
                    $ids = implode(',', $ids);
                    $query = "UPDATE $table SET deleted = 1 WHERE $clef_primaire IN($ids)";
                } else {
                    $is_author = $this->is_id_belong_to_user($table, $clef_primaire, $ids);
                    if( $is_author ) 
                    {
                        $query = "UPDATE $table SET deleted = 1 WHERE $clef_primaire = $ids";
                    }
                }
                if( $query)
                {
                    $wpdb->query($query);
                }
            }
        }
    }

}
