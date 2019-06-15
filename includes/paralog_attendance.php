<?php
if ( ! defined( 'ABSPATH' ) ) {
	wp_die( 'No direct access allowed', 'Security' );
}

/*if ( ! class_exists( 'Paralog_Table' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '/paralog_table.php';
}*/

/**
 * @package Paralog_Attendance
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Attendance extends Paralog_Table
{
	private $date_format = null;

	public function __construct()
	{
		parent::__construct( array(
			'singular' => __( 'présence', PL_DOMAIN ), //singular name of the listed records
			'plural' => __( 'présences', PL_DOMAIN ), //plural name of the listed records
			'ajax' => false, //does this table support ajax?
		) );

		$this->setTable( 'attendances' );
		$this->setPrimary( 'date' );  // ce n'est pas une vraie primary !

		$this->date_format = get_option( 'date_format' );
	}

	public function column_date( $item )
	{
		$actions = array(
			'edit' => sprintf(
				'<a href="?page=%s-form&date=%s">%s</a>',
				$_REQUEST['page'],
				$item['date'],
				__( 'Modifier', PL_DOMAIN )
			)
		);

		return sprintf(
			'%1$s %2$s',
			mysql2date( $this->date_format, $item[ $this->getPrimary() ] ),
			$this->row_actions( $actions )
		);
	}

	public function prepare_items()
	{
		global $wpdb;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$per_page = $this->get_items_per_page( 'items_per_page', 10 );
		$paged = isset( $_REQUEST['paged'] ) ? ( $per_page * max( 0, intval( $_REQUEST['paged'] ) - 1 ) ) : 0;
		$orderby = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? $_REQUEST['orderby'] : 'date';
		$order = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array(
				'asc',
				'desc'
			) ) ) ? $_REQUEST['order'] : 'desc';
		$table = $this->getTable();

		$query = $wpdb->prepare(
			"SELECT " .
			"`date`, " .
			"COUNT(*) AS 'quantity' " .
			"FROM `$table` " .
			"GROUP BY `date` " .
			"ORDER BY `$orderby` $order " .
			"LIMIT %d OFFSET %d",
			array(
				$per_page,
				$paged
			)
		);

		$this->items = $wpdb->get_results( $query, ARRAY_A );

		$total_items = $wpdb->get_var( "SELECT COUNT(DISTINCT(`date`)) FROM `$table`" );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => ceil( $total_items / $per_page ),
			'per_page' => $per_page,
		) );
	}

	public function get_columns()
	{
		if ( current_user_can( 'delete_others_posts' ) ) {
			$columns = array(
				'cb' => '<input type="checkbox" />',
			);
		} else {
			$columns = array();
		}

		$columns = array_merge( $columns, array(
			'date' => __( "Date", PL_DOMAIN ),
			'quantity' => __( "Quantité", PL_DOMAIN )
		) );

		return $columns;
	}

	protected function get_sortable_columns()
	{
		$sortable_columns = array(
			'date' => array( 'date', false )
		);

		return $sortable_columns;
	}

	public function process_bulk_action()
	{
		global $wpdb;

		$table = $this->getTable();
		$primary = $this->getPrimary();

		if ( 'delete' == $this->current_action() ) {
			$dates = isset( $_REQUEST[ $primary ] ) ? $_REQUEST[ $primary ] : array();
			if ( ! empty( $dates ) ) {
				if ( is_array( $dates ) ) {
					$dates = implode( "','", $dates );
				}
				$query = "DELETE FROM `$table` WHERE `$primary` IN('$dates')";
				$wpdb->query( $query );
			}
		}
	}

	public function form_edit()
	{
		global $wpdb;

		$table = $this->getTable();

		$message = '';
		$notice = '';
		$item = array();

		$default = array(
			'date' => null,
			'person_id' => null,
			'attendance' => null
		);

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], basename( __FILE__ ) ) ) {
			// combine our default item with request params
			$request = stripslashes_deep( $_REQUEST );
			$item = shortcode_atts( $default, $request );

			$item_valid = $this->form_validate( $item );

			if ( $item_valid === true ) {
				$result = $wpdb->replace( $table, $item );
				if ( $result !== false ) {
					$message = __( "Présence enregistrée", PL_DOMAIN );
				} else {
					$notice = __( "Un erreur est apparue lors de la sauvegarde", PL_DOMAIN );
				}
			} else {
				// if $item_valid not true it contains error message(s)
				$notice = $item_valid;
			}
		} else {
			$params = array( 'date', 'person_id' );
			foreach ( $params as $param ) {
				if ( isset( $_GET[ $param ] ) ) {
					$item[ $param ] = urldecode( $_GET[ $param ] );
				}
			}
		}
		add_meta_box( 'attendance_form_meta_box', __( 'Présence', PL_DOMAIN ), array(
			$this,
			'attendance_form_meta_box_handler'
		), 'attendance', 'normal', 'default' );
		?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"></div>
            <h1><?php _e( 'Fiche de présence', PL_DOMAIN ); ?>
                <a class="add-new-h2"
                   href="<?php echo get_admin_url(
					   get_current_blog_id(),
					   sprintf(
						   'admin.php?page=paralog-attendances&paged=%d',
						   $this->get_pagenum()
					   ) ); ?>">
					<?php _e( 'retour à la liste', PL_DOMAIN ); ?>
                </a>
            </h1>
			<?php if ( ! empty( $notice ) ): ?>
                <div id="notice" class="error">
                    <p>
						<?php echo $notice; ?>
                    </p>
                </div>
			<?php endif; ?>
			<?php if ( ! empty( $message ) ): ?>
                <div id="message" class="updated">
                    <p>
						<?php echo $message; ?>
                    </p>
                </div>
			<?php endif; ?>
            <form id="form" method="post">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( basename( __FILE__ ) ); ?>"/>
                <div class="metabox-holder" id="postsite">
                    <div id="post-body">
                        <div id="post-body-content">
							<?php do_meta_boxes( 'attendance', 'normal', $item ); ?>
                            <button type="submit" name="submit" value="save" class="button button-primary">
                                <span class="fa fa-save"></span>
								<?php _e( 'Enregistrer', PL_DOMAIN ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
			<?php $this->view_attendances( $item['date'] ); ?>
        </div>
		<?php
	}

	private function form_validate( $item )
	{
		$messages = array();

		if ( empty( $item['date'] ) ) {
			$messages[] = __( 'La date est obligatoire', PL_DOMAIN );
		}
		if ( empty( $item['person_id'] ) ) {
			$messages[] = __( 'Le nom du pilote est obligatoire', PL_DOMAIN );
		}
		if ( empty( $item['attendance'] ) ) {
			$messages[] = __( 'Le choix de présence est obligatoire', PL_DOMAIN );
		}

		if ( empty( $messages ) ) {
			return true;
		}

		return implode( '<br />', $messages );
	}

	public function view_attendances( $date )
	{
		$types = array(
			__( 'oui', PL_DOMAIN ),
			__( 'peut-être', PL_DOMAIN ),
			__( 'non', PL_DOMAIN ),
			__( 'ne se prononce pas encore', PL_DOMAIN ),
			__( 'non abonné', PL_DOMAIN ),
		);
		foreach ( $types as $type ):
			$pilots = $this->pilots_by_date_status( $date, $type );
			$winchmen = $this->winchmen_by_date_status( $date, $type );
			?>
            <h3><?php _e( $type, PL_DOMAIN ); ?></span></h3>
            <table class="table widefat fixed striped">
                <thead>
                <tr>
                    <th>
						<?php _e( 'Pilotes', PL_DOMAIN ); ?> (<?php echo count( $pilots ); ?>)
                    </th>
                    <th>
						<?php _e( 'Treuilleurs', PL_DOMAIN ); ?> (<?php echo count( $winchmen ); ?>)
                    </th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
						<?php foreach ( $pilots as $pilot ): ?>
							<?php echo $pilot['name']; ?>
                            (<span style="color:<?php echo $this->color_person( $pilot['type'] ); ?>">
                            <?php echo $pilot['type']; ?>
                        </span>)<br/>
						<?php endforeach; ?>
                    </td>
                    <td>
						<?php foreach ( $winchmen as $winchman ): ?>
							<?php echo $winchman['name']; ?>
                            (<span style="color:<?php echo $this->color_person( $winchman['type'] ); ?>">
                            <?php echo $winchman['type']; ?>
                        </span>)<br/>
						<?php endforeach; ?>
                    </td>
                </tr>
                </tbody>
            </table>
		<?php
		endforeach;
	}

	protected function pilots_by_date_status( $date, $status )
	{
		global $wpdb;

		$table_attendance = Paralog::table_name( 'attendances' );
		$table_person = Paralog::table_name( 'persons' );
		if ( $status == __( 'ne se prononce pas encore', PL_DOMAIN ) ) {
			$query = $wpdb->prepare(
				"SELECT " .
				"CONCAT_WS(' ', `tp`.`firstname`, `tp`.`lastname`) AS 'name', " .
				"`tp`.`pilot_type` AS 'type' " .
				"FROM `$table_person` AS tp " .
				"WHERE `tp`.`raise` = 1 " .
				"AND NOT EXISTS " .
				"( " .
				"SELECT `ta`.`person_id` " .
				"FROM `$table_attendance` AS `ta` " .
				"WHERE `ta`.`date` = %s " .
				"AND `tp`.`person_id` = `ta`.`person_id` " .
				") " .
				"ORDER BY `tp`.`lastname` ASC, `tp`.`firstname` ASC",
				$date
			);
		} else if ( $status == __( 'non abonné', PL_DOMAIN ) ) {
			$query = $wpdb->prepare(
				"SELECT " .
				"CONCAT_WS(' ', `tp`.`firstname`, `tp`.`lastname`) AS 'name', " .
				"`tp`.`pilot_type` AS 'type' " .
				"FROM `$table_person` AS tp " .
				"WHERE `tp`.`raise` <> 1 " .
				"AND NOT EXISTS " .
				"( " .
				"SELECT `ta`.`person_id` " .
				"FROM `$table_attendance` AS `ta` " .
				"WHERE `ta`.`date` = %s " .
				"AND `tp`.`person_id` = `ta`.`person_id` " .
				") " .
				"ORDER BY `tp`.`lastname` ASC, `tp`.`firstname` ASC",
				$date
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT " .
				"CONCAT_WS(' ', `tp`.`firstname`, `tp`.`lastname`) AS 'name', " .
				"`tp`.`pilot_type` AS 'type' " .
				"FROM " .
				"`$table_attendance` AS ta, " .
				"`$table_person` AS tp " .
				"WHERE " .
				"`ta`.`date` = %s " .
				"AND `ta`.`attendance` = %s " .
				"AND `ta`.`person_id` = `tp`.`person_id` " .
				"ORDER BY `tp`.`lastname` ASC, `tp`.`firstname` ASC",
				array(
					$date,
					$status
				)
			);
		}

//        echo $query;
		return $wpdb->get_results( $query, ARRAY_A );
	}

	protected function winchmen_by_date_status( $date, $status )
	{
		global $wpdb;

		$table_attendance = Paralog::table_name( 'attendances' );
		$table_person = Paralog::table_name( 'persons' );
		if ( $status == __( 'ne se prononce pas encore', PL_DOMAIN ) ) {
			$query = $wpdb->prepare(
				"SELECT " .
				"CONCAT_WS(' ', `tp`.`firstname`, `tp`.`lastname`) AS 'name', " .
				"`tp`.`winchman_type` AS 'type' " .
				"FROM `$table_person` AS tp " .
				"WHERE `tp`.`winchman` LIKE %s " .
				"AND `tp`.`raise` = 1 " .
				"AND NOT EXISTS " .
				"( " .
				"SELECT `ta`.`person_id` " .
				"FROM `$table_attendance` AS `ta` " .
				"WHERE `ta`.`date` = %s " .
				"AND `tp`.`person_id` = `ta`.`person_id` " .
				") " .
				"ORDER BY `tp`.`lastname` ASC, `tp`.`firstname` ASC",
				array(
					__( 'oui', PL_DOMAIN ),
					$date
				)
			);
		} else if ( $status == __( 'non abonné', PL_DOMAIN ) ) {
			$query = $wpdb->prepare(
				"SELECT " .
				"CONCAT_WS(' ', `tp`.`firstname`, `tp`.`lastname`) AS 'name', " .
				"`tp`.`winchman_type` AS 'type' " .
				"FROM `$table_person` AS tp " .
				"WHERE `tp`.`winchman` LIKE %s " .
				"AND `tp`.`raise` <> 1 " .
				"AND NOT EXISTS " .
				"( " .
				"SELECT `ta`.`person_id` " .
				"FROM `$table_attendance` AS `ta` " .
				"WHERE `ta`.`date` = %s " .
				"AND `tp`.`person_id` = `ta`.`person_id` " .
				") " .
				"ORDER BY `tp`.`lastname` ASC, `tp`.`firstname` ASC",
				array(
					__( 'oui', PL_DOMAIN ),
					$date
				)
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT " .
				"CONCAT_WS(' ', `tp`.`firstname`, `tp`.`lastname`) AS 'name', " .
				"`tp`.`winchman_type` AS 'type' " .
				"FROM " .
				"`$table_attendance` AS ta, " .
				"`$table_person` AS tp " .
				"WHERE " .
				"`ta`.`date` = %s " .
				"AND `ta`.`attendance` = %s " .
				"AND `ta`.`person_id` = `tp`.`person_id` " .
				"AND `tp`.`winchman` LIKE %s " .
				"ORDER BY `tp`.`lastname` ASC, `tp`.`firstname` ASC",
				array(
					$date,
					$status,
					__( 'oui', PL_DOMAIN )
				)
			);
		}

//        echo $query;
		return $wpdb->get_results( $query, ARRAY_A );
	}

	public function attendance_form_meta_box_handler( $item )
	{
		$oui = __( "oui", PL_DOMAIN );
		$non = __( "non", PL_DOMAIN );
		$peutetre = __( "peut-être", PL_DOMAIN );

		$pilots = $this->pilot_name_items();
		?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="date">
						<?php _e( "Date", PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <input id="date" name="date" type="date" value="<?php echo esc_attr( $item['date'] ); ?>"
                           class="code" required/>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="person_id">
						<?php _e( "Nom", PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <select id="person_id" name="person_id" required>
                        <option value=""></option>
						<?php foreach ( $pilots as $pilot ): ?>
                            <option value="<?php echo $pilot['person_id']; ?>"
								<?php echo( $pilot['person_id'] == $item['person_id'] ? 'selected' : '' ); ?>>
								<?php echo esc_html( $pilot['name'] ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="attendance">
						<?php _e( "Serez-vous présent ?", PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <div id="attendance">
                        <input type="radio" name="attendance" value="<?php echo $oui; ?>"
							<?php echo( $oui === $item['attendance'] ? 'checked' : '' ); ?>
                               required/> <?php echo $oui; ?>
                        <input type="radio" name="attendance" value="<?php echo $peutetre; ?>"
							<?php echo( $peutetre === $item['attendance'] ? 'checked' : '' ); ?>
                               required/> <?php echo $peutetre; ?>
                        <input type="radio" name="attendance" value="<?php echo $non; ?>"
							<?php echo( $non === $item['attendance'] ? 'checked' : '' ); ?>
                               required/> <?php echo $non; ?>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	/**
	 * @return String
	 */
	protected function column_cb( $item )
	{
		return sprintf( '<input type="checkbox" name="date[]" value="%s" />', $item['when'] );
	}

	protected function column_default( $item, $column_name )
	{
		switch ( $column_name ) {
			case 'date':
				return mysql2date( $this->date_format, $item[ $column_name ] );
			case 'quantity':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

}
