<?php
/**
 * @link              https://thierry.brouard.pro/
 * @since             1.0.0
 * @package           Paralog
 * @author            Thierry Brouard <thierry@brouard.pro>
 *
 * Plugin Name:       Paralog
 * Plugin URI:        https://thierry.brouard.pro/2018/01/paralog/
 * Description:       Gestion des journaux de décollages / treuillés avec les sites, les lignes, les pilotes, les élèves et les treuilleurs
 * Version:           1.3.8
 * Author:            Thierry Brouard <thierry@brouard.pro>
 * Author URI:        https://thierry.brouard.pro/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       paralog
 * Domain Path:       /languages
 * Requires at least: 3.1.0
 * Stable tag:        4.9.5
 * Tested up to:      4.9.5
 * Requires PHP:      5.6
 * */
if (!defined('ABSPATH')) {
    die("No direct access allowed");
}

if (!class_exists('Paralog')) {
    define('PL_VERSION', '1.3.8');
    define('PL_DB_VERSION', '1.9');
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
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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
                    . "site_id tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, "
                    . "name varchar(64) DEFAULT NULL, "
                    . "user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0, "
                    . "deleted tinyint(1) UNSIGNED NOT NULL DEFAULT 0, "
                    . "PRIMARY KEY (site_id) "
                    . ") $charset_collate";
                dbDelta($query);
                /*
                 * line equipment
                 */
                $table = self::table_name('lines');
                $query = "CREATE TABLE IF NOT EXISTS $table ( "
                    . "line_id tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, "
                    . "name varchar(32) DEFAULT NULL, "
                    . "user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0, "
                    . "deleted tinyint(1) UNSIGNED NOT NULL DEFAULT 0, "
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
                    . "user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0, "
                    . "deleted tinyint(1) UNSIGNED NOT NULL DEFAULT 0, "
                    . "PRIMARY KEY (person_id) "
                    . ") $charset_collate",
                    array(
                        $p, $e, $p,
                        $o, $n, $n,
                        $t, $e,
                    )
                );
                dbDelta($query);
                /*
                 * log book
                 */
                $table = self::table_name('logs');
                $query = $wpdb->prepare(
                    "CREATE TABLE IF NOT EXISTS $table ( "
                    . "log_id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, "
                    . "site_name varchar(64) DEFAULT NULL, "
                    . "line_name varchar(32) DEFAULT NULL, "
                    . "winchman_name varchar(129) DEFAULT NULL, "
                    . "winchman_type enum(%s,%s) DEFAULT NULL, "
                    . "pilot_name varchar(129) DEFAULT NULL, "
                    . "pilot_type enum(%s,%s) NOT NULL DEFAULT %s, "
                    . "passenger_name varchar(129) DEFAULT NULL, "
                    . "total_flying_weight smallint(5) UNSIGNED DEFAULT NULL, "
                    . "takeoff datetime NOT NULL DEFAULT '0000-00-00 00:00:00', "
                    . "user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0, "
                    . "deleted tinyint(1) UNSIGNED NOT NULL DEFAULT 0, "
                    . "PRIMARY KEY (log_id) "
                    . ") $charset_collate",
                    array(
                        $t, $e,
                        $p, $e, $p,
                    )
                );
                dbDelta($query);

                // options
                $options = array(
                    'db_version' => PL_DB_VERSION,
                    'active' => 'on',
                    'datetime' => current_time('mysql'),
                );
                update_option(PL_DOMAIN, $options, 'no');
            }
        }

        public static function insert_demo_datas()
        {
            global $wpdb;

            $user_id = get_current_user_id();

            $p = __('pilote', PL_DOMAIN);
            $t = __('treuilleur', PL_DOMAIN);
            $e = __('élève', PL_DOMAIN);
            $o = __('oui', PL_DOMAIN);
            $n = __('non', PL_DOMAIN);

            $table = self::table_name('sites');
            $query = $wpdb->prepare(
                "INSERT INTO $table (name, user_id, deleted) VALUES "
                . "('Mont Bouquet', %d, 0), "
                . "('Aslonnes \"Le Fort\"', %d, 0), "
                . "('Annecy', %d, 0), "
                . "('Massognes / Jarzay', %d, 0);",
                array(
                    $user_id,
                    $user_id,
                    $user_id,
                    $user_id,
                )
            );
            $wpdb->query($query);

            $table = self::table_name('lines');
            $query = $wpdb->prepare(
                "INSERT INTO $table (name, user_id, deleted) VALUES "
                . "('Déco EST', %d, 0), "
                . "('Déco SUD', %d, 0), "
                . "('Planfait', %d, 0), "
                . "('Montmin', %d, 0), "
                . "('Coche Cabane', %d, 0), "
                . "('Treuil 1B', %d, 0), "
                . "('Treuil 2B - ligne rouge', %d, 0), "
                . "('Treuil 2B - ligne verte', %d, 0), "
                . "('Dévidoir', %d, 0);",
                array(
                    $user_id,
                    $user_id,
                    $user_id,
                    $user_id,
                    $user_id,
                    $user_id,
                    $user_id,
                    $user_id,
                    $user_id,
                )
            );
            $wpdb->query($query);

            $table = self::table_name('persons');
            $query = $wpdb->prepare(
                "INSERT INTO $table (firstname, lastname, pilot_type, licence, winchman, winchman_type, user_id, deleted) VALUES "
                . "('Thierry', 'BROUARD', %s, '1309710X', %s, %s, %d, 0), "
                . "('Jean-Yves', 'COLLIN', %s, '0700484V', %s, %s, %d, 0), "
                . "('Quentin', 'COURTOIS', %s, '1604781B', %s, NULL, %d, 0), "
                . "('Bernard', 'MAUDET', %s, '0062282X', %s, %s, %d, 0), "
                . "('Carlos', 'MESQUITA', %s, '1302566G', %s, NULL, %d, 0);",
                array(
                    $p, $o, $e, $user_id,
                    $p, $o, $t, $user_id,
                    $p, $n, $user_id,
                    $p, $o, $t, $user_id,
                    $p, $n, $user_id,
                )
            );
            $wpdb->query($query);

            $table = self::table_name('logs');
            $query = $wpdb->prepare(
                "INSERT INTO $table (site_name, line_name, winchman_name, winchman_type, pilot_name, pilot_type, passenger_name, total_flying_weight, takeoff, user_id, deleted) VALUES "
                . "('Annecy', 'Planfait', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2017-10-10 15:06:07', %d, 0), "
                . "('Annecy', 'Montmin', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2017-10-10 15:14:45', %d, 0), "
                . "('Mont Bouquet', 'Déco SUD', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2018-01-10 15:50:21', %d, 0), "
                . "('Mont Bouquet', 'Déco EST', NULL, NULL, 'Bernard MAUDET', %s, NULL, 90, '2017-10-10 15:18:47', %d, 0), "
                . "('Massognes / Jarzay', 'Dévidoir 3B', 'Thierry BROUARD', %s, 'Quentin COURTOIS', %s, NULL, 105, '2018-01-10 15:36:55', %d, 0), "
                . "('Aslonnes \"Le Fort\"', 'Treuil 1B', 'Jean-Yves COLLIN', %s, 'Carlos MESQUITA', %s, NULL, 90, '2018-01-10 15:38:48', %d, 0), "
                . "('Aslonnes \"Le Fort\"', 'Treuil 2B - ligne rouge', 'Bernard MAUDET', %s, 'Thierry BROUARD', %s, NULL, 93, '2018-01-10 15:39:23', %d, 0);",
                array(
                    $p, $user_id,
                    $p, $user_id,
                    $p, $user_id,
                    $p, $user_id,
                    $e, $p, $user_id,
                    $t, $e, $user_id,
                    $t, $p, $user_id,
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
         * @name my_query
         * @global object $wpdb
         * @param string $query
         */
        private function my_query($query)
        {
            global $wpdb;

            if (!empty($wpdb->dbh)) {
                if ($wpdb->use_mysqli) {
                    $wpdb->result = mysqli_query($wpdb->dbh, $query);
                } else {
                    $wpdb->result = mysql_query($query, $wpdb->dbh);
                }
            }
        }

        /**
         * @name my_fetchrow
         * @global object $wpdb
         * @return array
         */
        private function my_fetchrow()
        {
            global $wpdb;

            $row = null;

            if ($wpdb->result) {
                if ($wpdb->use_mysqli) {
                    $row = mysqli_fetch_assoc($wpdb->result);
                } else {
                    $row = mysql_fetch_assoc($wpdb->result);
                }
            }

            return ($row) ? $row : false;
        }

        /**
         * @name export_csv
         * @global $wpdb
         * @param integer $year
         */
        public function export_csv($year)
        {
            global $wpdb;

            if (headers_sent()) {
                die('Headers already sent');
            }

            // Required for some browsers
            if (ini_get('zlib.output_compression')) {
                ini_set('zlib.output_compression', 'Off');
            }
            if (ob_get_contents()) { # Make sure no junk is included in the file
            ob_end_clean();
            }
            header('Pragma: public');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Cache-Control: public', false);
            header('Content-Description: File Transfer');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=data.csv');
            if (ob_get_contents()) { // Make __absolutely__ :) sure no junk is included in the file
                ob_end_flush();
                flush();
            }
            $out = fopen('php://output', 'w');
            $bom = chr(0xEF) . chr(0xBB) . chr(0xBF); // utf8-bom
            $separateur = ";";
            $delimiteur = '"';
            $echappement = "\\";

            fwrite($out, $bom, strlen($bom));
            
            $table = Paralog::table_name('logs');

            $query = "SHOW COLUMNS FROM $table WHERE Field NOT IN ('log_id','user_id','deleted');";
            $columns = $wpdb->get_results($query, ARRAY_A);
            $head = array_column($columns, 'Field');

            fputcsv($out, $head, $separateur, $delimiteur, $echappement);

            $sql = "SELECT " . implode(",", $head) . " FROM $table WHERE deleted = 0 ";

            if (is_numeric($year)) {
                $sql .= "AND YEAR(takeoff) = %d ";
                $query = $wpdb->prepare($sql, $year);
            } else {
                $query = $sql;
            }
            $query .= "ORDER BY takeoff DESC;";

            $this->my_query($query);

            while (($enregistrement = $this->my_fetchrow()) !== false) {
                fputcsv($out, $enregistrement, $separateur, $delimiteur, $echappement);
            }

            $wpdb->flush();
            fclose($out);
            exit;
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

                    $logs_hook = add_submenu_page(self::admin_slug, __("Décollages Paralog", PL_DOMAIN), __("Décollages", PL_DOMAIN), $allowed_group, 'paralog-logs', array($this, 'list_logs'));
                    add_action("load-$logs_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-logs', __("Ajouter un décollage / treuillé", PL_DOMAIN), __("Ajouter un décollage / treuillé", PL_DOMAIN), $allowed_group, 'paralog-logs-form', array($this, 'form_log'));

                    $persons_hook = add_submenu_page(self::admin_slug, __("Personnes Paralog", PL_DOMAIN), __("Personnes", PL_DOMAIN), $allowed_group, 'paralog-persons', array($this, 'list_persons'));
                    add_action("load-$persons_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-persons', __("Ajouter une personne", PL_DOMAIN), __("Ajouter une personne", PL_DOMAIN), $allowed_group, 'paralog-persons-form', array($this, 'form_person'));

                    $lines_hook = add_submenu_page(self::admin_slug, __("Lignes Paralog", PL_DOMAIN), __("Lignes", PL_DOMAIN), $allowed_group, 'paralog-lines', array($this, 'list_lines'));
                    add_action("load-$lines_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-lines', __("Ajouter une ligne", PL_DOMAIN), __("Ajouter une ligne", PL_DOMAIN), $allowed_group, 'paralog-lines-form', array($this, 'form_line'));

                    $sites_hook = add_submenu_page(self::admin_slug, __("Sites Paralog", PL_DOMAIN), __("Sites", PL_DOMAIN), $allowed_group, 'paralog-sites', array($this, 'list_sites'));
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
                'href' => admin_url('admin.php?page=paralog-logs-form'),
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

            $sites = '';
            $table = $this->table_name('logs');

            if (!empty($year)) {
                if (is_numeric($year)) {
                    $query = $wpdb->prepare(
                        "SELECT site_name, COUNT(*) AS site_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "AND YEAR(takeoff) = %d "
                        . "GROUP BY site_name;",
                        $year
                    );
                } else {
                    $query = "SELECT site_name, COUNT(*) AS site_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "GROUP BY site_name;";
                }
                $sites = $wpdb->get_results($query, ARRAY_A);
            }
            if (empty($sites)) {
                $sites = array(array('site_name' => __('aucune donnée'), 'site_count' => '-'));
            }

            return $sites;
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

            $lines = '';
            $table = $this->table_name('logs');

            if (!empty($year)) {
                if (is_numeric($year)) {
                    $query = $wpdb->prepare(
                        "SELECT line_name, COUNT(*) AS line_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "AND YEAR(takeoff) = %d "
                        . "GROUP BY line_name;",
                        $year
                    );
                } else {
                    $query = "SELECT line_name, COUNT(*) AS line_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "GROUP BY line_name;";
                }
                $lines = $wpdb->get_results($query, ARRAY_A);
            }
            if (empty($lines)) {
                $lines = array(array('line_name' => __('aucune donnée'), 'line_count' => '-'));
            }

            return $lines;
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

            $winchmen = '';
            $table = $this->table_name('logs');

            if (!empty($year)) {
                if (is_numeric($year)) {
                    $query = $wpdb->prepare(
                        "SELECT winchman_name, COUNT(*) AS winchman_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "AND YEAR(takeoff) = %d "
                        . "AND winchman_name IS NOT NULL "
                        . "GROUP BY winchman_name;",
                        $year
                    );
                } else {
                    $query = "SELECT winchman_name, COUNT(*) AS winchman_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "AND winchman_name IS NOT NULL "
                        . "GROUP BY winchman_name;";
                }
                $winchmen = $wpdb->get_results($query, ARRAY_A);
            }
            if (empty($winchmen)) {
                $winchmen = array(array('winchman_name' => __('aucune donnée'), 'winchman_count' => '-'));
            }

            return $winchmen;
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

            $pilots = '';
            $table = $this->table_name('logs');

            if (!empty($year)) {
                if (is_numeric($year)) {
                    $query = $wpdb->prepare(
                        "SELECT pilot_name, COUNT(*) AS pilot_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "AND YEAR(takeoff) = %d "
                        . "GROUP BY pilot_name;",
                        $year
                    );
                } else {
                    $query = "SELECT pilot_name, COUNT(*) AS pilot_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "GROUP BY pilot_name;";
                }
                $pilots = $wpdb->get_results($query, ARRAY_A);
            }
            if (empty($pilots)) {
                $pilots = array(array('pilot_name' => __('aucune donnée'), 'pilot_count' => '-'));
            }

            return $pilots;
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

            $passengers = '';
            $table = " . $this->table_name('logs') . ";

            if (!empty($year)) {
                if (is_numeric($year)) {
                    $query = $wpdb->prepare(
                        "SELECT site_name, COUNT(*) AS passenger_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "AND YEAR(takeoff) = %d "
                        . "AND passenger_name IS NOT NULL "
                        . "GROUP BY site_name;",
                        $year
                    );
                } else {
                    $query = "SELECT site_name, COUNT(*) AS passenger_count "
                        . "FROM $table "
                        . "WHERE deleted = 0 "
                        . "AND passenger_name IS NOT NULL "
                        . "GROUP BY site_name;";
                }
                $passengers = $wpdb->get_results($query, ARRAY_A);
            }
            if (empty($passengers)) {
                $passengers = array(array('site_name' => __('aucune donnée'), 'passenger_count' => '-'));
            }

            return $passengers;
        }

        /**
         * @name years_takeoff
         * @global object $wpdb
         * @return array
         */
        private function years_takeoff()
        {
            global $wpdb;
            
            $table = $this->table_name('logs');

            $query = "SELECT YEAR(takeoff) AS valeur, YEAR(takeoff) AS libelle "
                . "FROM $table "
                . "WHERE deleted = 0 "
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
            $demo_datas = isset($_GET['demo']) ? $_GET['demo'] : '0';
            $export = isset($_GET['export']) ? $_GET['export'] : '0';

            if ($demo_datas == '1') {
                Paralog::insert_demo_datas();
            }
            if ($export == '1') {
                Paralog::export_csv($param_year);
            }

            $years_list = $this->years_takeoff();
            if (empty($years_list)) {
                $demo_datas = '1';
                $years = array(array('valeur' => '', 'libelle' => __('aucune donnée')));
            } else {
                $demo_datas = '0';
                $years = array_merge(array(
                    array('valeur' => '', 'libelle' => __('choisissez une année')),
                    array('valeur' => '*', 'libelle' => __('toutes les années')),
                ), $years_list);
            }
            $sites = $this->stats_sites($param_year);
            $lines = $this->stats_lines($param_year);
            $winchmen = $this->stats_winchmen($param_year);
            $pilots = $this->stats_pilots($param_year);
            $passengers = $this->stats_passengers($param_year);?>
            <div class="wrap">
                <h1><?php _e("À propos de", PL_DOMAIN) . " Paralog";?></h1>
                <h2><?php _e("Journaux des décollages / treuillés", PL_DOMAIN);?></h2>
                <div class="notice inline notice-info">
                    <p><?php _e("Cette extension permet à toutes les personnes autorisées, de gérer les journaux des décollages / treuillés d'un ou plusieurs sites de vols. Ce programme a été initialement pensé pour les treuillés en plaine. Cependant, il peut très bien être utilisé sur les sites de décollages de relief.", PL_DOMAIN);?></p>
                    <?php _e("Notions", PL_DOMAIN);?>
                    <ul class="ul-square">
                        <li><?php _e('Le site : correspond au lieu de manière générale <span class="PL_gris_clair">(ex: Aslonnes, Mont Bouquet, Annecy, Samoëns, etc.)</span>', PL_DOMAIN);?>.</li>
                        <li><?php _e('La ligne : représente la ligne du treuil <span class="PL_gris_clair">(ex: Treuil 1B)</span>. Si celui-ci en possède plusieurs <span class="PL_gris_clair">(ex: Treuil 1B-L1, Treuil 1B-L2)</span> ou si le site possède plusieurs espaces de décollages <span class="PL_gris_clair">(ex: Déco Est, Déco Sud, Planfait, Montmin, Plateau des saix, 1600, La bourgeoise, etc.)</span>', PL_DOMAIN);?></li>
                    </ul>
                </div>
                <form name="statistiques" method="get" action="">
                    <input type="hidden" name="page" value="<?=self::admin_slug;?>" />
                    <h2><?php _e("Statistiques", PL_DOMAIN);?></h2>
                    <label><?php _e("Année", PL_DOMAIN);?> :
                        <select name="annee" onchange="submit();">
                            <?php foreach ($years as $year): ?>
                                <option value="<?=$year['valeur'];?>"<?=($param_year == $year['valeur'] ? 'selected' : '')?>><?=$year['libelle'];?></option>
                            <?php endforeach;?>
                        </select>
                    </label>
                    <?php
if ($demo_datas == '1') {
                echo '<p>' . __('aucune donnée à visualiser ?', PL_DOMAIN) . '<button type="submit" name="demo" value="1" class="page-title-action">' . __('ajouter de données de démonstration', PL_DOMAIN) . '</button></p>';
            }
            if (!empty($param_year)) {
                echo '<p>' . __('exporter les données sélectionnées', PL_DOMAIN) . '<button type="submit" name="export" value="1" class="page-title-action">' . __('exporter', PL_DOMAIN) . '</button></p>';
            }?>
                    <h3><?php _e("Les sites", PL_DOMAIN);?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e("Nom des sites", PL_DOMAIN);?></th>
                                <th><?php _e("Quantité", PL_DOMAIN);?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sites as $site): ?>
                                <tr>
                                    <td><?=$site['site_name'];?></td>
                                    <td><?=$site['site_count'];?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                    <h3><?php _e("Les lignes", PL_DOMAIN);?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e("Nom des lignes", PL_DOMAIN);?></th>
                                <th><?php _e("Quantité", PL_DOMAIN);?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $line): ?>
                                <tr>
                                    <td><?=$line['line_name'];?></td>
                                    <td><?=$line['line_count'];?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                    <h3><?php _e("Les treuilleurs", PL_DOMAIN);?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e("Nom des treuilleurs", PL_DOMAIN);?></th>
                                <th><?php _e("Quantité", PL_DOMAIN);?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($winchmen as $winchman): ?>
                                <tr>
                                    <td><?=$winchman['winchman_name'];?></td>
                                    <td><?=$winchman['winchman_count'];?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                    <h3><?php _e("Les pilotes", PL_DOMAIN);?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e("Nom des pilotes", PL_DOMAIN);?></th>
                                <th><?php _e("Quantité", PL_DOMAIN);?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pilots as $pilot): ?>
                                <tr>
                                    <td><?=$pilot['pilot_name'];?></td>
                                    <td><?=$pilot['pilot_count'];?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                    <h3><?php _e("Les passagers", PL_DOMAIN);?></h3>
                    <table class="table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e("Nom des sites", PL_DOMAIN);?></th>
                                <th><?php _e("Quantité", PL_DOMAIN);?><sup>*</sup></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($passengers as $passenger): ?>
                                <tr>
                                    <td><?=$passenger['site_name'];?></td>
                                    <td><?=$passenger['passenger_count'];?></td>
                                </tr>
                            <?php endforeach;?>
                        </tbody>
                    </table>
                </form>
                <p>*
                    <?php _e('Nombre de décollages ou de treuillés');?>
                </p>
            </div>
            <?php
}

        /**
         * @name list_logs
         */
        public function list_logs()
        {
            require_once $this->plugin_dir . '/includes/paralog_log.php';
            $class = new Paralog_Log();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des décollages / treuillés", PL_DOMAIN);?></h1> <a href="<?=get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged");?>" class="page-title-action"><?php _e("Ajouter un décollage / treuillé", PL_DOMAIN);?></a>
                <?=$message;?>
                <form method="post">
                    <input type="hidden" name="page" value="<?=$page?>">
                    <?php
//$class->search_box('search', 'search_id');
            $class->display();?>
                </form>
            </div>
            <?php
}

        public function form_log()
        {
            require_once $this->plugin_dir . '/includes/paralog_log.php';
            $class = new Paralog_Log();
            $class->form_edit();
        }

        /**
         * @name list_sites
         */
        public function list_sites()
        {
            require_once $this->plugin_dir . '/includes/paralog_site.php';
            $class = new Paralog_Site();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des sites", PL_DOMAIN);?></h1> <a href="<?=get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged");?>" class="page-title-action"><?php _e("Ajouter un site", PL_DOMAIN);?></a>
                <?=$message;?>
                <form method="post">
                    <input type="hidden" name="page" value="<?=$page?>">
                    <?php
//$site->search_box('search', 'search_id');
            $class->display();?>
                </form>
            </div>
            <?php
}

        public function form_site()
        {
            require_once $this->plugin_dir . '/includes/paralog_site.php';
            $class = new Paralog_Site();
            $class->form_edit();
        }

        /**
         * @name list_lines
         */
        public function list_lines()
        {
            require_once $this->plugin_dir . '/includes/paralog_line.php';
            $class = new Paralog_Line();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des lignes", PL_DOMAIN);?></h1> <a href="<?=get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged");?>" class="page-title-action"><?php _e("Ajouter une ligne", PL_DOMAIN);?></a>
                <?=$message;?>
                <form method="post">
                    <input type="hidden" name="page" value="<?=$page?>">
                    <?php
//$site->search_box('search', 'search_id');
            $class->display();?>
                </form>
            </div>
            <?php
}

        public function form_line()
        {
            require_once $this->plugin_dir . '/includes/paralog_line.php';
            $class = new Paralog_Line();
            $class->form_edit();
        }

        /**
         * @name list_persons
         */
        public function list_persons()
        {
            require_once $this->plugin_dir . '/includes/paralog_person.php';
            $class = new Paralog_Person();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), count($_REQUEST['id'])) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des personnes", PL_DOMAIN);?></h1> <a href="<?=get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged");?>" class="page-title-action"><?php _e("Ajouter une personne", PL_DOMAIN);?></a>
                <?=$message;?>
                <form method="post">
                    <input type="hidden" name="page" value="<?=$page?>">
                    <?php
                    // $site->search_box('search', 'search_id');
                    $class->display();?>
                </form>
            </div>
            <?php
}

        public function form_person()
        {
            require_once $this->plugin_dir . '/includes/paralog_person.php';
            $class = new Paralog_Person();
            $class->form_edit();
        }

        public static function add_options()
        {
            $option = 'per_page';
            $args = array(
                'label' => __("nombre d'enregistrements"),
                'default' => 15,
                'option' => "items_$option",
            );

            add_screen_option($option, $args);
        }
    }
}

global $paralog;

$paralog = new Paralog();
