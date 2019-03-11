<?php

if (!defined('ABSPATH')) {
    die('No direct access allowed');
}

if (!class_exists('Paralog_Table')) {
    require_once plugin_dir_path(__FILE__) . '/paralog_table.php';
}

/**
 * Description of paralog_site
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Activity extends Paralog_Table
{

    public function __construct()
    {
        parent::__construct(array(
            'singular' => __('activité'), //singular name of the listed records
            'plural' => __('activités'), //plural name of the listed records
            'ajax' => false, //does this table support ajax?
        ));

        $this->setTable('activities');
        $this->setPrimary('activity_id');
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
            'date' => __('Date'),
            'site_name' => __('Nom')
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
                'edit' => sprintf('<a href="?page=%s-form&id=%d">%s</a>', $_REQUEST['page'], $item[$primary], __('Modifier')),
            ));
        }
        if (current_user_can('delete_others_posts') || ($item['user_id'] == $user_id)) {
            $actions = array_merge($actions, array(
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d">%s</a>', $_REQUEST['page'], 'delete', $item[$primary], __('Supprimer')),
            ));
        }

        return sprintf('%1$s %2$s', $item['name'], $this->row_actions($actions));
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

        $table = $this->getTable();
        
        $query = $wpdb->prepare(
            "SELECT "
            . "activity_id, "
            . "date, "
            . "site_name, "
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
            case 'date':
            case 'site_name':
                return $item[$column_name];
            default:
                return print_r($item, true); // Show the whole array for troubleshooting purposes
        }
    }

    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'date' => array('date', false),
            'site_name' => array('site_name', false)
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
            'activity_id' => 0,
            'date' => null,
            'site_name' => '',
            'comment' => '',
            'winch_incident' => '',
            'fly_incident' => '',
            'user_id' => get_current_user_id()
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
                        $message = __("Activité enregistrée");
                    } else {
                        $notice = __("Un erreur est apparue lors de la sauvegarde");
                    }
                } else {
                    $result = $wpdb->update($table, $item, array($primary => $item[$primary]));
                    if ($result !== false) {
                        $message = __("Activité mise à jour");
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
                $item = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table WHERE $primary = %d", 
                        $_REQUEST['id']
                    ), 
                    ARRAY_A
                );
                if (!$item) {
                    $item = $default;
                    $notice = __('Donnée introuvable');
                }
            }
        }
        add_meta_box('activity_form_meta_box', 'Donnée', array($this, 'activity_form_meta_box_handler'), 'activity', 'normal', 'default'); 
        ?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1><?php _e("Fiche d'activité"); ?> <a class="add-new-h2" href="<?= get_admin_url(get_current_blog_id(), sprintf('admin.php?page=paralog-activities&paged=%d', $this->get_pagenum())) ?>"><?php _e('retour à la liste') ?></a></h1>
            <?php if (!empty($notice)): ?>
                <div id="notice" class="error"><p><?= $notice ?></p></div>
            <?php endif; ?>
            <?php if (!empty($message)): ?>
                <div id="message" class="updated"><p><?= $message ?></p></div>
            <?php endif; ?>
            <form id="form" method="post">
                <input type="hidden" name="nonce" value="<?= wp_create_nonce(basename(__FILE__)) ?>"/>
                <input type="hidden" name="<?= $primary ?>" value="<?= esc_attr($item[$primary]) ?>"/>
                <div class="metabox-holder" id="postactivity">
                    <div id="post-body">
                        <div id="post-body-content">
                            <?php do_meta_boxes('activity', 'normal', $item) ?>
                            <input type="submit" value="<?php _e('Sauver'); ?>" id="submit" class="button-primary" name="submit">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    public function activity_form_meta_box_handler($item) 
    {
        $sites = $this->site_name_items();
        $lines = $this->line_name_items();
        $pilots = $this->pilot_name_items();
        $winchmen = $this->winchman_name_items();

        $instructor_list = $this->instructor_list($item['activity_id']);
        $plateform_list = $this->plateform_list($item['activity_id']);
        $winchman_list = $this->winchman_list($item['activity_id']);

        ?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="date"><?php _e('Date')?></label>
                    </th>
                    <td>
                        <input id="date" name="date" type="date" value="<?=esc_attr($item['date'])?>" class="code" /> 
                    </td>
                </tr>
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
                        <label for="instructor"><?php _e('Moniteur')?></label>
                    </th>
                    <td>
                        <select id="instructor" name="instructor">
                            <option value=""></option>
                            <?php foreach ($pilots as $pilot): ?>
                            <option value="<?=esc_attr($pilot['name'])?>"><?=esc_html($pilot['name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <!-- instructors -->
                <tr>
                    <th>&nbsp;</tr>
                    <td>
                    <?php foreach ($instructor_list as $instructor): ?>
                        <span><?=esc_html($instructor['person_name'])?></span>
                        <?php if( $instructor['person_type'] == __('élève') ):?>
                            (<span style="color:<?php $this->color_person($item['person_type'])?>"><?php _e('élève')?></span>)
                        <?php endif;?>
                        <br />
                    <?php endforeach;?>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="plateform"><?php _e('Plateforme')?></label>
                    </th>
                    <td>
                        <select id="plateform" name="plateform">
                            <option value=""></option>
                            <?php foreach ($pilots as $pilot): ?>
                            <option value="<?=esc_attr($pilot['name'])?>"><?=esc_html($pilot['name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <!-- plateform manager -->
                <tr>
                    <th>&nbsp;</tr>
                    <td>
                    <?php foreach($plateform_list as $plateform): ?>
                        <span><?=esc_html($plateform['person_name'])?></span>
                        <?php if( $plateform['person_type'] == __('élève') ): ?>
                            (<span style="color:<?php $this->color_person($item['person_type'])?>"><?php _e('élève')?></span>)
                        <?php endif;?>
                        <br />
                    <?php endforeach; ?>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman"><?php _e('Treuilleur')?></label>
                    </th>
                    <td>
                        <select id="winchman" name="winchman">
                            <option value=""></option>
                            <?php foreach ($winchmen as $winchman): ?>
                            <option value="<?=esc_attr($winchman['name'])?>"><?=esc_html($winchman['name'])?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <!-- winchmen -->
                <tr>
                    <th>&nbsp;</tr>
                    <td>
                        <?php foreach($winchman_list as $winchman): ?>
                            <span><?=esc_html($winchman['person_name'])?></span>
                            <?php if( $winchman['person_type'] == __('élève') ): ?>
                                (<span style="color:<?php $this->color_person($item['person_type'])?>"><?php _e('élève')?></span>)
                            <?php endif;?>
                            <br />
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winch_incident"><?php _e('Incident de treuil')?></label>
                    </th>
                    <td>
                        <textarea name="winch_incident" class="code"><?=esc_html($item['winch_incident'])?></textarea>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="fly_incident"><?php _e('Incident de vol')?></label>
                    </th>
                    <td>
                        <textarea name="fly_incident" class="code"><?=esc_html($item['fly_incident'])?></textarea>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="comment"><?php _e('Commentaire')?></label>
                    </th>
                    <td>
                        <textarea name="comment" class="code"><?=esc_html($item['comment'])?></textarea>
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
            $messages[] = __('Le nom du site est obligatoire');
        }

        if (empty($messages)) {
            return true;
        }

        return implode('<br />', $messages);
    }

    /**
     * @param Integer
     * @param String
     * @return String $query
     */
    private function person_list_by_activity_and_type($id, $type) 
    {
        global $wpdb;

        $table = Paralog::table_name('activities_persons');

        $query = $wpdb->prepare(
              "SELECT activity_person_id, person_name, person_type "
            . "FROM $table "
            . "WHERE activity_id = %d "
            . "AND type = %s "
            . "AND deleted = 0",
            $id, 
            $type
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @name instructor_list
     * @param Integer id
     * @return Array
     */
    private function instructor_list($id) 
    {
        return $this->person_list_by_activity_and_type($id, __('moniteur'));
    }

    /**
     * @name plateform_list
     * @param Integer id
     * @return Array
     */
    private function plateform_list($id)
    {
        return $this->person_list_by_activity_and_type($id, __('plateforme'));
    }

    /**
     * @name winchman_list
     * @param Integer id
     * @return Array
     */
    private function winchman_list($id)
    {
        return $this->person_list_by_activity_and_type($id, __('treuilleur'));
    }
}
