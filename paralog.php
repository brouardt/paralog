<?php
/**
 * @link              https://thierry.brouard.pro/
 * @since             1.0.0
 * @package           Paralog
 *
 * Plugin Name:       Paralog
 * Plugin URI:        https://thierry.brouard.pro/2018/01/paralog/
 * Description:       Gestion des journaux de décollages / treuillés avec les sites, les lignes, les pilotes, les élèves et les treuilleurs
 * Version:           1.2.1
 * Author:            Thierry Brouard <thierry@brouard.pro>
 * Author URI:        https://thierry.brouard.pro/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       paralog
 * Domain Path:       /languages
 * Requires at least: 4.4
 * Stable tag:        4.9.4
 * Tested up to:      4.9.4
 * Requires PHP:      5.6
 * */
if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (!class_exists('Paralog')) {
    define('PL_VERSION', '1.2.1');
    define('PL_DB_VERSION', '1.7');
    define('PL_DOMAIN', 'paralog');
    define('PL_DATE_FORMAT', 'Y-m-d H:i:s');

    /**
     * @name Paralog
     */
    class Paralog
    {
        const admin_slug = 'paralog-admin';

        public $plugin_dir;
        public $plugin_url;
        public $plugin_name;
        private static $tables = array('sites', 'lines', 'persons', 'logs');

        /**
         * @name __construct
         */
        public function __construct()
        {
            $this->plugin_dir = untrailingslashit(plugin_dir_path(__FILE__));
            $this->plugin_url = untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)));
            $this->plugin_name = plugin_basename(__FILE__);

            add_action('init', array($this, 'load_language'));

            add_action('admin_menu', array($this, 'paralog_menu'));
            add_action('admin_bar_menu', array($this, 'admin_paralog_bar_menu'));

            add_action('admin_enqueue_scripts', array($this, 'register_styles'));

            register_activation_hook(__FILE__, array(__CLASS__, 'on_activation'));
            register_deactivation_hook(__FILE__, array(__CLASS__, 'on_deactivation'));
            register_uninstall_hook(__FILE__, array(__CLASS__, 'on_uninstall'));
        }

        /**
         * @name lang
         */
        public function load_language()
        {
            load_plugin_textdomain(PL_DOMAIN, false, $this->plugin_dir . '/languages');
        }
                

        /**
         * @name register_styles
         */
        public function register_styles()
        {
            wp_register_style(PL_DOMAIN, plugins_url('paralog/css/style.css'));
            wp_enqueue_style(PL_DOMAIN);
        }

        /**
         *
         * @global object $wpdb
         * @param string $name
         * @return string
         */
        public static function table_name($name)
        {
            global $wpdb;
            
            return $wpdb->prefix . 'pl_' . $name;
        }
        
        /**
         * @name on_activation
         * @global object $wpdb
         * @global string $charset_collate
         */
        public static function on_activation()
        {
            global $wpdb, $charset_collate;
            
            $options = get_option(PL_DOMAIN);
            
            if ($options['db_version'] != PL_DB_VERSION) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

                $p = __('pilote', PL_DOMAIN);
                $t = __('treuilleur', PL_DOMAIN);
                $e = __('élève', PL_DOMAIN);
                $o = __('oui', PL_DOMAIN);
                $n = __('non', PL_DOMAIN);
                
                /*
                * area
                */
                $table = self::table_name('sites');
                $query = "CREATE TABLE IF NOT EXISTS $table ( "
                        . "site_id TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT, "
                        . "name VARCHAR(64) DEFAULT NULL, "
                        . "PRIMARY KEY (site_id) "
                        . ") $charset_collate";
                dbDelta($query);
                /*
                * line equipment
                */
                $table = self::table_name('lines');
                $query = "CREATE TABLE IF NOT EXISTS $table ( "
                        . "line_id TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT, "
                        . "name VARCHAR(32) DEFAULT NULL, "
                        . "PRIMARY KEY (line_id) "
                        . ") $charset_collate";
                dbDelta($query);
                /*
                * pilot, student and winchman
                */
                $table = self::table_name('persons');
                $query = $wpdb->prepare(
                        "CREATE TABLE IF NOT EXISTS $table ( "
                        . "person_id smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, "
                        . "firstname varchar(64) DEFAULT NULL, "
                        . "lastname varchar(64) DEFAULT NULL, "
                        . "pilot_type enum(%s,%s) NOT NULL DEFAULT %s, "
                        . "licence varchar(10) DEFAULT NULL, "
                        . "winchman enum(%s,%s) NOT NULL DEFAULT %s, "
                        . "winchman_type enum(%s,%s) DEFAULT NULL, "
                        . "PRIMARY KEY (person_id) "
                        . ") $charset_collate",
                        array(
                            $p, $e, $p,
                            $o, $n, $n,
                            $t, $e
                            )
                        );
                dbDelta($query);
                /*
                * log book
                */
                $table = self::table_name('logs');
                $query = $wpdb->prepare(
                        "CREATE TABLE IF NOT EXISTS $table ( "
                        . "log_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, "
                        . "site_name VARCHAR(64) DEFAULT NULL, "
                        . "line_name VARCHAR(32) DEFAULT NULL, "
                        . "winchman_name VARCHAR(129) DEFAULT NULL, "
                        . "winchman_type ENUM(%s,%s) DEFAULT NULL, "
                        . "pilot_name VARCHAR(129) DEFAULT NULL, "
                        . "pilot_type ENUM(%s,%s) NOT NULL DEFAULT %s, "
                        . "passenger_name VARCHAR(129) DEFAULT NULL, "
                        . "total_flying_weight SMALLINT(5) UNSIGNED DEFAULT NULL, "
                        . "takeoff DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', "
                        . "PRIMARY KEY (log_id) "
                        . ") $charset_collate",
                        array(
                            $t, $e,
                            $p, $e, $p
                            )
                        );
                dbDelta($query);
                    
                // options
                $options = array(
                    'db_version' => PL_DB_VERSION,
                    'active'     => 'on',
                    'datetime'   => current_time('mysql')
                );
                update_option(PL_DOMAIN, $options, 'no');
            }
        }

        public static function insert_demo_datas()
        {
            global $wpdb;

            $p = __('pilote', PL_DOMAIN);
            $t = __('treuilleur', PL_DOMAIN);
            $e = __('élève', PL_DOMAIN);
            $o = __('oui', PL_DOMAIN);
            $n = __('non', PL_DOMAIN);

            $table = self::table_name('sites');
            $query = "INSERT INTO $table (site_id, name) VALUES "
                    . "(1, 'Mont Bouquet'), "
                    . "(2, 'Aslonnes \"Le Fort\"'), "
                    . "(3, 'Annecy'), "
                    . "(4, 'Massognes / Jarzay');";
            $wpdb->query($query);

            $table = self::table_name('lines');
            $query = "INSERT INTO $table (line_id, name) VALUES "
                    . "(1, 'Déco EST'), "
                    . "(2, 'Déco SUD'), "
                    . "(3, 'Planfait'), "
                    . "(4, 'Montmin'), "
                    . "(5, 'Coche Cabane'), "
                    . "(6, 'Treuil 1B'), "
                    . "(7, 'Treuil 2B - ligne rouge'), "
                    . "(8, 'Treuil 2B - ligne verte'), "
                    . "(9, 'Dévidoir 3B');";
            $wpdb->query($query);
            
            $table = self::table_name('persons');
            $query = $wpdb->prepare(
                    "INSERT INTO $table (person_id, firstname, lastname, pilot_type, licence, winchman, winchman_type) VALUES "
                    . "(1, 'Thierry', 'BROUARD', %s, '1309710X', %s, %s), "
                    . "(2, 'Jean-Yves', 'COLLIN', %s, '0700484V', %s, %s), "
                    . "(3, 'Quentin', 'COURTOIS', %s, '1604781B', %s, NULL), "
                    . "(4, 'Bernard', 'MAUDET', %s, '0062282X', %s, %s), "
                    . "(5, 'Carlos', 'MESQUITA', %s, '1302566G', %s, NULL);",
                    array(
                        $p, $o, $e,
                        $p, $o, $t,
                        $p, $n,
                        $p, $o, $t,
                        $p, $n,
                    )
            );
            $wpdb->query($query);
            
            $table = self::table_name('logs');
            $query = $wpdb->prepare(
                    "INSERT INTO $table (log_id, site_name, line_name, winchman_name, winchman_type, pilot_name, pilot_type, passenger_name, total_flying_weight, takeoff) VALUES "
                    . "(1, 'Annecy', 'Planfait', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2017-10-10 15:06:07'), "
                    . "(2, 'Annecy', 'Montmin', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2017-10-10 15:14:45'), "
                    . "(3, 'Mont Bouquet', 'Déco SUD', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2018-01-10 15:50:21'), "
                    . "(4, 'Mont Bouquet', 'Déco EST', NULL, NULL, 'Bernard MAUDET', %s, NULL, 90, '2017-10-10 15:18:47'), "
                    . "(5, 'Massognes / Jarzay', 'Dévidoir 3B', 'Thierry BROUARD', %s, 'Quentin COURTOIS', %s, NULL, 105, '2018-01-10 15:36:55'), "
                    . "(6, 'Aslonnes \"Le Fort\"', 'Treuil 1B', 'Jean-Yves COLLIN', %s, 'Carlos MESQUITA', %s, NULL, 90, '2018-01-10 15:38:48'), "
                    . "(7, 'Aslonnes \"Le Fort\"', 'Treuil 2B - ligne rouge', 'Bernard MAUDET', %s, 'Thierry BROUARD', %s, NULL, 93, '2018-01-10 15:39:23');",
                    array(
                        $p,
                        $p,
                        $p,
                        $p,
                        $e, $p,
                        $t, $e,
                        $t, $p
                    )
            );
            $wpdb->query($query);

            return true;
        }

        /**
         * @name on_deactivation
         */
        public static function on_deactivation()
        {
            $options = get_option(PL_DOMAIN);

            $options['active'] = 'off';
            $options['datetime'] = current_time('mysql');

            update_option(PL_DOMAIN, $options, 'no');
        }

        /**
         * @name on_uninstall
         * @global object $wpdb
         */
        public static function on_uninstall()
        {
            global $wpdb;

            foreach (self::$tables as $table) {
                $name = self::table_name($table);
                $wpdb->query("DROP TABLE IF EXISTS $name;");
            }

            delete_option(PL_DOMAIN);
        }

        /**
         * @name paralog_menu
         */
        public function paralog_menu()
        {
            $allowed_group = 'edit_posts'; // 'manage_options'

            if (function_exists('add_menu_page')) {
                add_menu_page(__("Journaux de décollages / treuillés", PL_DOMAIN), __("Paralog", PL_DOMAIN), $allowed_group, self::admin_slug, array($this, 'about'), 'dashicons-media-spreadsheet');
                if (function_exists('add_submenu_page')) {
                    add_submenu_page(self::admin_slug, __("À propos de Paralog", PL_DOMAIN), __("À propos de", PL_DOMAIN), $allowed_group, self::admin_slug, array($this, 'about'));
    
                    $logs_hook = add_submenu_page(self::admin_slug, __("Journaux Paralog", PL_DOMAIN), __("Les journaux", PL_DOMAIN), $allowed_group, 'paralog-logs', array($this, 'list_logs'));
                    add_action("load-$logs_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-logs', __("Ajouter un décollage / treuillé", PL_DOMAIN), __("Ajouter un décollage / treuillé", PL_DOMAIN), $allowed_group, 'paralog-logs-form', array($this, 'form_log'));
                    
                    $persons_hook = add_submenu_page(self::admin_slug, __("Personnes Paralog", PL_DOMAIN), __("Les personnes", PL_DOMAIN), $allowed_group, 'paralog-persons', array($this, 'list_persons'));
                    add_action("load-$persons_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-persons', __("Ajouter une personne", PL_DOMAIN), __("Ajouter une personne", PL_DOMAIN), $allowed_group, 'paralog-persons-form', array($this, 'form_person'));
                    
                    $lines_hook = add_submenu_page(self::admin_slug, __("Lignes Paralog", PL_DOMAIN), __("Les lignes", PL_DOMAIN), $allowed_group, 'paralog-lines', array($this, 'list_lines'));
                    add_action("load-$lines_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-lines', __("Ajouter une ligne", PL_DOMAIN), __("Ajouter une ligne", PL_DOMAIN), $allowed_group, 'paralog-lines-form', array($this, 'form_line'));
                    
                    $sites_hook = add_submenu_page(self::admin_slug, __("Sites Paralog", PL_DOMAIN), __("Les sites", PL_DOMAIN), $allowed_group, 'paralog-sites', array($this, 'list_sites'));
                    add_action("load-$sites_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-sites', __("Ajouter un site", PL_DOMAIN), __("Ajouter un site", PL_DOMAIN), $allowed_group, 'paralog-sites-form', array($this, 'form_site'));
                }
            }
        }

        /**
         * @name admin_paralog_bar_menu
         * @global object wp_admin_bar
         */
        public function admin_paralog_bar_menu()
        {
            global $wp_admin_bar;

            $wp_admin_bar->add_menu(array(
                'parent' => 'new-content',
                'id' => 'paralog-admin-bar',
                'title' => __('Décollage', PL_DOMAIN),
                'href' => admin_url('admin.php?page=paralog-logs-form')
            ));
        }

        /**
         * @name stats_sites
         * @global object $wpdb
         * @param integer $year
         * @return array
         */
        private function stats_sites($year)
        {
            global $wpdb;

            $query = $wpdb->prepare(
                "SELECT site_name, COUNT(*) AS site_count "
                . "FROM " . $this->table_name('logs') . " "
                . "WHERE YEAR(takeoff) = %d "
                . "GROUP BY site_name;",
                $year
            );

            return $wpdb->get_results($query, ARRAY_A);
        }

        /**
         * @name stats_lines
         * @global object $wpdb
         * @param integer $year
         * @return array
         */
        private function stats_lines($year)
        {
            global $wpdb;

            $query = $wpdb->prepare(
                "SELECT line_name, COUNT(*) AS line_count "
                . "FROM " . $this->table_name('logs') . " "
                . "WHERE YEAR(takeoff) = %d "
                . "GROUP BY line_name;",
                $year
            );

            return $wpdb->get_results($query, ARRAY_A);
        }

        /**
         * @name stats_winchmen
         * @global object $wpdb
         * @param integer $year
         * @return array
         */
        private function stats_winchmen($year)
        {
            global $wpdb;

            $query = $wpdb->prepare(
                "SELECT winchman_name, COUNT(*) AS winchman_count "
                . "FROM " . $this->table_name('logs') . " "
                . "WHERE YEAR(takeoff) = %d AND winchman_name IS NOT NULL "
                . "GROUP BY winchman_name;",
                $year
            );

            return $wpdb->get_results($query, ARRAY_A);
        }

        /**
         * @name stats_pilots
         * @global object $wpdb
         * @param integer $year
         * @return array
         */
        private function stats_pilots($year)
        {
            global $wpdb;

            $query = $wpdb->prepare(
                "SELECT pilot_name, COUNT(*) AS pilot_count "
                . "FROM " . $this->table_name('logs') . " "
                . "WHERE YEAR(takeoff) = %d "
                . "GROUP BY pilot_name;",
                $year
            );

            return $wpdb->get_results($query, ARRAY_A);
        }

        /**
         * @name stats_passengers
         * @global object $wpdb
         * @param integer $year
         * @return array
         */
        private function stats_passengers($year)
        {
            global $wpdb;

            $query = $wpdb->prepare(
                "SELECT site_name, COUNT(*) AS passenger_count "
                . "FROM " . $this->table_name('logs') . " "
                . "WHERE YEAR(takeoff) = %d AND passenger_name IS NOT NULL "
                . "GROUP BY site_name;",
                $year
            );

            return $wpdb->get_results($query, ARRAY_A);
        }

        /**
         * @name years_takeoff
         * @global object $wpdb
         * @return array
         */
        private function years_takeoff()
        {
            global $wpdb;

            $query = "SELECT YEAR(takeoff) AS valeur, YEAR(takeoff) AS libelle "
                . "FROM " . $this->table_name('logs') . " "
                . "GROUP BY YEAR(takeoff);";
                
            return $wpdb->get_results($query, ARRAY_A);
        }

        /**
         * @name about
         * @global object $wpdb
         */
        public function about()
        {
            $param_year = isset($_GET['annee']) ? $_GET['annee'] : '';
            $demo_datas = isset($_GET['demo']) ? $_GET['demo']: false;

            if ($demo_datas) {
                Paralog::insert_demo_datas();
            }

            $annees = $this->years_takeoff();
            if (empty($annees)) {
                $ad = __('aucune donnée');
                $annees = array(array('valeur' => '', 'libelle' => $ad));
                $sites = array(array('site_name' => $ad, 'site_count' => '-'));
                $lines =  array(array('line_name' => $ad, 'line_count' => '-'));
                $winchmen = array(array('winchman_name' => $ad, 'winchman_count' => '-'));
                $pilots = array(array('pilot_name' => $ad, 'pilot_count' => '-'));
                $passengers = array(array('site_name' => $ad, 'passenger_count' => '-'));
                $demo_datas = true;
            } else {
                $annees = array_merge(array(array('valeur' => '', 'libelle' => __('choisissez une année'))), $annees);
                $sites = $this->stats_sites($param_year);
                $lines = $this->stats_lines($param_year);
                $winchmen = $this->stats_winchmen($param_year);
                $pilots = $this->stats_pilots($param_year);
                $passengers = $this->stats_passengers($param_year);
                $demo_datas = false;
            } ?>
            <div class="wrap">
                <h1><?= _e("À propos de", PL_DOMAIN) . " Paralog"; ?></h1>
                <h2><?= _e("Journaux des décollages / treuillés", PL_DOMAIN); ?></h2>
                <div class="notice inline notice-info">
                    <p><?= _e("Cette extension permet à toutes les personnes autorisées, de gérer les journaux des décollages / treuillés d'un ou plusieurs sites de vols. Ce programme a été initialement pensé pour les treuillés en plaine. Cependant, il peut très bien être utilisé sur les sites de décollages de relief.", PL_DOMAIN); ?></p>
                    <?= _e("Notions", PL_DOMAIN); ?>
                    <ul class="ul-square">
                        <li><?= _e('Le site : correspond au lieu de manière générale <span class="PL_gris_clair">(ex: Aslonnes, Mont Bouquet, Annecy, Samoëns, etc.)</span>', PL_DOMAIN); ?>.</li>
                        <li><?= _e('La ligne : représente la ligne du treuil <span class="PL_gris_clair">(ex: Treuil 1B)</span>. Si celui-ci en possède plusieurs <span class="PL_gris_clair">(ex: Treuil 1B-L1, Treuil 1B-L2)</span> ou si le site possède plusieurs espaces de décollages <span class="PL_gris_clair">(ex: Déco Est, Déco Sud, Planfait, Montmin, Plateau des saix, 1600, La bourgeoise, etc.)</span>', PL_DOMAIN); ?></li>
                    </ul>
                </div>
                <form name="statistiques" method="get" action="">
                    <input type="hidden" name="page" value="<?= self::admin_slug; ?>" />
                    <input type="hidden" name="demo" value="<?= $demo_datas; ?>" />
                    <h2><?= _e("Statistiques", PL_DOMAIN); ?></h2>
                    <label><?= _e("Année", PL_DOMAIN); ?> : 
                        <select name="annee" onchange="submit();">
                            <?php foreach ($annees as $annee) : ?>
                                <option value="<?= $annee['valeur']; ?>"<?= ($param_year == $annee['valeur'] ? 'selected' : '') ?>><?= $annee['libelle']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php 
                        if ($demo_datas) {
                            echo '<p>' .  __('aucune donnée à visualiser ?', PL_DOMAIN)
                                 . '<button type="submit" class="page-title-action">ajouter de données de démonstration</button></p>';
                        } ?>
                    <h3><?= _e("Les sites", PL_DOMAIN); ?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?= _e("Nom des sites", PL_DOMAIN); ?></th>
                                <th><?= _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sites as $site) : ?>
                                <tr>
                                    <td><?= $site['site_name']; ?></td>
                                    <td><?= $site['site_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3><?= _e("Les lignes", PL_DOMAIN); ?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?= _e("Nom des lignes", PL_DOMAIN); ?></th>
                                <th><?= _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $line) : ?>
                                <tr>
                                    <td><?= $line['line_name']; ?></td>
                                    <td><?= $line['line_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3><?= _e("Les treuilleurs", PL_DOMAIN); ?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?= _e("Nom des treuilleurs", PL_DOMAIN); ?></th>
                                <th><?= _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($winchmen as $winchman) : ?>
                                <tr>
                                    <td><?= $winchman['winchman_name']; ?></td>
                                    <td><?= $winchman['winchman_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3><?= _e("Les pilotes", PL_DOMAIN); ?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?= _e("Nom des pilotes", PL_DOMAIN); ?></th>
                                <th><?= _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pilots as $pilot) : ?>
                                <tr>
                                    <td><?= $pilot['pilot_name']; ?></td>
                                    <td><?= $pilot['pilot_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3><?= _e("Les passagers", PL_DOMAIN); ?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?= _e("Nom des sites", PL_DOMAIN); ?></th>
                                <th><?= _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($passengers as $passenger) : ?>
                                <tr>
                                    <td><?= $passenger['site_name']; ?></td>
                                    <td><?= $passenger['passenger_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                <p>*
                <?= _e('Nombre de décollages ou de treuillés'); ?>
                </p>
            </div>
            <?php
        }

        /**
         * @name list_logs
         */
        public function list_logs()
        {
            require_once($this->plugin_dir . '/includes/paralog_log.php');
            $class = new Paralog_Log();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1; ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?= _e("Gestion des décollages / treuillés", PL_DOMAIN); ?></h1> <a href="<?= get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>" class="page-title-action"><?= _e("Ajouter un décollage / treuillé", PL_DOMAIN); ?></a>
                <?= $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <?php
                    //$class->search_box('search', 'search_id');
                    $class->display(); ?>
                </form>
            </div>
            <?php
        }

        public function form_log()
        {
            require_once($this->plugin_dir . '/includes/paralog_log.php');
            $class = new Paralog_Log();
            $class->form_edit();
        }

        /**
         * @name list_sites
         */
        public function list_sites()
        {
            require_once($this->plugin_dir . '/includes/paralog_site.php');
            $class = new Paralog_Site();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1; ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?= _e("Gestion des sites", PL_DOMAIN); ?></h1> <a href="<?= get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>" class="page-title-action"><?= _e("Ajouter un site", PL_DOMAIN); ?></a>
                <?= $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <?php
                    //$site->search_box('search', 'search_id');
                    $class->display(); ?>
                </form>
            </div>
            <?php
        }

        public function form_site()
        {
            require_once($this->plugin_dir . '/includes/paralog_site.php');
            $class = new Paralog_Site();
            $class->form_edit();
        }

        /**
         * @name list_lines
         */
        public function list_lines()
        {
            require_once($this->plugin_dir . '/includes/paralog_line.php');
            $class = new Paralog_Line();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1; ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?= _e("Gestion des lignes", PL_DOMAIN); ?></h1> <a href="<?= get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>" class="page-title-action"><?= _e("Ajouter une ligne", PL_DOMAIN); ?></a>
                <?= $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <?php
                    //$site->search_box('search', 'search_id');
                    $class->display(); ?>
                </form>
            </div>
            <?php
        }

        public function form_line()
        {
            require_once($this->plugin_dir . '/includes/paralog_line.php');
            $class = new Paralog_Line();
            $class->form_edit();
        }

        /**
         * @name list_persons
         */
        public function list_persons()
        {
            require_once($this->plugin_dir . '/includes/paralog_person.php');
            $class = new Paralog_Person();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1; ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?= _e("Gestion des personnes", PL_DOMAIN); ?></h1> <a href="<?= get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>" class="page-title-action"><?= _e("Ajouter une personne", PL_DOMAIN); ?></a>
                <?= $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <?php
                    //$site->search_box('search', 'search_id');
                    $class->display(); ?>
                </form>
            </div>
            <?php
        }

        public function form_person()
        {
            require_once($this->plugin_dir . '/includes/paralog_person.php');
            $class = new Paralog_Person();
            $class->form_edit();
        }

        public static function add_options()
        {
            $option ='per_page';
            $args=array(
                'label' => __("nombre d'enregistrements"),
                'default' => 15,
                'option' => "items_$option"
            );

            add_screen_option($option, $args);
        }
    }
}

global $paralog;

$paralog = new Paralog();
