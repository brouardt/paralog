<?php

if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

/**
 * Description of paralog_common
 *
 * @author thier
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


}
