<?php
if (!defined('ABSPATH')) {
    wp_die('No direct access allowed', 'Security');
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Table commune à Paralog_(activity,site,line,person,log)
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Table extends WP_List_Table
{
    protected $table;
    protected $primary_key;

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array $items
     *
     * @return Paralog_Table
     */
    public function setItems(array $items): Paralog_Table
    {
        $this->items = $items;

        return $this;
    }

    public function process_bulk_action()
    {
        global $wpdb;

        $table = $this->getTable();
        $primary = $this->getPrimary();

        if ('delete' == $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (!empty($ids)) {
                $query = '';
                if (is_array($ids)) {
                    $ids = implode(',', $ids);
                    $query = "UPDATE `$table` SET `deleted` = 1 WHERE `$primary` IN($ids)";
                } else {
                    $is_author = $this->is_id_belong_to_user($ids);
                    if ($is_author) {
                        $query = "UPDATE `$table` SET `deleted` = 1 WHERE `$primary` = $ids";
                    }
                }
                if ($query) {
                    $wpdb->query($query);
                }
            }
        }
    }

    /**
     * @return String
     */
    protected function getTable()
    {
        return Paralog::table_name($this->table);
    }

    /**
     * @param String $table
     *
     * @return Object $this
     */
    protected function setTable($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @return String
     */
    protected function getPrimary()
    {
        return $this->primary_key;
    }

    /**
     * @param Integer $id
     *
     * @return Boolean
     */
    protected function is_id_belong_to_user($id)
    {
        global $wpdb;

        $query = sprintf(
            "SELECT TRUE FROM %s WHERE `user_id` = %d AND `%s` = %d",
            $this->getTable(),
            get_current_user_id(),
            $this->getPrimary(),
            $id
        );

        $result = $wpdb->get_var($query);

        return $result ? true : false;
    }

    /**
     * @param String $primary_key
     *
     * @return Object $this
     */
    protected function setPrimary($primary_key)
    {
        $this->primary_key = $primary_key;

        return $this;
    }

    /**
     * @param array $item
     * @return String
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

    /**
     * @name person_name_type
     *
     * @param Integer id
     * @param String type
     *
     * @return Object
     */
    protected function person_name_type($id, $type)
    {
        global $wpdb;

        $table = Paralog::table_name('persons');
        $query = $wpdb->prepare("SELECT " .
            "CONCAT_WS(' ', `firstname`, `lastname`) AS 'name', " .
            "`$type` AS 'type' " .
            "FROM `$table` " .
            "WHERE `deleted` = 0 " .
            "AND `person_id` = %d",
            $id
        );

        return $wpdb->get_row($query, OBJECT);
    }

    /**
     * @name pilot_name_items
     * @return Array
     */
    protected function pilot_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('persons');
        $query = "SELECT " .
            "`person_id`, " .
            "CONCAT_WS(' ', `firstname`, `lastname`) AS 'name' " .
            "FROM `$table` " .
            "WHERE `deleted` = 0 " .
            "ORDER BY `lastname` ASC, `firstname` ASC";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @name winchman_name_items
     * @return Array
     */
    protected function winchman_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('persons');
        $query = $wpdb->prepare("SELECT " .
            "`person_id`, " .
            "CONCAT_WS(' ', `firstname`, `lastname`) AS 'name' " .
            "FROM `$table` " .
            "WHERE `deleted` = 0 " .
            "AND `winchman` LIKE %s " .
            "ORDER BY `lastname` ASC, `firstname` ASC",
            __('oui', PL_DOMAIN)
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @name line_name_items
     * @return Array
     */
    protected function line_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('lines');
        $query = "SELECT `name` " .
            "FROM `$table` " .
            "WHERE `deleted` = 0 " .
            "ORDER BY `name`";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @name site_name_items
     * @return Array
     */
    protected function site_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('sites');
        $query = "SELECT `name` " .
            "FROM `$table` " .
            "WHERE `deleted` = 0 " .
            "ORDER BY `name`";

        return $wpdb->get_results($query, ARRAY_A);
    }

    protected function color_person($item)
    {
        return ($item == __('élève', PL_DOMAIN)) ? 'orange' : 'green';
    }
}
