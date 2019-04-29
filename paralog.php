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
 * Version:           1.6.6
 * Author:            Thierry Brouard <thierry@brouard.pro>
 * Author URI:        https://thierry.brouard.pro/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       paralog
 * Domain Path:       /languages
 * Requires at least: 3.1.0
 * Stable tag:        5.1.1
 * Tested up to:      5.1.1
 * Requires PHP:      5.6
 */
if (!defined('ABSPATH')) {
    wp_die('No direct access allowed', 'Security');
}

/*
 * autoloader
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'Paralog_') !== false) {
        $file = dirname(__FILE__) . '/includes/' . strtolower($class) . '.php';
        if (file_exists($file)) {
            include_once $file;
        }
    }
});

if (!class_exists('Paralog')) {
    define('PL_VERSION', '1.6.6');
    define('PL_DB_VERSION', '2.5');
    define('PL_DOMAIN', 'paralog');
    define('PL_ADMIN_SLUG', 'paralog-admin');

    /**
     * @name Paralog
     */
    class Paralog
    {
        public $plugin_dir;
        public $plugin_url;
        public $plugin_name;
        private static $tables = array(
            'activities',
            'activities_persons',
            'sites',
            'lines',
            'persons',
            'logs',
            'attendances',
        );

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

            add_filter('cron_schedules', array('Paralog_Options', 'add_weekly_to_cron'));

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
         * @param string $name
         *
         * @return string
         * @global object $wpdb
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

            /*
             * database
             */
            if ($options['db_version'] != PL_DB_VERSION) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                /*
                 * activity
                 */
                $table = self::table_name('activities');
                $query = $wpdb->prepare("CREATE TABLE IF NOT EXISTS `$table` ( " .
                    "`activity_id` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT, " .
                    "`date` DATE NOT NULL DEFAULT '0000-00-00', " .
                    "`site_name` VARCHAR(64) NULL DEFAULT NULL, " .
                    "`line_name` VARCHAR(32) NULL DEFAULT NULL, " .
                    "`start_wind_orientation` ENUM(%s,%s,%s,%s,%s,%s,%s,%s) NULL DEFAULT NULL, " .
                    "`end_wind_orientation`  ENUM(%s,%s,%s,%s,%s,%s,%s,%s) NULL DEFAULT NULL, " .
                    "`start_counter` MEDIUMINT(8) UNSIGNED NOT NULL, " .
                    "`end_counter` MEDIUMINT(8) UNSIGNED NOT NULL, " .
                    "`start_time` TIME NOT NULL DEFAULT '00:00:00', " .
                    "`end_time` TIME NOT NULL DEFAULT '00:00:00', " .
                    "`start_gazoline` TINYINT(3) UNSIGNED NOT NULL DEFAULT 100, " .
                    "`end_gazoline` TINYINT(3) UNSIGNED NOT NULL DEFAULT 100, " .
                    "`comment` MEDIUMTEXT NULL DEFAULT NULL, " .
                    "`winch_incident` MEDIUMTEXT NULL DEFAULT NULL, " .
                    "`fly_incident` MEDIUMTEXT NULL DEFAULT NULL, " .
                    "`user_id` BIGINT(20) NOT NULL DEFAULT 0, " .
                    "`deleted` TINYINT(1) NOT NULL DEFAULT 0, " .
                    "PRIMARY KEY (`activity_id`) " .
                    ") $charset_collate",
                    array(
                        __('nord', PL_DOMAIN),
                        __('nord-est', PL_DOMAIN),
                        __('est', PL_DOMAIN),
                        __('sud-est', PL_DOMAIN),
                        __('sud', PL_DOMAIN),
                        __('sud-ouest', PL_DOMAIN),
                        __('ouest', PL_DOMAIN),
                        __('nord-ouest', PL_DOMAIN),
                        __('nord', PL_DOMAIN),
                        __('nord-est', PL_DOMAIN),
                        __('est', PL_DOMAIN),
                        __('sud-est', PL_DOMAIN),
                        __('sud', PL_DOMAIN),
                        __('sud-ouest', PL_DOMAIN),
                        __('ouest', PL_DOMAIN),
                        __('nord-ouest', PL_DOMAIN),
                    )
                );
                dbDelta($query);
                /*
                 * activities persons
                 */
                $table = self::table_name('activities_persons');
                $query = $wpdb->prepare("CREATE TABLE IF NOT EXISTS `$table` ( " .
                    "`activity_person_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, " .
                    "`activity_id` MEDIUMINT(8) UNSIGNED NOT NULL, " .
                    "`person_type` ENUM(%s,%s,%s) NULL DEFAULT NULL, " .
                    "`person_name` VARCHAR(129) NULL DEFAULT NULL, " .
                    "`user_id` BIGINT(20) NOT NULL DEFAULT 0, " .
                    "`deleted` TINYINT(1) NOT NULL DEFAULT 0, " .
                    "PRIMARY KEY (`activity_person_id`) " .
                    ") $charset_collate",
                    array(
                        __('moniteur', PL_DOMAIN),
                        __('treuilleur', PL_DOMAIN),
                        __('plateforme', PL_DOMAIN),
                    )
                );
                dbDelta($query);
                /*
                 * area
                 */
                $table = self::table_name('sites');
                $query = "CREATE TABLE IF NOT EXISTS `$table` ( " .
                    "`site_id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT, " .
                    "`name` VARCHAR(64) DEFAULT NULL, " .
                    "`message` TEXT NULL DEFAULT NULL, " .
                    "`user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, " .
                    "`deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, " .
                    "PRIMARY KEY (`site_id`) " .
                    ") $charset_collate";
                dbDelta($query);
                /*
                 * line equipment
                 */
                $table = self::table_name('lines');
                $query = "CREATE TABLE IF NOT EXISTS `$table` ( " .
                    "`line_id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT, " .
                    "`name` VARCHAR(32) DEFAULT NULL, " .
                    "`user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, " .
                    "`deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, " .
                    "PRIMARY KEY (`line_id`) " .
                    ") $charset_collate";
                dbDelta($query);
                /*
                 * pilot, student and winchman
                 */
                $table = self::table_name('persons');
                $query = $wpdb->prepare("CREATE TABLE IF NOT EXISTS `$table` ( " .
                    "`person_id` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT, " .
                    "`firstname` VARCHAR(64) DEFAULT NULL, " .
                    "`lastname` VARCHAR(64) DEFAULT NULL, " .
                    "`email` VARCHAR(259) DEFAULT NULL, " .
                    "`raise` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1, " .
                    "`pilot_type` ENUM(%s,%s) NOT NULL DEFAULT %s, " .
                    "`licence` VARCHAR(10) DEFAULT NULL, " .
                    "`winchman` ENUM(%s,%s) NOT NULL DEFAULT %s, " .
                    "`winchman_type` ENUM(%s,%s) DEFAULT NULL, " .
                    "`user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, " .
                    "`deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, " .
                    "PRIMARY KEY (`person_id`) " .
                    ") $charset_collate",
                    array(
                        __('pilote', PL_DOMAIN),
                        __('élève', PL_DOMAIN),
                        __('pilote', PL_DOMAIN),
                        __('oui', PL_DOMAIN),
                        __('non', PL_DOMAIN),
                        __('non', PL_DOMAIN),
                        __('treuilleur', PL_DOMAIN),
                        __('élève', PL_DOMAIN),
                    )
                );
                dbDelta($query);
                /*
                 * log book
                 */
                $table = self::table_name('logs');
                $query = $wpdb->prepare("CREATE TABLE IF NOT EXISTS `$table` ( " .
                    "`log_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, " .
                    "`site_name` VARCHAR(64) DEFAULT NULL, " .
                    "`line_name` VARCHAR(32) DEFAULT NULL, " .
                    "`winchman_name` VARCHAR(129) DEFAULT NULL, " .
                    "`winchman_type` ENUM(%s,%s) DEFAULT NULL, " .
                    "`pilot_name` VARCHAR(129) DEFAULT NULL, " .
                    "`pilot_type` ENUM(%s,%s) NOT NULL DEFAULT %s, " .
                    "`passenger_name` VARCHAR(129) DEFAULT NULL, " .
                    "`total_flying_weight` SMALLINT(5) UNSIGNED DEFAULT NULL, " .
                    "`takeoff` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', " .
                    "`user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, " .
                    "`deleted` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0, " .
                    "PRIMARY KEY (`log_id`) " .
                    ") $charset_collate",
                    array(
                        __('treuilleur', PL_DOMAIN),
                        __('élève', PL_DOMAIN),
                        __('pilote', PL_DOMAIN),
                        __('élève', PL_DOMAIN),
                        __('pilote', PL_DOMAIN),
                    )
                );
                dbDelta($query);
                /*
                 * attendances
                 */
                $table = self::table_name('attendances');
                $query = $wpdb->prepare("CREATE TABLE IF NOT EXISTS `$table` (" .
                    "`date` DATE NOT NULL, " .
                    "`person_id` SMALLINT(5) UNSIGNED NOT NULL, " .
                    "`attendance` ENUM(%s,%s,%s) NOT NULL DEFAULT %s, " .
                    "UNIQUE KEY `udp` (`date`,`person_id`) " .
                    ") $charset_collate",
                    __('oui', PL_DOMAIN),
                    __('non', PL_DOMAIN),
                    __('peut-être', PL_DOMAIN),
                    __('oui', PL_DOMAIN)
                );
                dbDelta($query);
                /*
                 * options
                 */
                $options = array(
                    'db_version' => PL_DB_VERSION,
                    'active' => 'on',
                    'datetime' => current_time('mysql'),
                );
                update_option(PL_DOMAIN, $options, 'no');
            }
            /*
             * CRON
             */
            Paralog_Options::add_cron();
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

            Paralog_Options::remove_cron();
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
                $wpdb->query("DROP TABLE IF EXISTS `$name`");
            }

            delete_option(PL_DOMAIN);
        }

        /**
         * @name paralog_menu
         */
        public function paralog_menu()
        {
            $allowed_group = 'edit_posts';

            if (function_exists('add_menu_page')) {
                add_menu_page(__("Journaux de décollages / treuillés", PL_DOMAIN), __("Paralog", PL_DOMAIN), $allowed_group, PL_ADMIN_SLUG, array(
                    $this,
                    'about',
                ), 'dashicons-media-spreadsheet');
                if (function_exists('add_submenu_page')) {
                    /*
                     * about
                     */
                    add_submenu_page(PL_ADMIN_SLUG, __("À propos de Paralog", PL_DOMAIN), __("À propos de", PL_DOMAIN), $allowed_group, PL_ADMIN_SLUG, array(
                        $this,
                        'about',
                    ));
                    /*
                     * log
                     */
                    $logs_hook = add_submenu_page(PL_ADMIN_SLUG, __("Décollages Paralog", PL_DOMAIN), __("Décollages", PL_DOMAIN), $allowed_group, 'paralog-logs', array(
                        $this,
                        'list_logs',
                    ));
                    add_action("load-$logs_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-logs', __("Ajouter un décollage / treuillé", PL_DOMAIN), __("Ajouter un décollage / treuillé", PL_DOMAIN), $allowed_group, 'paralog-logs-form', array(
                        $this,
                        'form_log',
                    ));
                    /*
                     * person
                     */
                    $persons_hook = add_submenu_page(PL_ADMIN_SLUG, __("Personnes Paralog", PL_DOMAIN), __("Personnes", PL_DOMAIN), $allowed_group, 'paralog-persons', array(
                        $this,
                        'list_persons',
                    ));
                    add_action("load-$persons_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-persons', __("Ajouter une personne", PL_DOMAIN), __("Ajouter une personne", PL_DOMAIN), $allowed_group, 'paralog-persons-form', array(
                        $this,
                        'form_person',
                    ));
                    /*
                     * lines
                     */
                    $lines_hook = add_submenu_page(PL_ADMIN_SLUG, __("Lignes Paralog", PL_DOMAIN), __("Lignes", PL_DOMAIN), $allowed_group, 'paralog-lines', array(
                        $this,
                        'list_lines',
                    ));
                    add_action("load-$lines_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-lines', __("Ajouter une ligne", PL_DOMAIN), __("Ajouter une ligne", PL_DOMAIN), $allowed_group, 'paralog-lines-form', array(
                        $this,
                        'form_line',
                    ));
                    /*
                     * sites
                     */
                    $sites_hook = add_submenu_page(PL_ADMIN_SLUG, __("Sites Paralog", PL_DOMAIN), __("Sites", PL_DOMAIN), $allowed_group, 'paralog-sites', array(
                        $this,
                        'list_sites',
                    ));
                    add_action("load-$sites_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-sites', __("Ajouter un site", PL_DOMAIN), __("Ajouter un site", PL_DOMAIN), $allowed_group, 'paralog-sites-form', array(
                        $this,
                        'form_site',
                    ));
                    /*
                     * activities
                     */
                    $activities_hook = add_submenu_page(PL_ADMIN_SLUG, __("Activités Paralog", PL_DOMAIN), __("Activités", PL_DOMAIN), $allowed_group, 'paralog-activities', array(
                        $this,
                        'list_activities',
                    ));
                    add_action("load-$activities_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-activities', __("Ajouter une activité", PL_DOMAIN), __("Ajouter une activité", PL_DOMAIN), $allowed_group, 'paralog-activities-form', array(
                        $this,
                        'form_activity',
                    ));
                    /*
                     * attendances
                     */
                    $attendances_hook = add_submenu_page(PL_ADMIN_SLUG, __("Présence Paralog", PL_DOMAIN), __("Présences", PL_DOMAIN), $allowed_group, 'paralog-attendances', array(
                        $this,
                        'list_attendances',
                    ));
                    add_action("load-$attendances_hook", array($this, 'add_options'));
                    add_submenu_page('paralog-attendance', __("Ajouter une présence", PL_DOMAIN), __("Ajouter une présence", PL_DOMAIN), $allowed_group, 'paralog-attendances-form', array(
                        $this,
                        'form_attendance',
                    ));
                    /*
                     * options
                     */
                    add_submenu_page(PL_ADMIN_SLUG, __('Réglages', PL_DOMAIN), __('Réglages', PL_DOMAIN), 'manage_options', 'paralog_options', array(
                        $this,
                        'form_options',
                    ));
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
         * @name about
         * @global object $wpdb
         */
        public function about()
        {
            $param_year = isset($_GET['annee']) ? $_GET['annee'] : '';
            $demo_data = isset($_GET['demo']) ? $_GET['demo'] : '0';
            $export = isset($_GET['export']) ? $_GET['export'] : '0';

            if ($demo_data == '1') {
                Paralog_Statistic::insert_demo_datas();
            }
            if ($export == '1') {
                Paralog_Statistic::export_csv($param_year);
            }

            Paralog_Statistic::display_stats($param_year);
        }

        public function form_options()
        {
            $class = new Paralog_Options();
            $class->form_edit();
        }

        /**
         * @name list_logs
         */
        public function list_logs()
        {
            $class = new Paralog_Log();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $count = is_array($_REQUEST['id']) ? count($_REQUEST['id']) : 1;
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), $count) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des décollages / treuillés"); ?></h1>
                <a href="<?php echo get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>"
                   class="page-title-action">
                    <span class="fa fa-plus"></span>
                    <?php _e("Ajouter un décollage / treuillé", PL_DOMAIN); ?>
                </a>
                <?php echo $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php
                    $class->display();
                    ?>
                </form>
            </div>
            <?php
        }

        public function form_log()
        {
            $class = new Paralog_Log();
            $class->form_edit();
        }

        /**
         * @name list_sites
         */
        public function list_sites()
        {
            $class = new Paralog_Site();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $count = is_array($_REQUEST['id']) ? count($_REQUEST['id']) : 1;
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), $count) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des sites", PL_DOMAIN); ?></h1>
                <a href="<?php echo get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>"
                   class="page-title-action">
                    <span class="fa fa-plus"></span>
                    <?php _e("Ajouter un site", PL_DOMAIN); ?>
                </a>
                <?php echo $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php
                    $class->display();
                    ?>
                </form>
            </div>
            <?php
        }

        public function form_site()
        {
            $class = new Paralog_Site();
            $class->form_edit();
        }

        /**
         * @name list_lines
         */
        public function list_lines()
        {
            $class = new Paralog_Line();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $count = is_array($_REQUEST['id']) ? count($_REQUEST['id']) : 1;
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), $count) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des lignes", PL_DOMAIN); ?></h1>
                <a href="<?php echo get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>"
                   class="page-title-action">
                    <span class="fa fa-plus"></span>
                    <?php _e("Ajouter une ligne", PL_DOMAIN); ?>
                </a>
                <?php echo $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php
                    $class->display();
                    ?>
                </form>
            </div>
            <?php
        }

        public function form_line()
        {
            $class = new Paralog_Line();
            $class->form_edit();
        }

        /**
         * @name list_persons
         */
        public function list_persons()
        {
            $class = new Paralog_Person();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $count = is_array($_REQUEST['id']) ? count($_REQUEST['id']) : 1;
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), $count) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des personnes", PL_DOMAIN); ?></h1>
                <a href="<?php echo get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>"
                   class="page-title-action">
                    <span class="fa fa-plus"></span>
                    <?php _e("Ajouter une personne", PL_DOMAIN); ?>
                </a>
                <?php echo $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php
                    $class->display();
                    ?>
                </form>
            </div>
            <?php
        }

        public function form_person()
        {
            $class = new Paralog_Person();
            $class->form_edit();
        }

        /**
         * @name list_activities
         */
        public function list_activities()
        {
            $class = new Paralog_Activity();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $count = is_array($_REQUEST['id']) ? count($_REQUEST['id']) : 1;
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), $count) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des activités", PL_DOMAIN); ?></h1>
                <a href="<?php echo get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>"
                   class="page-title-action">
                    <span class="fa fa-plus"></span>
                    <?php _e("Ajouter une activité", PL_DOMAIN); ?>
                </a>
                <?php echo $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php
                    $class->display();
                    ?>
                </form>
            </div>
            <?php
        }

        /**
         * @name form_activity
         */
        public function form_activity()
        {
            $class = new Paralog_Activity();
            $class->form_edit();
        }

        /**
         * @name list_attendances
         */
        public function list_attendances()
        {
            $class = new Paralog_Attendance();
            $class->prepare_items();
            if ('delete' === $class->current_action()) {
                $count = is_array($_REQUEST['date']) ? count($_REQUEST['date']) : null;
                $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Élément(s) supprimé(s): %d', PL_DOMAIN), $count) . '</p></div>';
            } else {
                $message = '';
            }
            $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '';
            $paged = isset($_REQUEST['paged']) ? $_REQUEST['paged'] : 1;
            ?>
            <div class="wrap">
                <h1 class="wp-heading-inline"><?php _e("Gestion des présences", PL_DOMAIN); ?></h1>
                <a href="<?php echo get_admin_url(get_current_blog_id(), "admin.php?page=$page-form&paged=$paged"); ?>"
                   class="page-title-action">
                    <span class="fa fa-plus"></span>
                    <?php _e("Ajouter une présence", PL_DOMAIN); ?>
                </a>
                <?php echo $message; ?>
                <form method="post">
                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php
                    $class->display();
                    ?>
                </form>
            </div>
            <?php
        }

        /**
         * @name form_attendance
         */
        public function form_attendance()
        {
            $class = new Paralog_Attendance();
            $class->form_edit();
        }

        /**
         * @name add_options
         */
        public static function add_options()
        {
            $option = 'per_page';
            $args = array(
                'label' => __("nombre d'enregistrements", PL_DOMAIN),
                'default' => 15,
                'option' => "items_$option",
            );

            add_screen_option($option, $args);
        }
    }
}

global $paralog;

$paralog = new Paralog();
