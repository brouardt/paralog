<?php
if ( ! defined( 'ABSPATH' ) ) {
	wp_die( 'No direct access allowed', 'Security' );
}

/**
 * @package Paralog_Options
 *
 * @author Thierry Brouard <thierry@brouard.pro>
 */
class Paralog_Options
{
	const RAISE = 'paralog_raise';
	const RECURRENCE = 'weekly';


	/**
	 * @param $schedules
	 * @return array
	 */
	public static function add_weekly_to_cron( $schedules )
	{
		if ( ! isset( $schedules[ self::RECURRENCE ] ) ) {
			$schedules[ self::RECURRENCE ] = array(
				'interval' => 3600 * 24 * 7, // 3600s = 1h x 24h x 7j
				'display' => __( 'Une fois par semaine', PL_DOMAIN )
			);
		}

		return $schedules;
	}

	public function form_edit()
	{
		$message = '';
		$notice = '';

		$default = array(
			'activity' => 1,
			'raise_day' => 'Friday',
			'raise_time' => '12:00 UTC',
			'raise_subject' => '',
			'raise_message' => '',
		);

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], basename( __FILE__ ) ) ) {
			// combine our default item with request params
			$request = stripslashes_deep( $_REQUEST );
			$item = shortcode_atts( $default, $request );

			$item_valid = $this->form_validate( $item );

			if ( $item_valid === true ) {
				if ( $_REQUEST['submit'] === 'save' ) {
					$options = get_option( PL_DOMAIN );
					foreach ( array_keys( $default ) as $key ) {
						$options[ $key ] = $item[ $key ];
					}
					update_option( PL_DOMAIN, $options, 'no' );
					self::remove_cron();
					self::add_cron();
					$message = __( 'Paramètres mis à jour', PL_DOMAIN );
				}
				if ( $_REQUEST['submit'] === 'send' ) {
					$date = filter_input( INPUT_POST, 'raise_date' );
					$count = self::raise_pilots( $date );
					if ( $count ) {
						$message = "$count " . __( 'rappel(s) envoyé(s)', PL_DOMAIN );
					} else {
						$notice = __( "Un problème empeche l'envoi de l'e-mail", PL_DOMAIN );
					}
				}
			} else {
				// if $item_valid not true it contains error message(s)
				$notice = $item_valid;
			}
		} else {
			// if this is not post back we load item to edit or give new one to create
			$item = $default;
			$options = get_option( PL_DOMAIN );
			foreach ( array_keys( $default ) as $key ) {
				$item[ $key ] = $options[ $key ];
			}
		}
		add_meta_box( 'options_form_meta_box', __( 'Paramètre', PL_DOMAIN ), array(
			$this,
			'options_form_meta_box_handler',
		), 'options', 'normal', 'default' );
		$date_raise = new DateTime( 'tomorrow' );
		$date_raise_min = $date_raise->format( 'Y-m-d' );
		?>
        <div class="wrap">
            <div class="icon32 icon32-posts-post" id="icon-edit"></div>
            <h1><?php _e( "Réglages", PL_DOMAIN ); ?></h1>
            <div class="notice notice-info is-dismissible">
				<?php _e( "Vous pouvez utiliser des variables pour un traitement dynamique de l'information dans le sujet ou le message de votre e-mail.", PL_DOMAIN ); ?>
                <ul class="ul-square">
                    <li>%DATE_ATTENDANCE% = <?php _e( "la date du lendemain.", PL_DOMAIN ); ?></li>
                    <li>%FORM_ATTENDANCE%
                        = <?php _e( "lien vers le formulaire de saisie pré-rempli.", PL_DOMAIN ); ?></li>
                    <li>%UNSUBSCRIBE_ATTENDANCE%
                        = <?php _e( "lien vers la fiche pilote pour modification de paramètres.", PL_DOMAIN ); ?></li>
                </ul>
            </div>
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
                <div class="metabox-holder" id="postoptions">
                    <div id="post-body">
                        <div id="post-body-content">
							<?php do_meta_boxes( "options", 'normal', $item ); ?>
                            <button type="submit" name="submit" value="save" class="button button-primary">
                                <span class="fa fa-save"></span>
								<?php _e( 'Enregistrer', PL_DOMAIN ); ?>
                            </button>
                            <button type="submit" name="submit" value="send" class="button button-secondary">
                                <span class="fa fa-send"></span>
								<?php _e( 'Envoyer un rappel pour le ', PL_DOMAIN ); ?>
                            </button>
                            <input type="date" name="raise_date" value="<?php echo esc_attr( $date_raise_min ); ?>"
                                   min="<?php echo esc_attr( $date_raise_min ); ?>" />
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

		if( $item['activity'] == '' ) {
            $messages[] = __( "Le rappel est à définir", PL_DOMAIN );
        }

		if ( empty( $messages ) ) {
			return true;
		}

		return implode( '<br />', $messages );
	}

	public static function remove_cron()
	{
		$timestamp = wp_next_scheduled( self::RAISE );
		wp_unschedule_event( $timestamp, self::RAISE );
	}

	public static function add_cron()
	{
		$options = get_option( PL_DOMAIN );

		if ( isset( $options['raise_day'] ) && $options['raise_day'] != '' ) {
			if ( ! wp_next_scheduled( self::RAISE ) ) {

				$raise = 'next ' . $options['raise_day'] .
				         ( isset( $options['raise_time'] ) ? $options['raise_time'] : '12:00 UTC' );
				$timestamp = strtotime( $raise );
				wp_schedule_event( $timestamp, self::RECURRENCE, self::RAISE );
			}
			add_action( self::RAISE, array( __CLASS__, 'raise_pilots' ) );
		}
	}

	public static function raise_pilots( $date = 'tomorrow' )
	{
		global $wpdb;

		$options = get_option( PL_DOMAIN );

		if ( $options['activity'] == 1 ) {
			$table = Paralog::table_name( 'persons' );
			$query = "SELECT " .
			         "`person_id`, " .
			         "`firstname`, " .
			         "`lastname`, " .
			         "`email` " .
			         "FROM `$table` " .
			         "WHERE `deleted` = 0 " .
			         "AND `raise` = 1";
			$recipients = $wpdb->get_results( $query );

			$date = new DateTime( $date, new DateTimeZone( get_option( 'timezone_string' ) ) );
			$date_attendance = $date->format( get_option( 'date_format' ) );

			$count = 0;
			foreach ( $recipients as $recipient ) {
				$to = ltrim( "{$recipient->firstname} {$recipient->lastname} <{$recipient->email}>" );

				$form_attendance = get_admin_url(
					get_current_blog_id(),
					sprintf(
						'admin.php?page=paralog-attendances-form&date=%s&person_id=%d',
						$date->format( 'Y-m-d' ),
						$recipient->person_id
					)
				);

				$unsubscribe_attendance = get_admin_url(
					get_current_blog_id(),
					sprintf(
						'admin.php?page=paralog-persons-form&id=%d',
						$recipient->person_id
					)
				);

				$subject = str_ireplace(
					array( '%DATE_ATTENDANCE%', '%FORM_ATTENDANCE%', '%UNSUBSCRIBE_ATTENDANCE%' ),
					array( $date_attendance, $form_attendance, $unsubscribe_attendance ),
					$options['raise_subject']
				);

				$message = str_ireplace(
					array( '%DATE_ATTENDANCE%', '%FORM_ATTENDANCE%', '%UNSUBSCRIBE_ATTENDANCE%' ),
					array( $date_attendance, $form_attendance, $unsubscribe_attendance ),
					$options['raise_message']
				);
				/*
				 * envoi du mail
				 */
				wp_mail( $to, $subject, $message );
				$count ++;
			}

			return $count;
		}

		return false;
	}

	public function options_form_meta_box_handler( $item )
	{
		$days = array(
			'' => __( 'Aucun', PL_DOMAIN ),
			'Monday' => __( 'Lundi', PL_DOMAIN ),
			'Tuesday' => __( 'Mardi', PL_DOMAIN ),
			'Wednesday' => __( 'Mercredi', PL_DOMAIN ),
			'Thursday' => __( 'Jeudi', PL_DOMAIN ),
			'Friday' => __( 'Vendredi', PL_DOMAIN ),
			'Saturday' => __( 'Samedi', PL_DOMAIN ),
			'Sunday' => __( 'Dimanche', PL_DOMAIN ),
		);
		$times = array(
			'00:00 UTC' => __( "00:00 UTC", PL_DOMAIN ),
			'01:00 UTC' => __( "01:00 UTC", PL_DOMAIN ),
			'02:00 UTC' => __( "02:00 UTC", PL_DOMAIN ),
			'03:00 UTC' => __( "03:00 UTC", PL_DOMAIN ),
			'04:00 UTC' => __( "04:00 UTC", PL_DOMAIN ),
			'05:00 UTC' => __( "05:00 UTC", PL_DOMAIN ),
			'06:00 UTC' => __( "06:00 UTC", PL_DOMAIN ),
			'07:00 UTC' => __( "07:00 UTC", PL_DOMAIN ),
			'08:00 UTC' => __( "08:00 UTC", PL_DOMAIN ),
			'09:00 UTC' => __( "09:00 UTC", PL_DOMAIN ),
			'10:00 UTC' => __( "10:00 UTC", PL_DOMAIN ),
			'11:00 UTC' => __( "11:00 UTC", PL_DOMAIN ),
			'12:00 UTC' => __( "12:00 UTC", PL_DOMAIN ),
			'13:00 UTC' => __( "13:00 UTC", PL_DOMAIN ),
			'14:00 UTC' => __( "14:00 UTC", PL_DOMAIN ),
			'15:00 UTC' => __( "15:00 UTC", PL_DOMAIN ),
			'16:00 UTC' => __( "16:00 UTC", PL_DOMAIN ),
			'17:00 UTC' => __( "17:00 UTC", PL_DOMAIN ),
			'18:00 UTC' => __( "18:00 UTC", PL_DOMAIN ),
			'19:00 UTC' => __( "19:00 UTC", PL_DOMAIN ),
			'20:00 UTC' => __( "20:00 UTC", PL_DOMAIN ),
			'21:00 UTC' => __( "21:00 UTC", PL_DOMAIN ),
			'22:00 UTC' => __( "22:00 UTC", PL_DOMAIN ),
			'23:00 UTC' => __( "23:00 UTC", PL_DOMAIN )
		);
		?>
        <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
            <tbody>
            <tr>
                <th valign="top" scope="row">
                    <label>
			            <?php _e( "Rappel de l'activité", PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <input id="activity_1" name="activity" type="radio"
                           value="1" <?php echo ($item['activity'] == 1 ? 'selected': ''); ?> class="code" />
                    <label for="activity_1">
                        <?php _e('Oui', PL_DOMAIN); ?>
                    </label>
                    <input id="activity_0" name="activity" type="radio"
                           value="0" <?php echo ($item['activity'] == 0 ? 'selected': ''); ?> class="code" />
                    <label for="activity_0">
                        <?php _e('Non', PL_DOMAIN); ?>
                    </label>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="raise_day">
						<?php _e( "Jour du rappel", PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <select id="raise_day" name="raise_day">
						<?php foreach ( $days as $key => $value ): ?>
                            <option value="<?php echo esc_attr( $key ); ?>"
								<?php echo( $key == $item['raise_day'] ? 'selected' : '' ); ?>>
								<?php echo esc_html( $value ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="raise_time">
						<?php _e( "Heure du rappel", PL_DOMAIN ) ?>
                    </label>
                </th>
                <td>
                    <select id="raise_time" name="raise_time">
						<?php foreach ( $times as $key => $value ): ?>
                            <option value="<?php echo esc_attr( $key ); ?>"
								<?php echo( $key == $item['raise_time'] ? 'selected' : '' ); ?>>
								<?php echo esc_html( $value ); ?>
                            </option>
						<?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="raise_subject">
						<?php _e( 'Sujet de rappel', PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <input type="text" name="raise_subject"
                           value="<?php echo esc_html( $item['raise_subject'] ); ?>" class="code"/>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="raise_message">
						<?php _e( 'Message de rappel', PL_DOMAIN ); ?>
                    </label>
                </th>
                <td>
                    <textarea name="raise_message" class="code"
                              rows="5"><?php echo esc_html( $item['raise_message'] ); ?></textarea>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
	}
}
