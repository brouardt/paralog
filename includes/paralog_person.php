<?php

if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

/**
 * Description of paralog_person
 *
 * @author thier
 */
class Paralog_Person extends WP_List_Table
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
        if (current_user_can('delete_others_posts')) {
            $columns = array(
                'cb' => '<input type="checkbox" />'
            );
        } else {
            $columns = array();
        }

        $columns = array_merge($columns, array(
            'name' => __("Prénom + Nom", PL_DOMAIN),
            /*'firstname' => __("Prénom", PL_DOMAIN),
            'lastname' => __("Nom", PL_DOMAIN),*/
            'pilot_type' => __("Statut pilote", PL_DOMAIN),
            'licence' => __("Licence", PL_DOMAIN),
            'winchman' => __("Treuilleur", PL_DOMAIN),
            'winchman_type' => __("Statut treuilleur", PL_DOMAIN)
        ));

        return $columns;
    }

    public function column_name($item)
    {
        $actions = array();

        if (current_user_can('edit_others_posts')) {
            $actions = array_merge($actions, array(
                'edit' => sprintf('<a href="?page=%s-form&id=%d&paged=%d">%s</a>', $_REQUEST['page'], $item['person_id'], $this->get_pagenum(), __('Modifier', PL_DOMAIN))
            ));
        }
        if (current_user_can('delete_others_posts')) {
            $actions = array_merge($actions, array(
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d">%s</a>', $_REQUEST['page'], 'delete', $item['person_id'], __('Supprimer', PL_DOMAIN))
            ));
        }

        return sprintf('%1$s %2$s', trim($item['firstname'] . ' ' . $item['lastname']), $this->row_actions($actions));
    }

    public function column_licence($item)
    {
        if (!empty($item['licence'])) {
            $column = sprintf('<a href="https://intranet.ffvl.fr/licences/%1$s" target="_blank">%1$s</a>', $item['licence']);
        } else {
            $column = '';
        }
        
        return $column;
    }

    protected function get_bulk_actions()
    {
        if (current_user_can('delete_others_posts')) {
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
        return sprintf('<input type="checkbox" name="id[]" value="%s" />', $item['person_id']);
    }

    public function process_bulk_action()
    {
        global $wpdb;

        if ('delete' === $this->current_action() && current_user_can('delete_others_posts')) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) {
                $ids = implode(',', $ids);
            }

            if (!empty($ids)) {
                $query = "DELETE FROM " . Paralog::table_name('persons') . " WHERE person_id IN($ids)";
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
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'lastname';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

        $table = Paralog::table_name('persons');

        $query = $wpdb->prepare(
            "SELECT person_id, firstname, lastname, pilot_type, licence, winchman, winchman_type "
            . "FROM $table "
            . "ORDER BY $orderby $order "
            . "LIMIT %d OFFSET %d",
            $per_page,
            $paged
        );
        $this->items = $wpdb->get_results($query, ARRAY_A);

        $total_items = $wpdb->get_var("SELECT COUNT(person_id) FROM $table");

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page),
            'per_page' => $per_page
        ));
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'pilot_type':
            case 'winchman':
            case 'winchman_type':
                return $item[$column_name];
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'name' => array('lastname', true),
            'pilot_type' => array('pilot_type', false),
            'licence' => array('licence', false),
            'winchman' => array('winchman', false),
            'winchman_type' => array('winchman_type', false)
        );

        return $sortable_columns;
    }

    public function form_edit()
    {
        global $wpdb;

        $table = Paralog::table_name('persons');

        $message = '';
        $notice = '';

        $default = array(
            'person_id' => 0,
            'firstname' => '',
            'lastname' => '',
            'pilot_type' => __('pilote', PL_DOMAIN),
            'licence' => '',
            'winchman' => __('non', PL_DOMAIN),
            'winchman_type' => null,
            'user_id' => get_current_user_id()
        );

        if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
            // combine our default item with request params
            $request = stripslashes_deep($_REQUEST);
            $item = shortcode_atts($default, $request);

            $item_valid = $this->form_validate($item);

            if ($item_valid === true) {
                if ($item['person_id'] == 0) {
                    $result = $wpdb->insert($table, $item);
                    $item['person_id'] = $wpdb->insert_id;
                    if ($result !== false) {
                        $message = __("Personne enregistrée", PL_DOMAIN);
                    } else {
                        $notice = __("Un erreur est apparue lors de la sauvegarde", PL_DOMAIN);
                    }
                } else {
                    $result = $wpdb->update($table, $item, array('person_id' => $item['person_id']));
                    if ($result !== false) {
                        $message = __("Personne mise à jour", PL_DOMAIN);
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
                $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE person_id = %d", $_REQUEST['id']), ARRAY_A);
                if (!$item) {
                    $item = $default;
                    $notice = __('Donnée introuvable', PL_DOMAIN);
                }
            }
        }
        add_meta_box('person_form_meta_box', 'Donnée', array($this, 'person_form_meta_box_handler'), 'person', 'normal', 'default'); ?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1><?php _e('Fiche de personnel', PL_DOMAIN); ?> <a class="add-new-h2" href="<?= get_admin_url(get_current_blog_id(), sprintf('admin.php?page=paralog-persons&paged=%d', $this->get_pagenum())) ?>"><?php _e('retour à la liste', PL_DOMAIN) ?></a></h1>
            <?php if (!empty($notice)): ?>
                <div id="notice" class="error"><p><?= $notice ?></p></div>
            <?php endif; ?>
            <?php if (!empty($message)): ?>
                <div id="message" class="updated"><p><?= $message ?></p></div>
            <?php endif; ?>
            <form id="form" method="post">
                <input type="hidden" name="nonce" value="<?= wp_create_nonce(basename(__FILE__)) ?>"/>
                <input type="hidden" name="person_id" value="<?= esc_attr($item['person_id']) ?>"/>
                <div class="metabox-holder" id="postsite">
                    <div id="post-body">
                        <div id="post-body-content">
                            <?php do_meta_boxes('person', 'normal', $item) ?>
                            <input type="submit" value="<?php _e('Sauver', PL_DOMAIN); ?>" id="submit" class="button-primary" name="submit">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    public function person_form_meta_box_handler($item)
    {
        $treuilleur = __('treuilleur', PL_DOMAIN);
        $pilote = __('pilote', PL_DOMAIN);
        $eleve = __('élève', PL_DOMAIN);
        $oui = __('oui', PL_DOMAIN);
        $non = __('non', PL_DOMAIN); ?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="firstname"><?php _e('Prénom', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <input id="firstname" name="firstname" type="text" style="width: 95%" value="<?= esc_attr($item['firstname']) ?>" size="50" maxlength="64" class="code" placeholder="<?php _e('ex: Thierry', PL_DOMAIN) ?>" required>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="lastname"><?php _e('Nom', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <input id="lastname" name="lastname" type="text" style="width: 95%" value="<?= esc_attr($item['lastname']) ?>" size="50" maxlength="64" class="code" placeholder="<?php _e('ex: Brouard', PL_DOMAIN) ?>" required>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label><?php _e('Type de pilote', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <label><input name="pilot_type" type="radio" value="<?= $pilote ?>"<?= ($item['pilot_type'] == $pilote ? ' checked':'') ?>/> <?= $pilote ?></label>
                        <label><input name="pilot_type" type="radio" value="<?= $eleve ?>"<?= ($item['pilot_type'] == $eleve ? ' checked':'') ?>/> <?= $eleve ?></label>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="licence"><?php _e('Licence', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <input id="licence" name="licence" type="text" style="width: 95%" value="<?= esc_attr($item['licence']) ?>" size="50" maxlength="10" class="code" placeholder="<?php _e('ex: 0000000X', PL_DOMAIN) ?>">
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman"><?php _e('Treuilleur', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <label><input name="winchman" type="radio" value="<?= $oui ?>"<?= ($item['winchman'] == $oui ? ' checked':'') ?>/> <?= $oui ?></label>
                        <label><input name="winchman" type="radio" value="<?= $non ?>"<?= ($item['winchman'] == $non ? ' checked':'') ?>/> <?= $non ?></label>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman_type"><?php _e('Type de treuilleur', PL_DOMAIN) ?></label>
                    </th>
                    <td>
                        <label><input name="winchman_type" type="radio" value="<?= $treuilleur ?>"<?= ($item['winchman_type'] == $treuilleur ? ' checked':'') ?>/> <?= $treuilleur ?></label>
                        <label><input name="winchman_type" type="radio" value="<?= $eleve ?>"<?= ($item['winchman_type'] == $eleve ? ' checked':'') ?>/> <?= $eleve ?></label>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private function form_validate($item)
    {
        $messages = array();

        if (empty($item['firstname'])) {
            $messages[] = __('Le prénom de la personne est obligatoire', PL_DOMAIN);
        }
        if (empty($item['lastname'])) {
            $messages[] = __('Le nom de la personne est obligatoire', PL_DOMAIN);
        }
        if (empty($item['pilot_type'])) {
            $messages[] = __('Le type de pilote est obligatoire', PL_DOMAIN);
        }
        if (empty($item['winchman'])) {
            $messages[] = __('Le champ treuilleur est obligatoire', PL_DOMAIN);
        } elseif ($item['winchman'] == __('oui', PL_DOMAIN)) {
            if (empty($item['winchman_type'])) {
                $messages[] = __('Le type de treuilleur est obligatoire', PL_DOMAIN);
            }
        }

        if (empty($messages)) {
            return true;
        }

        return implode('<br />', $messages);
    }
}
