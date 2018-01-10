<?php
if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

/**
 * Description of paralog_site
 *
 * @author thier
 */
class Paralog_Site extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct(array(
            'singular' => __('site', PL_DOMAIN), //singular name of the listed records
            'plural' => __('sites', PL_DOMAIN), //plural name of the listed records
            'ajax' => false, //does this table support ajax?
        ));
    }

    public function get_columns()
    {
        if (current_user_can('delete_posts')) {
            $columns = array(
                'cb' => '<input type="checkbox" />',
            );
        } else {
            $columns = array();
        }

        $columns = array_merge($columns, array(
            'name' => __("Nom", PL_DOMAIN),
        ));

        return $columns;
    }

    public function column_name($item)
    {
        $actions = array();

        if (current_user_can('edit_posts')) {
            $actions = array_merge($actions, array(
                'edit' => sprintf('<a href="?page=%s-form&id=%d">' . __('Modifier', PL_DOMAIN) . '</a>', $_REQUEST['page'], $item['site_id']),
            ));
        }
        if (current_user_can('delete_posts')) {
            $actions = array_merge($actions, array(
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d">' . __('Supprimer', PL_DOMAIN) . '</a>', $_REQUEST['page'], 'delete', $item['site_id']),
            ));
        }

        return sprintf('%1$s %2$s', $item['name'], $this->row_actions($actions));
    }

    protected function get_bulk_actions()
    {
        if (current_user_can('delete_posts')) {
            $bulk_actions = array(
                'delete' => __('Supprimer', PL_DOMAIN),
            );
        } else {
            $bulk_actions = array();
        }

        return $bulk_actions;
    }

    protected function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="id[]" value="%s" />', $item['site_id']);
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
                $query = "DELETE FROM " . Paralog::table_name('sites') . " WHERE site_id IN($ids)";
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

        $per_page = $this->get_items_per_page('items_per_page', 5);
        $paged = isset($_REQUEST['paged']) ? ($per_page * max(0, intval($_REQUEST['paged']) - 1)) : 0;
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'name';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

        $table = Paralog::table_name('sites');

        $query = $wpdb->prepare(
            "SELECT site_id, name "
            . "FROM $table "
            . "ORDER BY $orderby $order "
            . "LIMIT %d OFFSET %d",
            $per_page,
            $paged
        );
        $this->items = $wpdb->get_results($query, ARRAY_A);

        $total_items = $wpdb->get_var("SELECT COUNT(site_id) FROM $table");

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page),
            'per_page' => $per_page,
        ));
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'name':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'name' => array('name', false),
        );

        return $sortable_columns;
    }

    public function form_edit()
    {
        global $wpdb;

        $table = Paralog::table_name('sites');

        $message = '';
        $notice = '';

        $default = array(
            'site_id' => 0,
            'name' => '',
        );

        if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
            // combine our default item with request params
            $request = stripslashes_deep($_REQUEST);
            $item = shortcode_atts($default, $request);

            $item_valid = $this->form_validate($item);

            if ($item_valid === true) {
                if ($item['site_id'] == 0) {
                    $result = $wpdb->insert($table, $item);
                    $item['site_id'] = $wpdb->insert_id;
                    if ($result !== false) {
                        $message = __("Site enregistré", PL_DOMAIN);
                    } else {
                        $notice = __("Un erreur est apparue lors de la sauvegarde", PL_DOMAIN);
                    }
                } else {
                    $result = $wpdb->update($table, $item, array('site_id' => $item['site_id']));
                    if ($result !== false) {
                        $message = __("Site mis à jour", PL_DOMAIN);
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
                $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE site_id = %d", $_REQUEST['id']), ARRAY_A);
                if (!$item) {
                    $item = $default;
                    $notice = __('Donnée introuvable', PL_DOMAIN);
                }
            }
        }
        add_meta_box('site_form_meta_box', 'Donnée', array($this, 'site_form_meta_box_handler'), 'site', 'normal', 'default'); ?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1><?= _e('Fiche de site', PL_DOMAIN); ?> <a class="add-new-h2" href="<?= get_admin_url(get_current_blog_id(), sprintf('admin.php?page=paralog-sites&paged=%d', $this->get_pagenum())) ?>"><?= _e('retour à la liste', PL_DOMAIN) ?></a></h1>
            <?php if (!empty($notice)): ?>
                <div id="notice" class="error"><p><?= $notice ?></p></div>
            <?php endif; ?>
            <?php if (!empty($message)): ?>
                <div id="message" class="updated"><p><?= $message ?></p></div>
            <?php endif; ?>
            <form id="form" method="post">
                <input type="hidden" name="nonce" value="<?= wp_create_nonce(basename(__FILE__)) ?>"/>
                <input type="hidden" name="site_id" value="<?= esc_attr($item['site_id']) ?>"/>
                <div class="metabox-holder" id="postsite">
                    <div id="post-body">
                        <div id="post-body-content">
                            <?php do_meta_boxes('site', 'normal', $item) ?>
                            <input type="submit" value="<?= _e('Sauver', PL_DOMAIN); ?>" id="submit" class="button-primary" name="submit">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function site_form_meta_box_handler($item)
    {
        ?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="name"><?= _e('Nom du site', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <input id="name" name="name" type="text" style="width: 95%" value="<?= esc_attr($item['name']) ?>" size="50" maxlength="64" class="code" placeholder="<?= _e('ex: annecy', PL_DOMAIN) ?>" required>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private function form_validate($item)
    {
        $messages = array();

        if (empty($item['name'])) {
            $messages[] = __('Le nom du site st obligatoire', PL_DOMAIN);
        }

        if (empty($messages)) {
            return true;
        }

        return implode('<br />', $messages);
    }
}
