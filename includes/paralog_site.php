<?php
if ( ! defined( 'ABSPATH' ) ) {
	wp_die( 'No direct access allowed', 'Security' );
}

if ( ! class_exists( 'Paralog_Table' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '/paralog_table.php';
}

/**
 * @package Paralog_Site
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Site extends Paralog_Table
{
	public function __construct()
	{
		parent::__construct( array(
			'singular' => __( 'site', PL_DOMAIN ), //singular name of the listed records
			'plural' => __( 'sites', PL_DOMAIN ), //plural name of the listed records
			'ajax' => false, //does this table support ajax?
		) );

		$this->setTable( 'sites' );
		$this->setPrimary( 'site_id' );
	}

	public function column_name( $item )
	{
		$actions = array();
		$user_id = get_current_user_id();
		$primary = $this->getPrimary();

		if ( current_user_can( 'edit_others_posts' ) || ( $item['user_id'] == $user_id ) ) {
			$actions = array_merge( $actions, array(
				'edit' => sprintf(
					'<a href="?page=%s-form&id=%d">%s</a>',
					$_REQUEST['page'],
					$item[ $primary ],
					__( 'Modifier', PL_DOMAIN )
				)
			) );
		}
		if ( current_user_can( 'delete_others_posts' ) || ( $item['user_id'] == $user_id ) ) {
			$actions = array_merge( $actions, array(
				'delete' => sprintf(
					'<a href="?page=%s&action=delete&id=%d">%s</a>',
					$_REQUEST['page'],
					$item[ $primary ],
					__( 'Supprimer', PL_DOMAIN )
				)
			) );
		}

		return sprintf(
			'%1$s %2$s',
			$item['name'],
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

		$per_page = $this->get_items_per_page( 'items_per_page', 5 );
		$paged = isset( $_REQUEST['paged'] ) ? ( $per_page * max( 0, intval( $_REQUEST['paged'] ) - 1 ) ) : 0;
		$orderby = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? $_REQUEST['orderby'] : 'name';
		$order = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array(
				'asc',
				'desc'
			) ) ) ? $_REQUEST['order'] : 'asc';

		$table = $this->getTable();

		$query = $wpdb->prepare( "SELECT " .
		                         "`site_id`, " .
		                         "`name`, " .
		                         "`user_id` " .
		                         "FROM `$table` " .
		                         "WHERE `deleted` = 0 " .
		                         "ORDER BY `$orderby` $order " .
		                         "LIMIT %d OFFSET %d",
			$per_page,
			$paged
		);
		$this->items = $wpdb->get_results( $query, ARRAY_A );

		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE `deleted` = 0" );

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
			'name' => __( "Nom", PL_DOMAIN ),
		) );

		return $columns;
	}

	protected function get_sortable_columns()
	{
		$sortable_columns = array(
			'name' => array( 'name', false ),
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
			'site_id' => 0,
			'name' => '',
			'message' => null,
			'user_id' => get_current_user_id()
		);

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], basename( __FILE__ ) ) ) {
			// combine our default item with request params
			$request = stripslashes_deep( $_REQUEST );
			$item = shortcode_atts( $default, $request );

			$item_valid = $this->form_validate( $item );

			if ( $item_valid === true ) {
				if ( $item[ $primary ] == 0 ) {
					$result = $wpdb->insert( $table, $item );
					$item[ $primary ] = $wpdb->insert_id;
					if ( $result !== false ) {
						$message = __( "Site enregistré", PL_DOMAIN );
					} else {
						$notice = __( "Un erreur est apparue lors de la sauvegarde", PL_DOMAIN );
					}
				} else {
					$result = $wpdb->update( $table, $item, array( $primary => $item[ $primary ] ) );
					if ( $result !== false ) {
						$message = __( "Site mis à jour", PL_DOMAIN );
					} else {
						$notice = __( "Un erreur est apparue lors de la mise à jour", PL_DOMAIN );
					}
				}
			} else {
				// if $item_valid not true it contains error message(s)
				$notice = $item_valid;
			}
		} else {
			// if this is not post back we load item to edit or give new one to create
			$item = $default;
			if ( isset( $_REQUEST['id'] ) ) {
				$item = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `$table` WHERE `$primary` = %d",
						$_REQUEST['id']
					),
					ARRAY_A
				);
				if ( ! $item ) {
					$item = $default;
					$notice = __( 'Donnée introuvable', PL_DOMAIN );
				}
			}
		}
		add_meta_box( 'site_form_meta_box', __( 'Site', PL_DOMAIN ), array(
			$this,
			'site_form_meta_box_handler'
		), 'site', 'normal', 'default' );
		?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"></div>
            <h1><?php _e( 'Fiche de site', PL_DOMAIN ); ?>
                <a class="add-new-h2"
                   href="<?php echo get_admin_url(
					   get_current_blog_id(),
					   sprintf(
						   'admin.php?page=paralog-sites&paged=%d',
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
                <input type="hidden" name="<?php echo $primary; ?>"
                       value="<?php echo esc_attr( $item[ $primary ] ); ?>"/>
                <div class="metabox-holder" id="postsite">
                    <div id="post-body">
                        <div id="post-body-content">
							<?php do_meta_boxes( 'site', 'normal', $item ); ?>
                            <button type="submit" name="submit" value="save" class="button button-primary">
                                <span class="fa fa-save"></span>
								<?php _e( 'Enregistrer', PL_DOMAIN ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
		<?php
	}

	private function form_validate( $item )
	{
		$messages = array();

		if ( empty( $item['name'] ) ) {
			$messages[] = __( 'Le nom du site st obligatoire', PL_DOMAIN );
		}

		if ( empty( $messages ) ) {
			return true;
		}

		return implode( '<br />', $messages );
	}

	public function site_form_meta_box_handler( $item )
	{
		?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="name">
						<?php _e( 'Nom du site', PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <input id="name" name="name" type="text" style="width: 95%"
                           value="<?php echo esc_attr( $item['name'] ); ?>"
                           size="50" maxlength="64" class="code"
                           placeholder="<?php _e( 'ex: annecy', PL_DOMAIN ); ?>"
                           required>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="message">
						<?php _e( 'Message du site', PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <textarea name="message"
                              placeholder="<?php _e( 'Prevenir le propriétaire', PL_DOMAIN ); ?>"
                              class="code"><?php echo esc_html( $item['message'] ); ?></textarea>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	protected function column_default( $item, $column_name )
	{
		switch ( $column_name ) {
			case 'name':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}
}
