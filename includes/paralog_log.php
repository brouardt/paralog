<?php

if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

/**
 * Description of paralog_log
 *
 * @author thier
 */
class Paralog_Log extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct(array(
            'singular' => __('personne', PL_DOMAIN), //singular name of the listed records
            'plural' => __('personnes', PL_DOMAIN), //plural name of the listed records
            'ajax' => false                    //does this table support ajax?
        ));
    }

    public function get_columns()
    {
        if (current_user_can('delete_posts')) {
            $columns = array(
                'cb' => '<input type="checkbox" />'
            );
        } else {
            $columns = array();
        }

        $columns = array_merge($columns, array(
            'site_name' => __("Site", PL_DOMAIN),
            'line_name' => __("Ligne", PL_DOMAIN),
            'winchman_name' => __("Treuilleur", PL_DOMAIN),
            'pilot_name' => __("Pilote", PL_DOMAIN),
            'passenger_name' => __("Passager", PL_DOMAIN),
            'takeoff' => __("Décollage", PL_DOMAIN),
            'log_id' => __("Vol N°", PL_DOMAIN)
        ));

        return $columns;
    }

    public function column_site_name($item)
    {
        $actions = array();

        if (current_user_can('edit_posts')) {
            $actions = array_merge($actions, array(
                'edit' => sprintf('<a href="?page=%s-form&id=%d">' . __('Modifier', PL_DOMAIN) . '</a>', $_REQUEST['page'], $item['log_id'])
            ));
        }
        if (current_user_can('delete_posts')) {
            $actions = array_merge($actions, array(
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d">' . __('Supprimer', PL_DOMAIN) . '</a>', $_REQUEST['page'], 'delete', $item['log_id'])
            ));
        }

        return sprintf('%1$s %2$s', $item['site_name'], $this->row_actions($actions));
    }

    protected function get_bulk_actions()
    {
        if (current_user_can('delete_posts')) {
            $bulk_actions = array(
                'delete' => __('Supprimer', PL_DOMAIN)
            );
        } else {
            $bulk_actions = array();
        }

        return $bulk_actions;
    }

    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="id[]" value="%s" />', $item['log_id']);
    }

    public function process_bulk_action()
    {
        global $wpdb;

        if ('delete' === $this->current_action() && current_user_can('delete_posts')) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) {
                $ids = implode(',', $ids);
            }

            if (!empty($ids)) {
                $query = "DELETE FROM " . Paralog::table_name('logs') . " WHERE log_id IN($ids)";
                $wpdb->query($query);
            }
        }
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

        $table = Paralog::table_name('logs');

        $query = $wpdb->prepare(
            "SELECT "
            . "log_id, "
            . "site_name, line_name, "
            . "winchman_name, winchman_type, "
            . "pilot_name, pilot_type, passenger_name, "
            . "takeoff "
            . "FROM $table "
            . "ORDER BY $orderby $order "
            . "LIMIT %d OFFSET %d",
            $per_page,
            $paged
        );
        $this->items = $wpdb->get_results($query, ARRAY_A);

        $total_items = $wpdb->get_var("SELECT COUNT(log_id) FROM $table");

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page),
            'per_page' => $per_page
        ));
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'log_id':
            case 'site_name':
            case 'line_name':
            case 'passenger_name':
                return $item[$column_name];
            case 'winchman_name':
            return $item[$column_name] . '<br><span style="font-size: smaller;color: ' . $this->color_person($item['winchman_type']) . '">' . $item['winchman_type'] . '</span>';
            case 'pilot_name':
            return $item[$column_name] . '<br><span style="font-size: smaller;color: ' . $this->color_person($item['pilot_type']) . '">' . $item['pilot_type'] . '</span>';
            case 'takeoff':
                return mysql2date(__('d/m/Y H:i:s', PL_DOMAIN), $item[$column_name]);
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    private function color_person($item)
    {
        return ($item == __('élève', PL_DOMAIN)) ? 'orange' : 'green';
    }

    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'log_id' => array('log_id', false),
            'site_name' => array('site_name', false),
            'line_name' => array('line_name', false),
            'winchman_name' => array('winchman_name', false),
            'pilot_name' => array('pilot_name', false),
            'passenger_name' => array('passenger_name', false),
            'takeoff' => array('takeoff', false)
        );

        return $sortable_columns;
    }

    public function form_edit()
    {
        global $wpdb;

        $table = Paralog::table_name('logs');

        $message = '';
        $notice = '';

        $default = array(
            'log_id' => 0,
            'site_name' => '',
            'line_name' => '',
            'winchman_id' => null,
            'pilot_id' => null,
            'passenger_name' => '',
            'total_flying_weight' => null,
            'takeoff' => null
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

                $item['takeoff'] = date('Y-m-d H:i:s');
                
                unset($item['winchman_id'], $item['pilot_id']);

                if (trim($item['passenger_name'] == '')) {
                    $item['passenger_name'] = null;
                }

                if ($item['log_id'] == 0) {
                    $result = $wpdb->insert($table, $item);
                    $item['log_id'] = $wpdb->insert_id;
                    if ($result !== false) {
                        $message = __("Treuillé enregistré", PL_DOMAIN);
                    } else {
                        $notice = __("Un erreur est apparue lors de la sauvegarde", PL_DOMAIN);
                    }
                } else {
                    $result = $wpdb->update($table, $item, array('log_id' => $item['log_id']));
                    if ($result !== false) {
                        $message = __("Treuillé mis à jour", PL_DOMAIN);
                    } else {
                        $notice = __("Un erreur est apparue lors de la mise à jour", PL_DOMAIN);
                    }
                }
            } else {
                // if $item_valid not true it contains error message(s)
                $notice = $item_valid;
            }
        } else {
            // if this is not post back we load item to edit or give new one to create
            $item = $default;
            if (isset($_REQUEST['id'])) {
                $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE log_id = %d", $_REQUEST['id']), ARRAY_A);
                if (!$item) {
                    $item = $default;
                    $notice = __('Donnée introuvable', PL_DOMAIN);
                }
            }
        }
        add_meta_box('log_form_meta_box', 'Journal', array($this, 'log_form_meta_box_handler'), 'log', 'normal', 'default'); ?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1><?= _e('Fiche de décollage / treuillé', PL_DOMAIN); ?> <a class="add-new-h2" href="<?= get_admin_url(get_current_blog_id(), sprintf('admin.php?page=paralog-logs&paged=%d', $this->get_pagenum())) ?>"><?= _e('retour à la liste', PL_DOMAIN) ?></a></h1>
            <?php if (!empty($notice)): ?>
                <div id="notice" class="error"><p><?= $notice ?></p></div>
            <?php endif; ?>
            <?php if (!empty($message)): ?>
                <div id="message" class="updated"><p><?= $message ?></p></div>
            <?php endif; ?>
            <form id="form" method="post">
                <input type="hidden" name="nonce" value="<?= wp_create_nonce(basename(__FILE__)) ?>"/>
                <input type="hidden" name="log_id" value="<?= esc_attr($item['log_id']) ?>"/>
                <div class="metabox-holder" id="postsite">
                    <div id="post-body">
                        <div id="post-body-content">
                            <?php do_meta_boxes('log', 'normal', $item) ?>
                            <input type="submit" value="<?= _e('Sauver', PL_DOMAIN); ?>" id="submit" class="button-primary" name="submit">
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
        $winchmen = $this->winchman_name_items(); ?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="site_name"><?= _e('Nom du site', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <select id="site_name" name="site_name">
                            <option value=""></option>
                            <?php foreach ($sites as $site) :?>
                            <option value="<?= esc_attr($site['name']) ?>"<?= ($site['name'] == $item['site_name'] ? ' selected':'') ?>><?= esc_html($site['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="line_name"><?= _e('Nom de la ligne', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <select id="line_name" name="line_name">
                            <option value=""></option>
                            <?php foreach ($lines as $line) :?>
                            <option value="<?= esc_attr($line['name']) ?>"<?= ($line['name'] == $item['line_name'] ? ' selected':'') ?>><?= esc_html($line['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman_id"><?= _e('Nom du treuilleur', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <select id="winchman_id" name="winchman_id">
                            <option value=""></option>
                            <?php foreach ($winchmen as $winchman) :?>
                            <option value="<?= $winchman['person_id'] ?>"<?= ($winchman['name'] == $item['winchman_name'] ? ' selected':'') ?>><?= esc_html($winchman['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="pilot_id"><?= _e('Nom du pilote', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <select id="pilot_id" name="pilot_id">
                            <option value=""></option>
                            <?php foreach ($pilots as $pilot) :?>
                            <option value="<?= $pilot['person_id'] ?>"<?= ($pilot['name'] == $item['pilot_name'] ? ' selected':'') ?>><?= esc_html($pilot['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="passenger_name"><?= _e('Nom du passager', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <input id="passenger_name" name="passenger_name" type="text" style="width: 95%" value="<?= esc_attr($item['passenger_name']) ?>" size="50" maxlength="129" class="code" placeholder="<?= _e('ex: Joe-Henri BLACK', PL_DOMAIN) ?>"/>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="total_flying_weight"><?= _e('Poid total volant (PTV)', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <input id="total_flying_weight" name="total_flying_weight" type="text" style="width: 5em" value="<?= esc_attr($item['total_flying_weight']) ?>" size="5" maxlength="4" class="code" placeholder="<?= _e('ex: 123', PL_DOMAIN) ?>"/>
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
            $messages[] = __('Le nom du site est obligatoire', PL_DOMAIN);
        }
        if (empty($item['line_name'])) {
            $messages[] = __('Le nom de la ligne est obligatoire', PL_DOMAIN);
        }
        /*if (empty($item['winchman_id'])) {
            $messages[] = __('Le nom du treuilleur est obligatoire', PL_DOMAIN);
        }*/
        if (empty($item['pilot_id'])) {
            $messages[] = __('Le nom du pilote est obligatoire', PL_DOMAIN);
        }
        if (is_int($item['total_flying_weight'])) {
            $messages[] = __('Le poid total volant (PTV) est obligatoire', PL_DOMAIN);
        }

        if (empty($messages)) {
            return true;
        }

        return implode('<br />', $messages);
    }

    /**
     * @name pilot_name_items
     * @return array
     */
    private function pilot_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('persons');
        $query = "SELECT person_id, CONCAT_WS(' ', firstname, lastname) AS name FROM $table ORDER BY lastname ASC, firstname ASC;";

        return $wpdb->get_results($query, ARRAY_A);
    }
    /**
     * @name winchman_name_items
     * @return array
     */
    private function winchman_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('persons');
        $query = $wpdb->prepare(
            "SELECT person_id, CONCAT_WS(' ', firstname, lastname) AS name FROM $table WHERE winchman LIKE %s ORDER BY lastname ASC, firstname ASC;",
            __('oui', PL_DOMAIN)
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @name line_name_items
     * @return array
     */
    private function line_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('lines');
        $query = "SELECT name FROM $table ORDER BY name;";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @name site_name_items
     * @return array
     */
    private function site_name_items()
    {
        global $wpdb;

        $table = Paralog::table_name('sites');
        $query = "SELECT name FROM $table ORDER BY name;";

        return $wpdb->get_results($query, ARRAY_A);
    }

    private function person_name_type($id, $type)
    {
        global $wpdb;

        $table = Paralog::table_name('persons');
        $query = $wpdb->prepare("SELECT CONCAT_WS(' ', firstname, lastname) AS name, $type AS type FROM $table WHERE person_id = %d;", $id);

        return $wpdb->get_row($query, OBJECT);
    }
}
