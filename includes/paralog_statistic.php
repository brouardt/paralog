<?php
if (!defined('ABSPATH')) {
    die('No direct access allowed');
}

/**
 * @package Paralog_Statistic
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Statistic
{
    public static function insert_demo_datas()
    {
        global $wpdb;

        $user_id = get_current_user_id();

        $table = Paralog::table_name('sites');
        $query = $wpdb->prepare(
            "INSERT INTO `$table` (`name`, `user_id`, `deleted`) VALUES " .
            "('Mont Bouquet', %d, 0), " .
            "('Aslonnes \"Le Fort\"', %d, 0), " .
            "('Annecy', %d, 0), " .
            "('Massognes / Jarzay', %d, 0);",
            array(
                $user_id,
                $user_id,
                $user_id,
                $user_id,
            )
        );
        $wpdb->query($query);

        $table = Paralog::table_name('lines');
        $query = $wpdb->prepare(
            "INSERT INTO `$table` (`name`, `user_id`, `deleted`) VALUES " .
            "('Déco EST', %d, 0), " .
            "('Déco SUD', %d, 0), " .
            "('Planfait', %d, 0), " .
            "('Montmin', %d, 0), " .
            "('Coche Cabane', %d, 0), " .
            "('Treuil 1B', %d, 0), " .
            "('Treuil 2B - ligne rouge', %d, 0), " .
            "('Treuil 2B - ligne verte', %d, 0), " .
            "('Dévidoir', %d, 0);",
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

        $table = Paralog::table_name('persons');
        $query = $wpdb->prepare(
            "INSERT INTO `$table` (`firstname`, `lastname`, `pilot_type`, `licence`, `winchman`, `winchman_type`, `user_id`, `deleted`) VALUES " .
            "('Thierry', 'BROUARD', %s, '1309710X', %s, %s, %d, 0), " .
            "('Jean-Yves', 'COLLIN', %s, '0700484V', %s, %s, %d, 0), " .
            "('Quentin', 'COURTOIS', %s, '1604781B', %s, NULL, %d, 0), " .
            "('Bernard', 'MAUDET', %s, '0062282X', %s, %s, %d, 0), " .
            "('Carlos', 'MESQUITA', %s, '1302566G', %s, NULL, %d, 0);",
            array(
                __('pilote', PL_DOMAIN),
                __('oui', PL_DOMAIN),
                __('élève', PL_DOMAIN),
                $user_id,
                __('pilote', PL_DOMAIN),
                __('oui', PL_DOMAIN),
                __('treuilleur', PL_DOMAIN),
                $user_id,
                __('pilote', PL_DOMAIN),
                __('non', PL_DOMAIN),
                $user_id,
                __('pilote', PL_DOMAIN),
                __('oui', PL_DOMAIN),
                __('treuilleur', PL_DOMAIN),
                $user_id,
                __('pilote', PL_DOMAIN),
                __('non', PL_DOMAIN),
                $user_id,
            )
        );
        $wpdb->query($query);

        $table = Paralog::table_name('logs');
        $query = $wpdb->prepare(
            "INSERT INTO `$table` (`site_name`, `line_name`, `winchman_name`, `winchman_type`, `pilot_name`, `pilot_type`, `passenger_name`, `total_flying_weight`, `takeoff`, `user_id`, `deleted`) VALUES " .
            "('Annecy', 'Planfait', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2017-10-10 15:06:07', %d, 0), " .
            "('Annecy', 'Montmin', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2017-10-10 15:14:45', %d, 0), " .
            "('Mont Bouquet', 'Déco SUD', NULL, NULL, 'Thierry BROUARD', %s, NULL, 95, '2018-01-10 15:50:21', %d, 0), " .
            "('Mont Bouquet', 'Déco EST', NULL, NULL, 'Bernard MAUDET', %s, NULL, 90, '2017-10-10 15:18:47', %d, 0), " .
            "('Massognes / Jarzay', 'Dévidoir', 'Thierry BROUARD', %s, 'Quentin COURTOIS', %s, NULL, 105, '2018-01-10 15:36:55', %d, 0), " .
            "('Aslonnes \"Le Fort\"', 'Treuil 1B', 'Jean-Yves COLLIN', %s, 'Carlos MESQUITA', %s, NULL, 90, '2018-01-10 15:38:48', %d, 0), " .
            "('Aslonnes \"Le Fort\"', 'Treuil 2B - ligne rouge', 'Bernard MAUDET', %s, 'Thierry BROUARD', %s, NULL, 93, '2018-01-10 15:39:23', %d, 0);",
            array(
                __('pilote', PL_DOMAIN), $user_id,
                __('pilote', PL_DOMAIN), $user_id,
                __('pilote', PL_DOMAIN), $user_id,
                __('pilote', PL_DOMAIN), $user_id,
                __('élève', PL_DOMAIN), __('pilote', PL_DOMAIN), $user_id,
                __('treuilleur', PL_DOMAIN), __('élève', PL_DOMAIN), $user_id,
                __('treuilleur', PL_DOMAIN), __('pilote', PL_DOMAIN), $user_id,
            )
        );
        $wpdb->query($query);

        $table = Paralog::table_name('activities');
        $query = $wpdb->prepare(
            "INSERT INTO `$table` (`activity_id`, `date`, `site_name`, `line_name`, `start_wind_orientation`, `end_wind_orientation`, `start_counter`, `end_counter`, `start_time`, `end_time`, `start_gazoline`, `end_gazoline`, `comment`, `winch_incident`, `fly_incident`, `user_id`, `deleted`) VALUES " .
            "(1, '2018-04-24', 'Aslonnes \"Le Fort\"', 'Treuil 1B', %s, %s, 64120, 64278, '13:25:00', '18:16:00', 45, 33, 'RAZ', '', '', %d, 0);",
            array(
                __('nord-ouest', PL_DOMAIN), __('nord-est', PL_DOMAIN), $user_id
            )
        );
        $wpdb->query($query);

        $table = Paralog::table_name('activities_persons');
        $query = $wpdb->prepare(
            "INSERT INTO `$table` (`activity_id`, `person_type`, `person_name`, `user_id`, `deleted`) VALUES" .
            "(1, %s, 'Thierry PROUST', %d, 0)," .
            "(1, %s, 'Joël GASCHET', %d, 0)," .
            "(1, %s, 'Thierry BROUARD', %d, 0)," .
            "(1, %s, 'Jean-Yves COLLIN', %s, %d, 0);",
            array(
                __('moniteur', PL_DOMAIN), $user_id,
                __('plateforme', PL_DOMAIN), $user_id,
                __('treuilleur', PL_DOMAIN), $user_id,
                __('treuilleur', PL_DOMAIN), $user_id,
            )
        );
        $wpdb->query($query);

        return true;
    }

    /**
     * @name export_csv
     *
     * @param integer $year
     *
     * @global $wpdb
     */
    public static function export_csv($year)
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

        $query = "SHOW COLUMNS FROM `$table` WHERE Field NOT IN ('log_id','user_id','deleted');";
        $columns = $wpdb->get_results($query, ARRAY_A);
        $head = array_column($columns, 'Field');

        fputcsv($out, $head, $separateur, $delimiteur, $echappement);

        $sql = "SELECT " . implode(",", $head) . " FROM `$table` WHERE `deleted` = 0 ";

        if (is_numeric($year)) {
            $sql .= "AND YEAR(`takeoff`) = %d ";
            $query = $wpdb->prepare($sql, $year);
        } else {
            $query = $sql;
        }
        $query .= "ORDER BY `takeoff` DESC;";

        self::my_query($query);

        while (($enregistrement = self::my_fetchrow()) !== false) {
            fputcsv($out, $enregistrement, $separateur, $delimiteur, $echappement);
        }

        $wpdb->flush();
        fclose($out);
        exit;
    }

    /**
     * @name my_query
     *
     * @param string $query
     *
     * @global object $wpdb
     */
    private static function my_query($query)
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
     * @return array
     * @global object $wpdb
     */
    private static function my_fetchrow()
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

    public static function display_stats($param_year)
    {
        $years_list = self::years_takeoff();
        if (empty($years_list)) {
            $demo_data = '1';
            $years = array(
                array(
                    'valeur' => '',
                    'libelle' => __('aucune donnée', PL_DOMAIN)
                )
            );
        } else {
            $demo_data = '0';
            $years = array_merge(
                array(
                    array('valeur' => '', 'libelle' => __('choisissez une année', PL_DOMAIN)),
                    array('valeur' => '*', 'libelle' => __('toutes les années', PL_DOMAIN)),
                ),
                $years_list
            );
        }
        $sites = self::stats_sites($param_year);
        $lines = self::stats_lines($param_year);
        $winchmen = self::stats_winchmen($param_year);
        $pilots = self::stats_pilots($param_year);
        $passengers = self::stats_passengers($param_year);
        ?>
        <div class="wrap">
            <h1><?php _e("À propos de Paralog", PL_DOMAIN); ?></h1>
            <h2><?php _e("Journaux des décollages / treuillés", PL_DOMAIN); ?></h2>
            <h3><?php _e("Version", PL_DOMAIN); ?> [<?php echo PL_VERSION; ?>]</h3>
            <div class="notice inline notice-info">
                <p><?php _e("Cette extension permet à toutes les personnes autorisées, de gérer les journaux des décollages / treuillés d'un ou plusieurs sites de vols. Ce programme a été initialement pensé pour les treuillés en plaine. Cependant, il peut très bien être utilisé sur les sites de décollages de relief.", PL_DOMAIN); ?></p>
                <?php _e("Notions", PL_DOMAIN); ?>
                <ul class="ul-square">
                    <li><?php _e('Le site : correspond au lieu de manière générale <span class="PL_gris_clair">(ex: Aslonnes, Mont Bouquet, Annecy, Samoëns, etc.)</span>', PL_DOMAIN); ?>
                        .
                    </li>
                    <li><?php _e('La ligne : représente la ligne du treuil <span class="PL_gris_clair">(ex: Treuil 1B)</span>. Si celui-ci en possède plusieurs <span class="PL_gris_clair">(ex: Treuil 1B-L1, Treuil 1B-L2)</span> ou si le site possède plusieurs espaces de décollages <span class="PL_gris_clair">(ex: Déco Est, Déco Sud, Planfait, Montmin, Plateau des saix, 1600, La bourgeoise, etc.)</span>', PL_DOMAIN); ?></li>
                </ul>
            </div>
            <form name="statistiques" method="get" action="">
                <input type="hidden" name="page" value="<?php echo PL_ADMIN_SLUG; ?>"/>
                <h2><?php _e("Statistiques", PL_DOMAIN); ?></h2>
                <label><?php _e("Année", PL_DOMAIN); ?> :
                    <select name="annee" onchange="submit();">
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year['valeur']; ?>"<?= ($param_year == $year['valeur'] ? 'selected' : '') ?>><?= $year['libelle']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php
                if ($demo_data == '1') {
                    echo '<p>' . __('aucune donnée à visualiser ?', PL_DOMAIN) . '<button type="submit" name="demo" value="1" class="page-title-action">' . __('ajouter de données de démonstration', PL_DOMAIN) . '</button></p>';
                }
                if (!empty($param_year)) {
                    echo '<p>' . __('exporter les données sélectionnées') . '<button type="submit" name="export" value="1" class="page-title-action">' . __('exporter', PL_DOMAIN) . '</button></p>';
                }
                ?>
                <h3><?php _e("Les sites", PL_DOMAIN); ?></h3>
                <table class="table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php _e("Nom des sites", PL_DOMAIN); ?></th>
                        <th><?php _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td><?= $site['site_name']; ?></td>
                            <td><?= $site['site_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <h3><?php _e("Les lignes", PL_DOMAIN); ?></h3>
                <table class="table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php _e("Nom des lignes", PL_DOMAIN); ?></th>
                        <th><?php _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td><?= $line['line_name']; ?></td>
                            <td><?= $line['line_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <h3><?php _e("Les treuilleurs", PL_DOMAIN); ?></h3>
                <table class="table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php _e("Nom des treuilleurs", PL_DOMAIN); ?></th>
                        <th><?php _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($winchmen as $winchman): ?>
                        <tr>
                            <td><?= $winchman['winchman_name']; ?></td>
                            <td><?= $winchman['winchman_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <h3><?php _e("Les pilotes", PL_DOMAIN); ?></h3>
                <table class="table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php _e("Nom des pilotes", PL_DOMAIN); ?></th>
                        <th><?php _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pilots as $pilot): ?>
                        <tr>
                            <td><?= $pilot['pilot_name']; ?></td>
                            <td><?= $pilot['pilot_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <h3><?php _e("Les passagers", PL_DOMAIN); ?></h3>
                <table class="table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php _e("Nom des sites", PL_DOMAIN); ?></th>
                        <th><?php _e("Quantité", PL_DOMAIN); ?><sup>*</sup></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($passengers as $passenger): ?>
                        <tr>
                            <td><?= $passenger['site_name']; ?></td>
                            <td><?= $passenger['passenger_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <p>*
                <?php _e("Nombre de décollages ou de treuillés", PL_DOMAIN); ?>
            </p>
        </div>
        <?php
    }

    /**
     * @name years_takeoff
     * @return array
     * @global object $wpdb
     */
    private static function years_takeoff()
    {
        global $wpdb;

        $table = Paralog::table_name('logs');

        $query = "SELECT " .
            "YEAR(`takeoff`) AS 'valeur', " .
            "YEAR(`takeoff`) AS 'libelle' " .
            "FROM `$table` " .
            "WHERE `deleted` = 0 " .
            "GROUP BY YEAR(`takeoff`)";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @name stats_sites
     *
     * @param integer $year
     *
     * @return array
     * @global object $wpdb
     */
    private static function stats_sites($year)
    {
        global $wpdb;

        $sites = '';
        $table = Paralog::table_name('logs');

        if (!empty($year)) {
            if (is_numeric($year)) {
                $query = $wpdb->prepare("SELECT " .
                    "`site_name`, " .
                    "COUNT(*) AS 'site_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "AND YEAR(`takeoff`) = %d " .
                    "GROUP BY `site_name`",
                    $year
                );
            } else {
                $query = "SELECT " .
                    "`site_name`, " .
                    "COUNT(*) AS 'site_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "GROUP BY `site_name`";
            }
            $sites = $wpdb->get_results($query, ARRAY_A);
        }
        if (empty($sites)) {
            $sites = array(
                array(
                    'site_name' => __('aucune donnée', PL_DOMAIN),
                    'site_count' => '-'
                )
            );
        }

        return $sites;
    }

    /**
     * @name stats_lines
     *
     * @param integer $year
     *
     * @return array
     * @global object $wpdb
     */
    private static function stats_lines($year)
    {
        global $wpdb;

        $lines = '';
        $table = Paralog::table_name('logs');

        if (!empty($year)) {
            if (is_numeric($year)) {
                $query = $wpdb->prepare("SELECT " .
                    "`line_name`, " .
                    "COUNT(*) AS 'line_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "AND YEAR(`takeoff`) = %d " .
                    "GROUP BY `line_name`",
                    $year
                );
            } else {
                $query = "SELECT " .
                    "`line_name`, " .
                    "COUNT(*) AS 'line_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "GROUP BY `line_name`";
            }
            $lines = $wpdb->get_results($query, ARRAY_A);
        }
        if (empty($lines)) {
            $lines = array(
                array(
                    'line_name' => __('aucune donnée', PL_DOMAIN),
                    'line_count' => '-'
                )
            );
        }

        return $lines;
    }

    /**
     * @name stats_winchmen
     *
     * @param integer $year
     *
     * @return array
     * @global object $wpdb
     */
    private static function stats_winchmen($year)
    {
        global $wpdb;

        $winchmen = '';
        $table = Paralog::table_name('logs');

        if (!empty($year)) {
            if (is_numeric($year)) {
                $query = $wpdb->prepare("SELECT " .
                    "`winchman_name`, " .
                    "COUNT(*) AS 'winchman_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "AND YEAR(takeoff) = %d " .
                    "AND `winchman_name` IS NOT NULL " .
                    "GROUP BY `winchman_name`",
                    $year
                );
            } else {
                $query = "SELECT " .
                    "`winchman_name`, " .
                    "COUNT(*) AS 'winchman_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "AND `winchman_name` IS NOT NULL " .
                    "GROUP BY `winchman_name`";
            }
            $winchmen = $wpdb->get_results($query, ARRAY_A);
        }
        if (empty($winchmen)) {
            $winchmen = array(
                array(
                    'winchman_name' => __('aucune donnée', PL_DOMAIN),
                    'winchman_count' => '-'
                )
            );
        }

        return $winchmen;
    }

    /**
     * @name stats_pilots
     *
     * @param integer $year
     *
     * @return array
     * @global object $wpdb
     */
    private static function stats_pilots($year)
    {
        global $wpdb;

        $pilots = '';
        $table = Paralog::table_name('logs');

        if (!empty($year)) {
            if (is_numeric($year)) {
                $query = $wpdb->prepare("SELECT " .
                    "`pilot_name`, " .
                    "COUNT(*) AS 'pilot_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "AND YEAR(`takeoff`) = %d " .
                    "GROUP BY `pilot_name`",
                    $year
                );
            } else {
                $query = "SELECT " .
                    "`pilot_name`, " .
                    "COUNT(*) AS 'pilot_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "GROUP BY `pilot_name`";
            }
            $pilots = $wpdb->get_results($query, ARRAY_A);
        }
        if (empty($pilots)) {
            $pilots = array(
                array(
                    'pilot_name' => __('aucune donnée', PL_DOMAIN),
                    'pilot_count' => '-'
                )
            );
        }

        return $pilots;
    }

    /**
     * @name stats_passengers
     *
     * @param integer $year
     *
     * @return array
     * @global object $wpdb
     */
    private static function stats_passengers($year)
    {
        global $wpdb;

        $passengers = '';
        $table = Paralog::table_name('logs');

        if (!empty($year)) {
            if (is_numeric($year)) {
                $query = $wpdb->prepare("SELECT " .
                    "`site_name`, " .
                    "COUNT(*) AS 'passenger_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "AND YEAR(`takeoff`) = %d " .
                    "AND `passenger_name` IS NOT NULL " .
                    "GROUP BY `site_name`",
                    $year
                );
            } else {
                $query = "SELECT " .
                    "`site_name`, " .
                    "COUNT(*) AS 'passenger_count' " .
                    "FROM `$table` " .
                    "WHERE `deleted` = 0 " .
                    "AND `passenger_name` IS NOT NULL " .
                    "GROUP BY `site_name`;";
            }
            $passengers = $wpdb->get_results($query, ARRAY_A);
        }
        if (empty($passengers)) {
            $passengers = array(
                array(
                    'site_name' => __('aucune donnée', PL_DOMAIN),
                    'passenger_count' => '-'
                )
            );
        }

        return $passengers;
    }
}