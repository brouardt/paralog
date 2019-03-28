<?php
if (!defined('ABSPATH')) {
    die('No direct access allowed');
}

if (!class_exists('Paralog_Table')) {
    require_once plugin_dir_path(__FILE__) . '/paralog_table.php';
}

/**
 * @package Paralog_Activity
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Activity extends Paralog_Table
{
    private $date_format = null;
    
    public function __construct()
    {
        // (_[_en]\(['"][\w\s\d]+['"])(\))
        parent::__construct(array(
            'singular' => __('activité', PL_DOMAIN), //singular name of the listed records
            'plural' => __('activités', PL_DOMAIN), //plural name of the listed records
            'ajax' => false, //does this table support ajax?
        ));

        $this->date_format = get_option('date_format');

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
            'date' => __('Date', PL_DOMAIN),
            'site_name' => __('Nom', PL_DOMAIN),
            'line_name' => __('Treuil', PL_DOMAIN)
        ));

        return $columns;
    }

    public function column_site_name($item)
    {
        $actions = array();
        $user_id = get_current_user_id();
        $primary = $this->getPrimary();

        if (current_user_can('edit_others_posts') || ($item['user_id'] == $user_id)) {
            $actions = array_merge($actions, array(
                'edit' => sprintf('<a href="?page=%s-form&id=%d">%s</a>', $_REQUEST['page'], $item[$primary], __('Modifier', PL_DOMAIN)),
            ));
        }
        if (current_user_can('delete_others_posts') || ($item['user_id'] == $user_id)) {
            $actions = array_merge($actions, array(
                'delete' => sprintf('<a href="?page=%s&action=%s&id=%d">%s</a>', $_REQUEST['page'], 'delete', $item[$primary], __('Supprimer', PL_DOMAIN)),
            ));
        }

        return sprintf('%1$s %2$s', $item['site_name'], $this->row_actions($actions));
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
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'date';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'desc';

        $table = $this->getTable();
        
        $query = $wpdb->prepare(
            "SELECT "
            . "activity_id, "
            . "date, "
            . "site_name, "
            . "line_name, "
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
                return mysql2date($this->date_format, $item[$column_name]);
            case 'site_name':
            case 'line_name':
                return $item[$column_name];
            default:
                return print_r($item, true); // Show the whole array for troubleshooting purposes
        }
    }

    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'date' => array('date', false),
            'site_name' => array('site_name', false),
            'line_name' => array('line_name', false)
        );

        return $sortable_columns;
    }

    public function form_edit()
    {
        global $wpdb;

        $table = $this->getTable();
        $table_ap = Paralog::table_name('activities_persons');
        $table_si = PAralog::table_name('sites');
        $primary = $this->getPrimary();

        $message = '';
        $notice = '';

        $default = array(
            'activity_id' => 0,
            'date' => null,
            'site_name' => null,
            'line_name' => null,
            'start_wind_orientation' => null,
            'end_wind_orientation' => null,
            'start_counter' => '',
            'end_counter' => '',
            'start_time' => '',
            'end_time' => '',
            'start_gazoline' => '',
            'end_gazoline' => '',
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
                $datetime = new DateTime(current_time('mysql'));
                if( empty($item['date'])) {
                    $item['date'] = $datetime->format('Y-m-d');
                }
                if( empty($item['start_time'])) {
                    $item['start_time'] = $datetime->format('H:i');
                }
                if ($item[$primary] == 0) {
                    $result = $wpdb->insert($table, $item);
                    $item[$primary] = $wpdb->insert_id;
                    if ($result !== false) {
                        $message = __("Activité enregistrée", PL_DOMAIN);
                    } else {
                        $notice = __("Un erreur est apparue lors de la sauvegarde", PL_DOMAIN);
                    }
                } else {
                    $result = $wpdb->update($table, $item, array($primary => $item[$primary]));
                    if ($result !== false) {
                        $message = __("Activité mise à jour", PL_DOMAIN);
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
                $item = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table WHERE $primary = %d", 
                        $_REQUEST['id']
                    ), 
                    ARRAY_A
                );
                if (!$item) {
                    $item = $default;
                    $notice = __('Donnée introuvable', PL_DOMAIN);
                }
            }
        }
        // message d'information du site
        if( isset($item[$primary]) && isset($item['site_name'])) {
            $information = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT message FROM $table_si WHERE name = %s AND deleted = 0",
                    $item['site_name']
                )
            );
        }
        // sauvegarde activities_persons
        if( isset($_REQUEST['instructor_action'])) {
            $action_key = explode(':', $_REQUEST['instructor_action']);
            if($action_key[0] === 'add') {
                $item_person = $this->person_activity(
                    $item[$primary], 
                    $_REQUEST['instructor'], 
                    __('moniteur', PL_DOMAIN), 
                    'pilot_type'
                );
                $wpdb->insert($table_ap, $item_person);
            } else {
                $wpdb->delete($table_ap, array('activity_person_id' =>  $action_key[1]));
            }
        } elseif( isset($_REQUEST['plateform_action'])) {
            $action_key = explode(':', $_REQUEST['plateform_action']);
            if($action_key[0] === 'add') {
                $item_person = $this->person_activity(
                    $item[$primary], 
                    $_REQUEST['plateform'], 
                    __('plateforme', PL_DOMAIN), 
                    'pilot_type'
                );
                $wpdb->insert($table_ap, $item_person);
            } else {
                $wpdb->delete($table_ap, array('activity_person_id' =>  $action_key[1]));
            }
        } elseif( isset($_REQUEST['winchman_action'])){
            $action_key = explode(':', $_REQUEST['winchman_action']);
            if($action_key[0] === 'add') {
                $item_person = $this->person_activity(
                    $item[$primary], 
                    $_REQUEST['winchman'], 
                    __('treuilleur', PL_DOMAIN), 
                    'winchman_type'
                );
                $wpdb->insert($table_ap, $item_person);
            } else {
                $wpdb->delete($table_ap, array('activity_person_id' =>  $action_key[1]));
            }
        }
        add_meta_box('activity_form_meta_box', 'Donnée', array($this, 'activity_form_meta_box_handler'), 'activity', 'normal', 'default'); 
        ?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
            <h1><?php _e("Fiche d'activité", PL_DOMAIN)?> <a class="add-new-h2" href="<?= get_admin_url(get_current_blog_id(), sprintf('admin.php?page=paralog-activities&paged=%d', $this->get_pagenum())) ?>"><?php _e('retour à la liste', PL_DOMAIN)?></a></h1>
            <?php if(!empty($information)): ?>
                <div id="information" class="notice notice-info is-dismissible"><p><?= esc_html($information) ?></div>
            <?php endif; ?>
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
                            <input type="submit" value="<?php _e('Sauver', PL_DOMAIN); ?>" id="submit" class="button-primary" name="submit">
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
        
        $orientations = array(
            __('nord', PL_DOMAIN),
            __('nord-est', PL_DOMAIN),
            __('est', PL_DOMAIN),
            __('sud-est', PL_DOMAIN),
            __('sud', PL_DOMAIN),
            __('sud-ouest', PL_DOMAIN),
            __('ouest', PL_DOMAIN),
            __('nord-ouest', PL_DOMAIN)
        );

        $levels = array( 0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100 );

        ?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="date"><?php _e('Date', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <input id="date" name="date" type="date" value="<?=esc_attr($item['date'])?>" class="code" /> 
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="site_name"><?php _e('Nom du site', PL_DOMAIN)?></label>
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
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="line_name"><?php _e('Nom de la ligne', PL_DOMAIN)?></label>
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
                        <label for="start_wind_orientation"><?php _e('Orientation du vent en début de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <select id="start_wind_orientation" name="start_wind_orientation">
                            <option value=""></option>
                            <?php foreach ($orientations as $orientation): ?>
                            <option value="<?=esc_attr($orientation)?>"<?=($orientation == $item['start_wind_orientation'] ? ' selected' : '')?>><?=esc_html($orientation)?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="start_counter"><?php _e('Compteur en debut de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <input type="number" name="start_counter" value="<?= $item['start_counter']?>" />
                    </td>
                </tr>                
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="start_gazoline"><?php _e('Niveau de carburant en debut de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <select id="start_gazoline" name="start_gazoline">
                            <option value=""></option>
                            <?php foreach ($levels as $level): ?>
                            <option value="<?=esc_attr($level)?>"<?=($level == $item['start_gazoline'] ? ' selected' : '')?>><?=esc_html($level)?>%</option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="start_time"><?php _e('Heure du début de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <input type="time" name="start_time" step="60" min="00:00" max="23:59" value="<?= $item['start_time']?>" />
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="end_wind_orientation"><?php _e('Orientation du vent en fin de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <select id="end_wind_orientation" name="end_wind_orientation">
                            <option value=""></option>
                            <?php foreach ($orientations as $orientation): ?>
                            <option value="<?=esc_attr($orientation)?>"<?=($orientation == $item['end_wind_orientation'] ? ' selected' : '')?>><?=esc_html($orientation)?></option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="end_counter"><?php _e('Compteur en fin de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <input type="number" name="end_counter" value="<?= $item['end_counter']?>" />
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="end_gazoline"><?php _e('Niveau de carburant en fin de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <select id="end_gazoline" name="end_gazoline">
                            <option value=""></option>
                            <?php foreach ($levels as $level): ?>
                            <option value="<?=esc_attr($level)?>"<?=($level == $item['end_gazoline'] ? ' selected' : '')?>><?=esc_html($level)?>%</option>
                            <?php endforeach;?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="end_time"><?php _e('Heure de fin de séance', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <input type="time" name="end_time" step="60" min="00:00" max="23:59" value="<?= $item['end_time']?>" />
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="instructor"><?php _e('Moniteur', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <select id="instructor" name="instructor">
                            <option value=""></option>
                            <?php foreach ($pilots as $pilot): ?>
                            <option value="<?=$pilot['person_id']?>"><?=esc_html($pilot['name'])?></option>
                            <?php endforeach;?>
                        </select>
                        <button type="submit" name="instructor_action" value="add:0" class="button button-primary">
                            <span class="fa fa-plus"></span>
                        </button>
                    </td>
                </tr>
                <!-- instructors -->
                <tr>
                    <th>&nbsp;</th>
                    <td>
                    <?php foreach ($instructor_list as $instructor): ?>
                        <div>
                            <button type="submit" name="instructor_action" value="remove:<?= $instructor['activity_person_id'] ?>" class="button">
                                <span class="fa fa-trash"></span>
                            </button>
                            <span><?=esc_html($instructor['person_name'])?></span>
                        </div>
                    <?php endforeach;?>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="plateform"><?php _e('Plateforme', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <select id="plateform" name="plateform">
                            <option value=""></option>
                            <?php foreach ($pilots as $pilot): ?>
                            <option value="<?=$pilot['person_id']?>"><?=esc_html($pilot['name'])?></option>
                            <?php endforeach;?>
                        </select>
                        <button type="submit" name="plateform_action" value="add:0" class="button button-primary">
                            <span class="fa fa-plus"></span>
                        </button>
                    </td>
                </tr>
                <!-- plateform manager -->
                <tr>
                    <th>&nbsp;</th>
                    <td>
                    <?php foreach($plateform_list as $plateform): ?>
                        <div>
                            <button type="submit" name="plateform_action" value="remove:<?= $plateform['activity_person_id'] ?>" class="button">
                                <span class="fa fa-trash"></span>
                            </button>
                            <span><?=esc_html($plateform['person_name'])?></span>
                        </div>
                    <?php endforeach; ?>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winchman"><?php _e('Treuilleur', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <select id="winchman" name="winchman">
                            <option value=""></option>
                            <?php foreach ($winchmen as $winchman): ?>
                            <option value="<?=$winchman['person_id']?>"><?=esc_html($winchman['name'])?></option>
                            <?php endforeach;?>
                        </select>
                        <button type="submit" name="winchman_action" value="add:0" class="button button-primary">
                            <span class="fa fa-plus"></span>
                        </button>
                    </td>
                </tr>
                <!-- winchmen -->
                <tr>
                    <th>&nbsp;</th>
                    <td>
                        <?php foreach($winchman_list as $winchman): ?>
                            <div>
                                <button type="submit" name="winchman_action" value="remove:<?= $winchman['activity_person_id'] ?>" class="button">
                                    <span class="fa fa-trash"></span>
                                </button>
                                <span><?=esc_html($winchman['person_name'])?></span>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="winch_incident"><?php _e('Incident de treuil', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <textarea name="winch_incident" class="code"><?=esc_html($item['winch_incident'])?></textarea>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="fly_incident"><?php _e('Incident de vol', PL_DOMAIN)?></label>
                    </th>
                    <td>
                        <textarea name="fly_incident" class="code"><?=esc_html($item['fly_incident'])?></textarea>
                    </td>
                </tr>
                <tr class="form-field">
                    <th valign="top" scope="row">
                        <label for="comment"><?php _e('Commentaire', PL_DOMAIN)?></label>
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

        if (empty($item['site_name'])) {
            $messages[] = __('Le nom du site est obligatoire', PL_DOMAIN);
        }

        if (empty($item['line_name'])) {
            $messages[] = __('Le nom de la ligne est obligatoire', PL_DOMAIN);
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
              "SELECT activity_person_id, person_name "
            . "FROM $table "
            . "WHERE activity_id = %d "
            . "AND person_type = %s "
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
        return $this->person_list_by_activity_and_type($id, __('moniteur', PL_DOMAIN));
    }

    /**
     * @name plateform_list
     * @param Integer id
     * @return Array
     */
    private function plateform_list($id)
    {
        return $this->person_list_by_activity_and_type($id, __('plateforme', PL_DOMAIN));
    }

    /**
     * @name winchman_list
     * @param Integer id
     * @return Array
     */
    private function winchman_list($id)
    {
        return $this->person_list_by_activity_and_type($id, __('treuilleur', PL_DOMAIN));
    }

    /**
     * @name person_activity
     * @param Integer id_activity
     * @param Integer id_person
     * @param String type
     * @param String name_type
     * @return Array
     */
    private function person_activity($id_activity, $id_person, $type, $name_type)
    {
        $default = array(
            'activity_id' => $id_activity,
            'person_type' => $type,
            'person_name' => null,
            'user_id' => get_current_user_id(),
            'deleted' => 0
        );

        $pilot = $this->person_name_type($id_person, $name_type);
        
        $data['person_name'] = $pilot->name;

        return shortcode_atts($default, $data);
    }
}
