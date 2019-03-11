<?php
if (!defined('ABSPATH')) {
    die('No direct access allowed');
}

if (!class_exists('Paralog_Table')) {
    require_once plugin_dir_path(__FILE__) . '/paralog_table.php';
}

/**
 * @package Paralog_Log
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Log extends Paralog_Table
{

    private $datetime_format = null;

    public function __construct()
    {
        parent::__construct(array(
            'singular' => __('personne'), //singular name of the listed records
            'plural' => __('personnes'), //plural name of the listed records
            'ajax' => false, //does this table support ajax?
        ));
        
        $this->setTable('logs');
        $this->setPrimary('log_id');
        
        $this->datetime_format = get_option('date_format') . ' ' . get_option('time_format');

        add_action('init',array($this, 'define_cookies'));
    }

    public function get_columns()
    {
        if (current_user_can('delete_others_posts')) {
            $columns = array(
                'cb' => '<input type="checkbox" />',
            );
        } else {
            $columns = array();
        }

        $columns = array_merge($columns, array(
            'takeoff' => __("Décollage"),
            'site_name' => __("Site"),
            'line_name' => __("Ligne"),
            'winchman_name' => __("Treuilleur"),
            'pilot_name' => __("Pilote"),
            'passenger_name' => __("Passager"),
        ));

        return $columns;
    }

    public function column_takeoff($item)
    {
        $actions = array();
        $user_id = get_current_user_id();
        $primary = $this->getPrimary();

        if (current_user_can('edit_others_posts') || ($item['user_id'] == $user_id)) {
            $actions = array_merge($actions, array(
                'edit' => sprintf('<a href="?page=%s-form&id=%d">%s</a>', $_REQUEST['page'], $item[$primary], __('Modifier')),
            ));
        }
        if (current_user_can('delete_others_posts') || ($item['user_id'] == $user_id)) {
            $actions = array_merge($actions, array(
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d">%s</a>', $_REQUEST['page'], 'delete', $item[$primary], __('Supprimer')),
            ));
        }

        return sprintf('%1$s %2$s', mysql2date($this->datetime_format, $item['takeoff']), $this->row_actions($actions));
    }

    public function prepare_items()
    {
        global $wpdb;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $per_page = $this->get_items_per_page('items_per_page', 10);
        $paged = isset($_REQUEST['paged']) ? ($per_page * max(0, intval($_REQUEST['paged']) - 1)) : 0;
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'log_id';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';

        $table = $this->getTable();

        $query = $wpdb->prepare(
            "SELECT "
            . "log_id, "
            . "site_name, line_name, "
            . "winchman_name, winchman_type, "
            . "pilot_name, pilot_type, passenger_name, "
            . "takeoff, "
            . "user_id "
            . "FROM $table "
            . "WHERE deleted = 0 "
            . "ORDER BY $orderby $order "
            . "LIMIT %d OFFSET %d",
            $per_page,
            $paged
        );
        $this->items = $wpdb->get_results($query, ARRAY_A);

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deleted = 0");

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page),
            'per_page' => $per_page,
        ));
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'site_name':
            case 'line_name':
            case 'passenger_name':
                return $item[$column_name];
            case 'winchman_name':
                return $item[$column_name] . '<br><span style="font-size: smaller;color: ' . $this->color_person($item['winchman_type']) . '">' . $item['winchman_type'] . '</span>';
            case 'pilot_name':
                return $item[$column_name] . '<br><span style="font-size: smaller;color: ' . $this->color_person($item['pilot_type']) . '">' . $item['pilot_type'] . '</span>';
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'takeoff' => array('takeoff', false),
            'site_name' => array('site_name', false),
            'line_name' => array('line_name', false),
            'winchman_name' => array('winchman_name', false),
            'pilot_name' => array('pilot_name', false),
            'passenger_name' => array('passenger_name', false),
        );

        return $sortable_columns;
    }

    public function form_edit()
    {
        global $wpdb;

        $table = $this->getTable();
        $primary = $this->getPrimary();

        $message = '';
        $notice = '';

        $wmid = null;

        $default = array(
            'log_id' => 0,
            'site_name' => '',
            'line_name' => '',
            'winchman_id' => null,
            'pilot_id' => null,
            'passenger_name' => '',
            'total_flying_weight' => null,
            'takeoff' => null,
            'takeoff_date' => null,
            'takeoff_time' => null,
            'user_id' => get_current_user_id(),
        );

        if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
            // combine our default item with request params
            $request = stripslashes_deep($_REQUEST);
            $item = shortcode_atts($default, $request);

            $item_valid = $this->form_validate($item);

            if ($item_valid === true) {
                if (isset($_REQUEST['winchman_id']) && !empty($_REQUEST['winchman_id'])) {
                    $winchman = $this->person_name_type($_REQUEST['winchman_id'], 'winchman_type');
                    $item['winchman_name'] = $winchman->name;
                    $item['winchman_type'] = $winchman->type;
                }
                $pilot = $this->person_name_type($_REQUEST['pilot_id'], 'pilot_type');
                $item['pilot_name'] = $pilot->name;
                $item['pilot_type'] = $pilot->type;

                $datetime = new DateTime(current_time('mysql'));
                $item['takeoff_date'] = empty($item['takeoff_date']) ? $datetime->format('Y-m-d') : $item['takeoff_date'];
                $item['takeoff_time'] = empty($item['takeoff_time']) ? $datetime->format('H:i:s') : $item['takeoff_time'];
                $item['takeoff'] = $item['takeoff_date'] . ' ' . $item['takeoff_time'];

                if (trim($item['passenger_name'] == '')) {
                    $item['passenger_name'] = null;
                }
                
                $row = $item;
                unset($row['winchman_id'], $row['pilot_id'], $row['takeoff_date'], $row['takeoff_time']);

                if ($row[$primary] == 0) {
                    $result = $wpdb->insert($table, $row);
                    $row[$primary] = $wpdb->insert_id;
                    if ($result !== false) {
                        $message = __("Décollage enregistré");
                    } else {
                        $notice = __("Un erreur est apparue lors de la sauvegarde");
                    }
                } else {
                    $result = $wpdb->update($table, $row, array($primary => $row[$primary]));
                    if ($result !== false) {
                        $message = __("Décollage mis à jour");
                    } else {
                        $notice = __("Un erreur est apparue lors de la mise à jour");
                    }
                }
            } else {
                // if $item_valid not true it contains error message(s)
                $notice = $item_valid;
            }
        } else {
            // if this is not post back we load item to edit or give new one to create
            $default['site_name'] = $this->get_cookie('pl_site_name', '');
            $default['line_name'] = $this->get_cookie('pl_line_name', '');
            $default['winchman_name'] = $this->get_cookie('pl_winchman_name', '');

            $item = $default;
            if (isset($_REQUEST['id'])) {
                $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE $primary = %d", $_REQUEST['id']), ARRAY_A);
                if (!$item) {
                    $item = $default;
                    $notice = __('Donnée introuvable');
                } else {
                    $datetime = new DateTime($item['takeoff']);
                    $item['takeoff_date'] = $datetime->format('Y-m-d');
                    $item['takeoff_time'] = $datetime->format('H:i:s');
                }
            } else {
                $information = __("Si vous laissez les champs date et heure vide, lors de la sauvegarde, ceux-ci prendront automatiquement le date et l'heure courante.");
            }
        }
        add_meta_box('log_form_meta_box', 'Journal', array($this, 'log_form_meta_box_handler'), 'log', 'normal', 'default');
        ?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1><?php _e('Fiche de décollage / treuillé');?> <a class="add-new-h2" href="<?=get_admin_url(get_current_blog_id(), sprintf('admin.php?page=paralog-logs&paged=%d', $this->get_pagenum()))?>"><?php _e('retour à la liste')?></a></h1>
            <?php if(!empty($information)):  ?>
                <div id="information" class="notice notice-info is-dismissible"><p><?=$information?></div>
            <?php endif; ?>
            <?php if (!empty($notice)): ?>
                <div id="notice" class="error"><p><?=$notice?></p></div>
            <?php endif;?>
            <?php if (!empty($message)): ?>
                <div id="message" class="updated"><p><?=$message?></p></div>
            <?php endif;?>
            <form id="form" method="post">
                <input type="hidden" name="nonce" value="<?=wp_create_nonce(basename(__FILE__))?>"/>
                <input type="hidden" name="<?= $primary ?>" value="<?=esc_attr($item[$primary])?>"/>
                <div class="metabox-holder" id="postsite">
                    <div id="post-body">
                        <div id="post-body-content">
                            <?php do_meta_boxes('log', 'normal', $item)?>
                            <input type="submit" value="<?php _e('Sauver');?>" id="submit" class="button-primary" name="submit">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function log_form_meta_box_handler($item)
    {
        $sites = $this->site_name_items();
        $lines = $this->line_name_items();
        $pilots = $this->pilot_name_items();
        $winchmen = $this->winchman_name_items();
        ?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="site_name"><?php _e('Nom du site')?></label>
                    </th>
                    <td>
                        <select id="site_name" name="site_name">
                            <option value=""></option>
                            <?php foreach ($sites as $site): ?>
                            <option value="<?=esc_attr($site['name'])?>"<?=($site['name'] == $item['site_name'] ? ' selected' : '')?>><?=esc_html($site['name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="line_name"><?php _e('Nom de la ligne')?></label>
                    </th>
                    <td>
                        <select id="line_name" name="line_name">
                            <option value=""></option>
                            <?php foreach ($lines as $line): ?>
                            <option value="<?=esc_attr($line['name'])?>"<?=($line['name'] == $item['line_name'] ? ' selected' : '')?>><?=esc_html($line['name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman_id"><?php _e('Nom du treuilleur')?></label>
                    </th>
                    <td>
                        <select id="winchman_id" name="winchman_id">
                            <option value=""></option>
                            <?php foreach ($winchmen as $winchman): ?>
                            <option value="<?=$winchman['person_id']?>"<?=($winchman['name'] == $item['winchman_name'] ? ' selected' : '')?>><?=esc_html($winchman['name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="pilot_id"><?php _e('Nom du pilote')?></label>
                    </th>
                    <td>
                        <select id="pilot_id" name="pilot_id">
                            <option value=""></option>
                            <?php foreach ($pilots as $pilot): ?>
                            <option value="<?=$pilot['person_id']?>"<?=($pilot['name'] == $item['pilot_name'] ? ' selected' : '')?>><?=esc_html($pilot['name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="passenger_name"><?php _e('Nom du passager')?></label>
                    </th>
                    <td>
                        <input id="passenger_name" name="passenger_name" type="text" style="width: 95%" value="<?=esc_attr($item['passenger_name'])?>" size="50" maxlength="129" class="code" placeholder="<?php _e('ex: Joe-Henri BLACK')?>"/>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="total_flying_weight"><?php _e('Poid total volant (PTV)')?></label>
                    </th>
                    <td>
                        <input id="total_flying_weight" name="total_flying_weight" type="text" style="width: 5em" value="<?=esc_attr($item['total_flying_weight'])?>" size="5" maxlength="4" class="code" placeholder="<?php _e('ex: 123')?>"/>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="takeoff_date"><?php _e('Date')?></label>
                    </th>
                    <td>
                        <input id="takeoff_date" name="takeoff_date" type="date" value="<?=esc_attr($item['takeoff_date'])?>" class="code" /> 
                    </td>
                </tr>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="takeoff_time"><?php _e('Heure')?></label>
                    </th>
                    <td>
                        <input id="takeoff_time" name="takeoff_time" type="time" step="1" min="00:00:00" max="23:59:59" value="<?=esc_attr($item['takeoff_time'])?>" class="code" />
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private function form_validate($item)
    {
        $messages = array();

        if (empty($item['site_name'])) {
            $messages[] = __('Le nom du site est obligatoire');
        }
        if (empty($item['line_name'])) {
            $messages[] = __('Le nom de la ligne est obligatoire');
        }
        if (empty($item['pilot_id'])) {
            $messages[] = __('Le nom du pilote est obligatoire');
        }
        if (is_int($item['total_flying_weight'])) {
            $messages[] = __('Le poid total volant (PTV) est obligatoire');
        }

        if (empty($messages)) {
            return true;
        }

        return implode('<br />', $messages);
    }

    /**
     * @name define_cookies
     */
    public function define_cookies() 
    {
        $fields = array('site_name', 'line_name', 'winchman_name');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $expire = strtotime('+12 hours');
            foreach($fields as $field) {
                if( isset($_POST[$field])) {
                    $this->set_cookie('pl_' . $field, $_POST[$field], $expire);
                }
            }
        }
    }

    /**
     * @name set_cookie
     * @param string $name
     * @param mixed $value
     */
    private function set_cookie($name, $value, $expire = 0)
    {
        setcookie($name, $value, $expire, COOKIEPATH, COOKIE_DOMAIN);
    }

    /**
     * @name get_cookie
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    private function get_cookie($name, $default = null)
    {
        return isset($_COOKIE[$name]) ? stripslashes($_COOKIE[$name]) : $default;
    }

}
