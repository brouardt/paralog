<?php

if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

if (!class_exists('Paralog_Table')) {
    require_once plugin_dir_path(__FILE__) . '/paralog_table.php';
}

/**
 * Description of paralog_person
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Person extends Paralog_Table
{

    public function __construct()
    {
        parent::__construct(array(
            'singular' => __('personne'), //singular name of the listed records
            'plural' => __('personnes'), //plural name of the listed records
            'ajax' => false, //does this table support ajax?
        ));

        $this->setTable('persons');
        $this->setPrimary('person_id');
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
            'name' => __("Prénom + Nom"),
            'pilot_type' => __("Statut pilote"),
            'licence' => __("Licence"),
            'winchman' => __("Treuilleur"),
            'winchman_type' => __("Statut treuilleur"),
        ));

        return $columns;
    }

    public function column_name($item)
    {
        $actions = array();
        $user_id = get_current_user_id();
        $primary = $this->getPrimary();

        if (current_user_can('edit_others_posts') || ($item['user_id'] == $user_id)) {
            $actions = array_merge($actions, array(
                'edit' => sprintf('<a href="?page=%s-form&id=%d&paged=%d">%s</a>', $_REQUEST['page'], $item[$primary], $this->get_pagenum(), __('Modifier')),
            ));
        }
        if (current_user_can('delete_others_posts') || ($item['user_id'] == $user_id)) {
            $actions = array_merge($actions, array(
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d">%s</a>', $_REQUEST['page'], 'delete', $item[$primary], __('Supprimer')),
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

        $table = $this->getTable();

        $query = $wpdb->prepare(
            "SELECT "
            . "person_id, "
            . "firstname, "
            . "lastname, "
            . "pilot_type, "
            . "licence, "
            . "winchman, "
            . "winchman_type, "
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
            'winchman_type' => array('winchman_type', false),
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

        $default = array(
            'person_id' => 0,
            'firstname' => '',
            'lastname' => '',
            'pilot_type' => __('pilote'),
            'licence' => '',
            'winchman' => __('non'),
            'winchman_type' => null,
            'user_id' => get_current_user_id(),
        );

        if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
            // combine our default item with request params
            $request = stripslashes_deep($_REQUEST);
            $item = shortcode_atts($default, $request);

            $item_valid = $this->form_validate($item);

            if ($item_valid === true) {
                if ($item[$primary] == 0) {
                    $result = $wpdb->insert($table, $item);
                    $item[$primary] = $wpdb->insert_id;
                    if ($result !== false) {
                        $message = __("Personne enregistrée");
                    } else {
                        $notice = __("Un erreur est apparue lors de la sauvegarde");
                    }
                } else {
                    $result = $wpdb->update($table, $item, array($primary => $item[$primary]));
                    if ($result !== false) {
                        $message = __("Personne mise à jour");
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
            $item = $default;
            if (isset($_REQUEST['id'])) {
                $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE $primary = %d", $_REQUEST['id']), ARRAY_A);
                if (!$item) {
                    $item = $default;
                    $notice = __('Donnée introuvable');
                }
            }
        }
        add_meta_box('person_form_meta_box', 'Donnée', array($this, 'person_form_meta_box_handler'), 'person', 'normal', 'default');
        ?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1><?php _e('Fiche de personnel');?> <a class="add-new-h2" href="<?=get_admin_url(get_current_blog_id(), sprintf('admin.php?page=paralog-persons&paged=%d', $this->get_pagenum()))?>"><?php _e('retour à la liste')?></a></h1>
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
                            <?php do_meta_boxes('person', 'normal', $item)?>
                            <input type="submit" value="<?php _e('Sauver');?>" id="submit" class="button-primary" name="submit">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function person_form_meta_box_handler($item)
    {
        $treuilleur = __('treuilleur');
        $pilote = __('pilote');
        $eleve = __('élève');
        $oui = __('oui');
        $non = __('non');
        ?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="firstname"><?php _e('Prénom')?></label>
                    </th>
                    <td>
                        <input id="firstname" name="firstname" type="text" style="width: 95%" value="<?=esc_attr($item['firstname'])?>" size="50" maxlength="64" class="code" placeholder="<?php _e('ex: Thierry')?>" required>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="lastname"><?php _e('Nom')?></label>
                    </th>
                    <td>
                        <input id="lastname" name="lastname" type="text" style="width: 95%" value="<?=esc_attr($item['lastname'])?>" size="50" maxlength="64" class="code" placeholder="<?php _e('ex: Brouard')?>" required>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label><?php _e('Type de pilote')?></label>
                    </th>
                    <td>
                        <label><input name="pilot_type" type="radio" value="<?=$pilote?>"<?=($item['pilot_type'] == $pilote ? ' checked' : '')?>/> <?=$pilote?></label>
                        <label><input name="pilot_type" type="radio" value="<?=$eleve?>"<?=($item['pilot_type'] == $eleve ? ' checked' : '')?>/> <?=$eleve?></label>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="licence"><?php _e('Licence')?></label>
                    </th>
                    <td>
                        <input id="licence" name="licence" type="text" style="width: 95%" value="<?=esc_attr($item['licence'])?>" size="50" maxlength="10" class="code" placeholder="<?php _e('ex: 0000000X')?>">
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman"><?php _e('Treuilleur')?></label>
                    </th>
                    <td>
                        <label><input name="winchman" type="radio" value="<?=$oui?>"<?=($item['winchman'] == $oui ? ' checked' : '')?>/> <?=$oui?></label>
                        <label><input name="winchman" type="radio" value="<?=$non?>"<?=($item['winchman'] == $non ? ' checked' : '')?>/> <?=$non?></label>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman_type"><?php _e('Type de treuilleur')?></label>
                    </th>
                    <td>
                        <label><input name="winchman_type" type="radio" value="<?=$treuilleur?>"<?=($item['winchman_type'] == $treuilleur ? ' checked' : '')?>/> <?=$treuilleur?></label>
                        <label><input name="winchman_type" type="radio" value="<?=$eleve?>"<?=($item['winchman_type'] == $eleve ? ' checked' : '')?>/> <?=$eleve?></label>
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
            $messages[] = __('Le prénom de la personne est obligatoire');
        }
        if (empty($item['lastname'])) {
            $messages[] = __('Le nom de la personne est obligatoire');
        }
        if (empty($item['pilot_type'])) {
            $messages[] = __('Le type de pilote est obligatoire');
        }
        if (empty($item['winchman'])) {
            $messages[] = __('Le champ treuilleur est obligatoire');
        } elseif ($item['winchman'] == __('oui')) {
            if (empty($item['winchman_type'])) {
                $messages[] = __('Le type de treuilleur est obligatoire');
            }
        }

        if (empty($messages)) {
            return true;
        }

        return implode('<br />', $messages);
    }
}
