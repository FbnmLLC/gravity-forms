<?php
/**
 * Plugin Name: 123PAY.IR - Gravity Forms
 * Description: پلاگین پرداخت، سامانه پرداخت یک دو سه پی برای Gravity Forms
 * Plugin URI: https://123pay.ir
 * Author: تیم فنی یک دو سه پی
 * Author URI: http://123pay.ir
 * Version: 1.0
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( __FILE__, array( "GFIr123pay_FBNM", "add_permissions" ) );
add_action( 'init', array( 'GFIr123pay_FBNM', 'init' ) );

require_once( 'functions.php' );
require_once( 'database.php' );
require_once( 'chart.php' );

class GFIr123pay_FBNM {

	public static $author = "FBNM";

	private static $version = "2.2.2";
	private static $min_gravityforms_version = "1.9.10";
	private static $config = null;

	public static function init() {
		if ( ! class_exists( "GFParsi" ) ) {
			add_action( 'admin_notices', array( 'GFIr123pay_FBNM', 'admin_notice_persian_gf' ) );

			return false;
		}

		if ( ! self::is_gravityforms_supported() ) {
			add_action( 'admin_notices', array( 'GFIr123pay_FBNM', 'admin_notice_gf_support' ) );

			return false;
		}

		add_filter( 'members_get_capabilities', array( "GFIr123pay_FBNM", "members_get_capabilities" ) );

		if ( is_admin() && self::has_access() ) {

			add_filter( 'gform_tooltips', array( 'GFIr123pay_FBNM', 'tooltips' ) );
			add_filter( 'gform_addon_navigation', array( 'GFIr123pay_FBNM', 'menu' ) );
			add_action( 'gform_entry_info', array( 'GFIr123pay_FBNM', 'payment_entry_detail' ), 4, 2 );
			add_action( 'gform_after_update_entry', array( 'GFIr123pay_FBNM', 'update_payment_entry' ), 4, 2 );

			if ( get_option( "gf_ir123pay_configured" ) ) {
				add_filter( 'gform_form_settings_menu', array( 'GFIr123pay_FBNM', 'toolbar' ), 10, 2 );
				add_action( 'gform_form_settings_page_ir123pay', array(
					'GFIr123pay_FBNM',
					'ir123pay_form_settings_page'
				) );
			}

			if ( rgget( "page" ) == "gf_settings" ) {
				RGForms::add_settings_page( array(
						'name'      => 'gf_ir123pay',
						'tab_label' => __( 'درگاه یک دو سه پی', 'gravityformsir123pay' ),
						'title'     => __( 'تنظیمات درگاه یک دو سه پی', 'gravityformsir123pay' ),
						'handler'   => array( 'GFIr123pay_FBNM', 'settings_page' ),
					)
				);
			}

			if ( self::is_ir123pay_page() ) {
				wp_enqueue_script( array( "sack" ) );
				self::setup();
			}

			if ( in_array( RG_CURRENT_PAGE, array( "admin-ajax.php" ) ) ) {
				add_action( 'wp_ajax_gf_ir123pay_update_feed_active', array(
					'GFIr123pay_FBNM',
					'update_feed_active'
				) );
			}
		}
		if ( get_option( "gf_ir123pay_configured" ) ) {
			add_filter( "gform_pre_render", array( "GFIr123pay_FBNM", "change_price" ), 10, 1 );
			add_filter( "gform_confirmation", array( "GFIr123pay_FBNM", "Request" ), 1000, 4 );
			add_filter( "gform_disable_notification", array( "GFIr123pay_FBNM", "delay_notifications" ), 10, 4 );
			add_filter( "gform_disable_registration", array( "GFIr123pay_FBNM", "delay_registration" ), 10, 4 );
			add_filter( "gform_disable_post_creation", array( "GFIr123pay_FBNM", "delay_posts" ), 10, 3 );
			add_filter( "gform_is_delayed_pre_process_feed", array( "GFIr123pay_FBNM", "delay_addons" ), 10, 4 );

			add_action( 'wp', array( 'GFIr123pay_FBNM', 'Verify' ), 5 );
		}

		add_filter( "gform_logging_supported", array( "GFIr123pay_FBNM", "set_logging_supported" ) );

		add_filter( 'gf_payment_gateways', array( "GFIr123pay_FBNM", 'gravityformsir123pay' ), 2 );
		do_action( 'gravityforms_gateways' );
		do_action( 'gravityforms_ir123pay' );
	}

	public static function admin_notice_persian_gf() {
		$class   = 'notice notice-error';
		$message = sprintf( __( "برای استفاده از درگاه های پرداخت گرویتی فرم نصب بسته فارسی ساز الزامی است . برای نصب فارسی ساز %sکلیک کنید%s.", "gravityformsir123pay" ), '<a href="' . admin_url( "plugin-install.php?tab=plugin-information&plugin=persian-gravity-forms&TB_iframe=true&width=772&height=884" ) . '">', '</a>' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}

	public static function admin_notice_gf_support() {
		$class   = 'notice notice-error';
		$message = sprintf( __( "درگاه یک دو سه پی نیاز به گرویتی فرم نسخه %s به بالا دارد . برای بروز رسانی هسته گرویتی فرم به %sسایت گرویتی فرم فارسی%s مراجعه نمایید .", "gravityformsir123pay" ), self::$min_gravityforms_version, "<a href='http://gravityforms.ir/11378' target='_blank'>", "</a>" );
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}

	public static function gravityformsir123pay( $form, $lead ) {
		$ir123pay = array(
			'class' => ( __CLASS__ . '|' . self::$author ),
			'title' => __( 'یک دو سه پی', 'gravityformsir123pay' ),
			'param' => array(
				'name'  => __( 'نام', 'gravityformsir123pay' ),
				'email' => __( 'ایمیل', 'gravityformsir123pay' ),
				'desc'  => __( 'توضیحات', 'gravityformsir123pay' )
			)
		);

		return apply_filters( self::$author . '_gf_ir123pay_detail', apply_filters( self::$author . '_gf_gateway_detail', $ir123pay, $form, $lead ), $form, $lead );
	}

	public static function add_permissions() {
		global $wp_roles;
		$editable_roles = get_editable_roles();
		foreach ( (array) $editable_roles as $role => $details ) {
			if ( $role == 'administrator' || in_array( 'gravityforms_edit_forms', $details['capabilities'] ) ) {
				$wp_roles->add_cap( $role, 'gravityforms_ir123pay' );
				$wp_roles->add_cap( $role, 'gravityforms_ir123pay_uninstall' );
			}
		}
	}

	public static function members_get_capabilities( $caps ) {
		return array_merge( $caps, array( "gravityforms_ir123pay", "gravityforms_ir123pay_uninstall" ) );
	}

	private static function setup() {
		if ( get_option( "gf_ir123pay_version" ) != self::$version ) {
			GFIr123payData::update_table();
		}
		update_option( "gf_ir123pay_version", self::$version );
	}

	public static function tooltips( $tooltips ) {
		$tooltips["gateway_name"] = __( "تذکر مهم : این قسمت برای نمایش به بازدید کننده می باشد و لطفا جهت جلوگیری از مشکل و تداخل آن را فقط یکبار تنظیم نمایید و از تنظیم مکرر آن خود داری نمایید .", "gravityformsir123pay" );

		return $tooltips;
	}

	public static function menu( $menus ) {
		$permission = "gravityforms_ir123pay";
		if ( ! empty( $permission ) ) {
			$menus[] = array(
				"name"       => "gf_ir123pay",
				"label"      => __( "یک دو سه پی", "gravityformsir123pay" ),
				"callback"   => array( "GFIr123pay_FBNM", "ir123pay_page" ),
				"permission" => $permission
			);
		}

		return $menus;
	}

	public static function toolbar( $menu_items ) {
		$menu_items[] = array(
			'name'  => 'ir123pay',
			'label' => __( 'یک دو سه پی', 'gravityformsir123pay' )
		);

		return $menu_items;
	}

	private static function is_gravityforms_supported() {
		if ( class_exists( "GFCommon" ) ) {
			$is_correct_version = version_compare( GFCommon::$version, self::$min_gravityforms_version, ">=" );

			return $is_correct_version;
		} else {
			return false;
		}
	}

	protected static function has_access( $required_permission = 'gravityforms_ir123pay' ) {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		return GFCommon::current_user_can_any( $required_permission );
	}

	protected static function get_base_url() {
		return plugins_url( null, __FILE__ );
	}

	protected static function get_base_path() {
		$folder = basename( dirname( __FILE__ ) );

		return WP_PLUGIN_DIR . "/" . $folder;
	}

	public static function set_logging_supported( $plugins ) {
		$plugins[ basename( dirname( __FILE__ ) ) ] = "Ir123pay";

		return $plugins;
	}

	public static function uninstall() {
		if ( ! self::has_access( "gravityforms_ir123pay_uninstall" ) ) {
			die( __( "شما مجوز کافی برای این کار را ندارید . سطح دسترسی شما پایین تر از حد مجاز است . ", "gravityformsir123pay" ) );
		}
		GFIr123payData::drop_tables();
		delete_option( "gf_ir123pay_settings" );
		delete_option( "gf_ir123pay_configured" );
		delete_option( "gf_ir123pay_version" );
		$plugin = basename( dirname( __FILE__ ) ) . "/index.php";
		deactivate_plugins( $plugin );
		update_option( 'recently_activated', array( $plugin => time() ) + (array) get_option( 'recently_activated' ) );
	}

	private static function is_ir123pay_page() {
		$current_page    = in_array( trim( strtolower( rgget( "page" ) ) ), array( 'gf_ir123pay', 'ir123pay' ) );
		$current_view    = in_array( trim( strtolower( rgget( "view" ) ) ), array( 'gf_ir123pay', 'ir123pay' ) );
		$current_subview = in_array( trim( strtolower( rgget( "subview" ) ) ), array( 'gf_ir123pay', 'ir123pay' ) );

		return $current_page || $current_view || $current_subview;
	}

	public static function ir123pay_form_settings_page() {
		GFFormSettings::page_header(); ?>
        <h3>
			<span><i class="fa fa-credit-card"></i> <?php esc_html_e( 'یک دو سه پی', 'gravityformsir123pay' ) ?>
                <a id="add-new-confirmation" class="add-new-h2"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=gf_ir123pay&view=edit&fid=' . absint( rgget( "id" ) ) ) ) ?>"><?php esc_html_e( 'افزودن فید جدید', 'gravityformsir123pay' ) ?></a></span>
            <a class="add-new-h2"
               href="admin.php?page=gf_ir123pay&view=stats&id=<?php echo absint( rgget( "id" ) ) ?>"><?php _e( "نمودار ها", "gravityformsir123pay" ) ?></a>
        </h3>
		<?php self::list_page( 'per-form' ); ?>
		<?php GFFormSettings::page_footer();
	}

	public static function has_ir123pay_condition( $form, $config ) {

		if ( empty( $config["meta"] ) ) {
			return false;
		}

		$config   = $config["meta"];
		$field    = '';
		$operator = isset( $config["ir123pay_conditional_operator"] ) ? $config["ir123pay_conditional_operator"] : "";
		if ( ! empty( $config["ir123pay_conditional_field_id"] ) ) {
			$field = RGFormsModel::get_field( $form, $config["ir123pay_conditional_field_id"] );
		}

		if ( empty( $field ) || empty( $config["ir123pay_conditional_enabled"] ) ) {
			return true;
		}

		$is_visible     = ! RGFormsModel::is_field_hidden( $form, $field, array() );
		$field_value    = RGFormsModel::get_field_value( $field, array() );
		$is_value_match = RGFormsModel::is_value_match( $field_value, $config["ir123pay_conditional_value"], $operator );

		return $is_value_match && $is_visible;
	}

	public static function get_config_by_entry( $entry ) {
		$feed_id = gform_get_meta( $entry["id"], "ir123pay_feed_id" );
		$feed    = ! empty( $feed_id ) ? GFIr123payData::get_feed( $feed_id ) : '';
		$return  = ! empty( $feed ) ? $feed : false;

		return apply_filters( self::$author . '_gf_ir123pay_get_config_by_entry', apply_filters( self::$author . '_gf_gateway_get_config_by_entry', $return, $entry ), $entry );
	}

	public static function delay_posts( $is_disabled, $form, $lead ) {

		$config = self::get_active_config( $form );

		if ( ! empty( $config ) && is_array( $config ) && $config ) {
			return true;
		}

		return $is_disabled;
	}

	public static function delay_notifications( $is_disabled, $notification, $form, $lead ) {

		$config = self::get_active_config( $form );

		if ( ! empty( $config ) && is_array( $config ) && $config ) {
			return true;
		}

		return $is_disabled;
	}

	public static function delay_addons( $is_delayed, $form, $entry, $slug ) {

		$config = self::get_active_config( $form );

		if ( ! empty( $config ) && is_array( $config ) && $config ) {

			if ( $slug != 'gravityformsuserregistration' && isset( $config["meta"] ) && isset( $config["meta"]["addon"] ) && $config["meta"]["addon"] == 'true' ) {

				$fulfilled = gform_get_meta( $entry['id'], $slug . '_is_fulfilled' );
				$processed = gform_get_meta( $entry['id'], 'processed_feeds' );

				return empty( $fulfilled ) && rgempty( $slug, $processed );
			}


			if ( $slug == 'gravityformsuserregistration' && isset( $config["meta"] ) && isset( $config["meta"]["type"] ) && $config["meta"]["type"] == "subscription" ) {

				$fulfilled = gform_get_meta( $entry['id'], $slug . '_is_fulfilled' );
				$processed = gform_get_meta( $entry['id'], 'processed_feeds' );

				return empty( $fulfilled ) && rgempty( $slug, $processed );
			}

		}

		return $is_delayed;
	}

	public static function delay_registration( $is_disabled, $form, $entry, $fulfilled = '' ) {

		$config = self::get_active_config( $form );

		if ( ! empty( $config ) && is_array( $config ) && $config ) {

			if ( isset( $config["meta"] ) && isset( $config["meta"]["type"] ) && $config["meta"]["type"] == "subscription" && apply_filters( 'gform_disable_registration_', true ) ) {

				if ( ! class_exists( 'GF_User_Registration' ) && class_exists( "GFUser" ) ) {

					$config         = GFUser::get_active_config( $form, $entry );
					$is_update_feed = rgars( $config, 'meta/feed_type' ) == 'update';
					$user_data      = GFUser::get_user_data( $entry, $form, $config, $is_update_feed );
					if ( ! empty( $user_data['password'] ) ) {
						gform_update_meta( $entry['id'], 'userregistration_password', GFUser::encrypt( $user_data['password'] ) );
					}

				}

				return true;
			}
		}

		return $is_disabled;
	}

	public static function Creat_User( $form, $lead ) {

		add_filter( "gform_disable_registration_", '__return_false' );

		if ( ! class_exists( 'GF_User_Registration' ) && class_exists( "GFUser" ) ) {

			GFUser::log_debug( "form #{$form['id']} - starting gf_create_user()." );
			global $wpdb;

			if ( rgar( $lead, 'status' ) == 'spam' ) {
				GFUser::log_debug( 'gf_create_user(): aborting. Entry is marked as spam.' );

				return;
			}

			$config         = GFUser::get_active_config( $form, $lead );
			$is_update_feed = rgars( $config, 'meta/feed_type' ) == 'update';

			if ( ! $config || ! $config['is_active'] ) {
				GFUser::log_debug( 'gf_create_user(): aborting. No feed or feed is inactive.' );

				return;
			}

			$user_data = GFUser::get_user_data( $lead, $form, $config, $is_update_feed );
			if ( ! $user_data ) {
				GFUser::log_debug( 'gf_create_user(): aborting. user_login or user_email are empty.' );

				return;
			}

			$password = gform_get_meta( $lead['id'], 'userregistration_password' );
			if ( $password ) {
				$password = GFUser::decrypt( $password );
				gform_delete_meta( $lead['id'], 'userregistration_password' );
			} else {
				$password = '';
			}

			$user_activation = rgars( $config, 'meta/user_activation' );
			if ( ! $is_update_feed && $user_activation ) {

				require_once( GFUser::get_base_path() . '/includes/signups.php' );
				GFUserSignups::prep_signups_functionality();
				$meta = array(
					'lead_id'    => $lead['id'],
					'user_login' => $user_data['user_login'],
					'email'      => $user_data['user_email'],
					'password'   => GFUser::encrypt( $password ),
				);

				$meta       = apply_filters( 'gform_user_registration_signup_meta', $meta, $form, $lead, $config );
				$meta       = apply_filters( "gform_user_registration_signup_meta_{$form['id']}", $meta, $form, $lead, $config );
				$ms_options = rgars( $config, 'meta/multisite_options' );

				if ( is_multisite() && rgar( $ms_options, 'create_site' ) && $site_data = GFUser::get_site_data( $lead, $form, $config ) ) {
					wpmu_signup_blog( $site_data['domain'], $site_data['path'], $site_data['title'], $user_data['user_login'], $user_data['user_email'], $meta );
				} else {
					$user_data['user_login'] = preg_replace( '/\s+/', '', sanitize_user( $user_data['user_login'], true ) );
					GFUser::log_debug( "Calling wpmu_signup_user (sends email with activation link) with login: " . $user_data['user_login'] . " email: " . $user_data['user_email'] . " meta: " . print_r( $meta, true ) );
					wpmu_signup_user( $user_data['user_login'], $user_data['user_email'], $meta );
					GFUser::log_debug( "Done with wpmu_signup_user" );
				}

				$activation_key = $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM $wpdb->signups WHERE user_login = %s ORDER BY registered DESC LIMIT 1", $user_data['user_login'] ) );

				GFUserSignups::add_signup_meta( $lead['id'], $activation_key );

				return;
			}

			if ( $is_update_feed ) {
				GFUser::update_user( $lead, $form, $config );
			} else {
				if ( ! $user_activation ) {
					GFUser::log_debug( "in gf_create_user - calling create_user" );
					GFUser::create_user( $lead, $form, $config, $password );
				}
			}
		}

	}

	public static function send_notification( $event, $form, $lead, $status = 'submit', $config ) {

		if ( empty( $config ) || ! is_array( $config ) ) {
			return false;
		}

		switch ( strtolower( $status ) ) {

			case 'submit':
				$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_1"] ) ? $config["meta"]["gf_ir123pay_notif_1"] : array();
				break;

			case 'completed':
				$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_2"] ) ? $config["meta"]["gf_ir123pay_notif_2"] : array();
				break;

			case 'failed':
				$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_3"] ) ? $config["meta"]["gf_ir123pay_notif_3"] : array();
				break;

			case 'cancelled':
				$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_4"] ) ? $config["meta"]["gf_ir123pay_notif_4"] : array();
				break;
		}

		$notifications         = GFCommon::get_notifications_to_send( $event, $form, $lead );
		$notifications_to_send = array();

		foreach ( $notifications as $notification ) {
			if ( in_array( $notification['id'], $selected_notifications ) && apply_filters( 'gf_ir123pay_send_notification', apply_filters( 'gf_gateway_send_notification', true, $notification, $selected_notifications, $event, $form, $lead, $status ), $notification, $selected_notifications, $event, $form, $lead, $status ) ) {
				$notifications_to_send[] = $notification['id'];
			}
		}

		GFCommon::send_notifications( $notifications_to_send, $form, $lead, true, $event );
	}

	public static function get_confirmation( $form, $lead = null, $event = '', $status = '', $config ) {

		if ( empty( $config ) || ! is_array( $config ) ) {
			return false;
		}

		if ( ! class_exists( "GFFormDisplay" ) ) {
			require_once( GFCommon::get_base_path() . "/form_display.php" );
		}

		switch ( strtolower( $status ) ) {

			case 'completed':
				$selected_confirmations = ! empty( $config["meta"]["gf_ir123pay_conf_1"] ) ? $config["meta"]["gf_ir123pay_conf_1"] : array();
				break;

			case 'failed':
				$selected_confirmations = ! empty( $config["meta"]["gf_ir123pay_conf_2"] ) ? $config["meta"]["gf_ir123pay_conf_2"] : array();
				break;

			case 'cancelled':
				$selected_confirmations = ! empty( $config["meta"]["gf_ir123pay_conf_3"] ) ? $config["meta"]["gf_ir123pay_conf_3"] : array();
				break;
		}

		if ( ! is_array( rgar( $form, 'confirmations' ) ) ) {
			return $form;
		}

		if ( ! empty( $event ) ) {
			$confirmations = wp_filter_object_list( $form['confirmations'], array( 'event' => $event ) );
		} else {
			$confirmations = $form['confirmations'];
		}

		if ( is_array( $form['confirmations'] ) && count( $confirmations ) <= 1 ) {
			$form['confirmation'] = reset( $confirmations );

			return $form;
		}

		if ( empty( $lead ) ) {
			//
		}

		foreach ( $confirmations as $confirmation ) {

			if ( rgar( $confirmation, 'event' ) != $event ) {
				continue;
			}

			if ( rgar( $confirmation, 'isDefault' ) ) {
				continue;
			}

			if ( isset( $confirmation['isActive'] ) && ! $confirmation['isActive'] ) {
				continue;
			}

			$logic = rgar( $confirmation, 'conditionalLogic' );
			if ( GFCommon::evaluate_conditional_logic( $logic, $form, $lead ) ) {

				if ( in_array( rgar( $confirmation, 'id' ), $selected_confirmations ) && apply_filters( 'gf_ir123pay_send_confirmation', apply_filters( 'gf_gateway_send_confirmation', true, $confirmation, $selected_confirmations, $event, $form, $lead, $status ), $confirmation, $selected_confirmations, $event, $form, $lead, $status ) ) {
					$form['confirmation'] = $confirmation;

					return $form;
				}
			}
		}

		$filtered_list        = wp_filter_object_list( $form['confirmations'], array( 'isDefault' => true ) );
		$form['confirmation'] = reset( $filtered_list );

		return $form;
	}

	public static function confirmation( $form, $lead = null, $event = '', $status = '', $fault = '', $config ) {

		if ( ! class_exists( "GFFormDisplay" ) ) {
			require_once( GFCommon::get_base_path() . "/form_display.php" );
		}

		$form = self::get_confirmation( $form, $lead, $event, $status, $config );

		if ( empty( $form ) || ! $form ) {
			return false;
		}

		$ajax = false;

		if ( ! empty( $form['confirmation']['type'] ) && $form['confirmation']['type'] == 'message' ) {
			$default_anchor = 0;
			$anchor         = gf_apply_filters( 'gform_confirmation_anchor', $form['id'], $default_anchor ) ? "<a id='gf_{$form['id']}' name='gf_{$form['id']}' class='gform_anchor' ></a>" : '';
			$nl2br          = rgar( $form['confirmation'], 'disableAutoformat' ) ? false : true;
			$cssClass       = rgar( $form, 'cssClass' );
			$confirmation   = empty( $form['confirmation']['message'] ) ? "{$anchor} " : "{$anchor}<div id='gform_confirmation_wrapper_{$form['id']}' class='gform_confirmation_wrapper {$cssClass}'><div id='gform_confirmation_message_{$form['id']}' class='gform_confirmation_message_{$form['id']} gform_confirmation_message'>" . GFCommon::replace_variables( $form['confirmation']['message'], $form, $lead, false, true, $nl2br ) . '</div></div>';
		} else {
			if ( ! empty( $form['confirmation']['pageId'] ) ) {
				$url = get_permalink( $form['confirmation']['pageId'] );
			} else {
				$url = GFCommon::replace_variables( trim( $form['confirmation']['url'] ), $form, $lead, false, true, true, 'text' );
			}

			$url_info      = parse_url( $url );
			$query_string  = rgar( $url_info, 'query' );
			$dynamic_query = GFCommon::replace_variables( trim( $form['confirmation']['queryString'] ), $form, $lead, true, false, false, 'text' );
			$dynamic_query = str_replace( array( "\r", "\n" ), '', $dynamic_query );
			$query_string  .= rgempty( 'query', $url_info ) || empty( $dynamic_query ) ? $dynamic_query : '&' . $dynamic_query;

			if ( ! empty( $url_info['fragment'] ) ) {
				$query_string .= '#' . rgar( $url_info, 'fragment' );
			}

			$url = isset( $url_info['scheme'] ) ? $url_info['scheme'] : 'http';
			$url .= '://' . rgar( $url_info, 'host' );
			if ( ! empty( $url_info['port'] ) ) {
				$url .= ':' . rgar( $url_info, 'port' );
			}

			$url .= rgar( $url_info, 'path' );
			if ( ! empty( $query_string ) ) {
				$url .= "?{$query_string}";
			}

			if ( headers_sent() || $ajax ) {
				$confirmation = self::get_js_redirect_confirmation( $url, $ajax );
			} else {
				$confirmation = array( 'redirect' => $url );
			}
		}

		$confirmation = gf_apply_filters( 'gform_confirmation', $form['id'], $confirmation, $form, $lead, $ajax );

		if ( ! is_array( $confirmation ) ) {
			$confirmation = GFCommon::gform_do_shortcode( $confirmation );
		} else if ( headers_sent() || $ajax ) {
			$confirmation = self::get_js_redirect_confirmation( $confirmation['redirect'], $ajax );
		}

		GFCommon::log_debug( 'GFFormDisplay::handle_confirmation(): Confirmation => ' . print_r( $confirmation, true ) );

		if ( is_array( $confirmation ) && isset( $confirmation["redirect"] ) ) {
			header( "Location: {$confirmation["redirect"]}" );
			exit;
		}
		$confirmation                             = str_ireplace( '{fault}', $fault, $confirmation );
		GFFormDisplay::$submission[ $form['id'] ] = array(
			"is_confirmation"      => true,
			"confirmation_message" => $confirmation,
			"form"                 => $form,
			"lead"                 => $lead
		);
	}

	public static function get_js_redirect_confirmation( $url, $ajax ) {
		$confirmation = "<script type=\"text/javascript\">" . apply_filters( 'gform_cdata_open', '' ) . " function gformRedirect(){document.location.href='$url';}";
		if ( ! $ajax ) {
			$confirmation .= 'gformRedirect();';
		}
		$confirmation .= apply_filters( 'gform_cdata_close', '' ) . '</script>';

		return $confirmation;
	}

	private static function redirect_confirmation( $url, $ajax ) {

		if ( headers_sent() || $ajax ) {
			if ( is_callable( array( 'GFFormDisplay', 'get_js_redirect_confirmation' ) ) ) {
				$confirmation = GFFormDisplay::get_js_redirect_confirmation( $url, $ajax );
			} else {
				$confirmation = self::get_js_redirect_confirmation( $url, $ajax );
			}
		} else {
			$confirmation = array( 'redirect' => $url );
		}

		return $confirmation;
	}

	public static function change_price( $form ) {

		$config = self::get_active_config( $form );
		if ( empty( $config ) || ! is_array( $config ) ) {
			return $form;
		}

		$shaparak = ! empty( $GLOBALS['shaparak'] ) ? $GLOBALS['shaparak'] : '';

		if ( empty( $shaparak ) ) {

			$currency = GFCommon::get_currency();
			if ( $currency == 'IRR' || $currency == 'IRT' ) {

				$GLOBALS['shaparak'] = 'apply';

				$max      = $currency == 'IRR' ? 1000 : 100;
				$show_max = ! empty( $config["meta"]["shaparak"] ) && $config["meta"]["shaparak"] == "sadt";
				?>
                <script type="text/javascript">gform.addFilter('gform_product_total', function (total, formId) {
                        if (total < <?php echo $max ?> && total > 0) {
                            total = <?php echo $show_max ? $max : 0 ?>;
                        }
                        return total;
                    });
                </script>
				<?php
			}
		}

		return $form;
	}

	public static function get_active_config( $form ) {

		if ( ! empty( self::$config ) ) {
			return self::$config;
		}

		$configs = GFIr123payData::get_feed_by_form( $form["id"], true );

		$configs = apply_filters( self::$author . '_gf_ir123pay_get_active_configs', apply_filters( self::$author . '_gf_gateway_get_active_configs', $configs, $form ), $form );

		$return = false;

		if ( ! empty( $configs ) && is_array( $configs ) ) {

			foreach ( $configs as $config ) {
				if ( self::has_ir123pay_condition( $form, $config ) ) {
					$return = $config;
				}
				break;
			}
		}

		self::$config = apply_filters( self::$author . '_gf_ir123pay_get_active_config', apply_filters( self::$author . '_gf_gateway_get_active_config', $return, $form ), $form );

		return self::$config;
	}

	public static function ir123pay_page() {
		$view = rgget( "view" );
		if ( $view == "edit" ) {
			self::config_page();
		} else if ( $view == "stats" ) {
			Ir123pay_Chart::stats_page();
		} else {
			self::list_page( '' );
		}
	}

	private static function list_page( $arg ) {

		if ( ! self::is_gravityforms_supported() ) {
			die( sprintf( __( "درگاه یک دو سه پی نیاز به گرویتی فرم نسخه %s دارد . برای بروز رسانی هسته گرویتی فرم به %sسایت گرویتی فرم فارسی%s مراجعه نمایید .", "gravityformsir123pay" ), self::$min_gravityforms_version, "<a href='http://gravityforms.ir/11378' target='_blank'>", "</a>" ) );
		}

		if ( rgpost( 'action' ) == "delete" ) {
			check_admin_referer( "list_action", "gf_ir123pay_list" );
			$id = absint( rgpost( "action_argument" ) );
			GFIr123payData::delete_feed( $id );
			?>
            <div class="updated fade" style="padding:6px"><?php _e( "فید حذف شد", "gravityformsir123pay" ) ?></div><?php
		} else if ( ! empty( $_POST["bulk_action"] ) ) {

			check_admin_referer( "list_action", "gf_ir123pay_list" );
			$selected_feeds = rgpost( "feed" );
			if ( is_array( $selected_feeds ) ) {
				foreach ( $selected_feeds as $feed_id ) {
					GFIr123payData::delete_feed( $feed_id );
				}
			}

			?>
            <div class="updated fade" style="padding:6px"><?php _e( "فید ها حذف شدند", "gravityformsir123pay" ) ?></div>
			<?php
		}
		?>
        <div class="wrap">

			<?php if ( $arg != 'per-form' ) { ?>

                <h2>
					<?php _e( "فرم های یک دو سه پی", "gravityformsir123pay" );
					if ( get_option( "gf_ir123pay_configured" ) ) { ?>
                        <a class="add-new-h2"
                           href="admin.php?page=gf_ir123pay&view=edit"><?php _e( "افزودن جدید", "gravityformsir123pay" ) ?></a>
						<?php
					} ?>
                </h2>

			<?php } ?>

            <form id="confirmation_list_form" method="post">
				<?php wp_nonce_field( 'list_action', 'gf_ir123pay_list' ) ?>
                <input type="hidden" id="action" name="action"/>
                <input type="hidden" id="action_argument" name="action_argument"/>
                <div class="tablenav">
                    <div class="alignleft actions" style="padding:8px 0 7px 0;">
                        <label class="hidden"
                               for="bulk_action"><?php _e( "اقدام دسته جمعی", "gravityformsir123pay" ) ?></label>
                        <select name="bulk_action" id="bulk_action">
                            <option value=''> <?php _e( "اقدامات دسته جمعی", "gravityformsir123pay" ) ?> </option>
                            <option value='delete'><?php _e( "حذف", "gravityformsir123pay" ) ?></option>
                        </select>
						<?php
						echo '<input type="submit" class="button" value="' . __( "اعمال", "gravityformsir123pay" ) . '" onclick="if( jQuery(\'#bulk_action\').val() == \'delete\' && !confirm(\'' . __( "فید حذف شود ؟ ", "gravityformsir123pay" ) . __( "\'Cancel\' برای منصرف شدن, \'OK\' برای حذف کردن", "gravityformsir123pay" ) . '\')) { return false; } return true;"/>';
						?>
                        <a class="button button-primary"
                           href="admin.php?page=gf_settings&subview=gf_ir123pay"><?php _e( 'تنظیمات حساب یک دو سه پی', 'gravityformsir123pay' ) ?></a>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped toplevel_page_gf_edit_forms" cellspacing="0">
                    <thead>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column"
                            style="padding:13px 3px;width:30px"><input type="checkbox"/></th>
                        <th scope="col" id="active" class="manage-column"
                            style="width:<?php echo $arg != 'per-form' ? '50px' : '20px' ?>"><?php echo $arg != 'per-form' ? __( 'وضعیت', 'gravityformsir123pay' ) : '' ?></th>
                        <th scope="col" class="manage-column"
                            style="width:<?php echo $arg != 'per-form' ? '65px' : '30%' ?>"><?php _e( " آیدی فید", "gravityformsir123pay" ) ?></th>
						<?php if ( $arg != 'per-form' ) { ?>
                            <th scope="col"
                                class="manage-column"><?php _e( "فرم متصل به درگاه", "gravityformsir123pay" ) ?></th>
						<?php } ?>
                        <th scope="col" class="manage-column"><?php _e( "نوع تراکنش", "gravityformsir123pay" ) ?></th>
                    </tr>
                    </thead>
                    <tfoot>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column" style="padding:13px 3px;">
                            <input type="checkbox"/></th>
                        <th scope="col" id="active"
                            class="manage-column"><?php echo $arg != 'per-form' ? __( 'وضعیت', 'gravityformsir123pay' ) : '' ?></th>
                        <th scope="col" class="manage-column"><?php _e( "آیدی فید", "gravityformsir123pay" ) ?></th>
						<?php if ( $arg != 'per-form' ) { ?>
                            <th scope="col"
                                class="manage-column"><?php _e( "فرم متصل به درگاه", "gravityformsir123pay" ) ?></th>
						<?php } ?>
                        <th scope="col" class="manage-column"><?php _e( "نوع تراکنش", "gravityformsir123pay" ) ?></th>
                    </tr>
                    </tfoot>
                    <tbody class="list:user user-list">
					<?php
					$currency = GFCommon::get_currency();

					if ( $arg != 'per-form' ) {
						$settings = GFIr123payData::get_feeds();
					} else {
						$settings = GFIr123payData::get_feed_by_form( rgget( 'id' ), false );
					}

					if ( ! get_option( "gf_ir123pay_configured" ) ) {
						?>
                        <td colspan="5" style="padding:20px;">
							<?php echo sprintf( __( "برای شروع باید درگاه را فعال نمایید . به %sتنظیمات یک دو سه پی%s بروید . ", "gravityformsir123pay" ), '<a href="admin.php?page=gf_settings&subview=gf_ir123pay">', "</a>" ); ?>
                        </td>
                        </tr>
						<?php
					} else if ( $currency != 'IRR' && $currency != 'IRT' ) { ?>
                        <tr>
                            <td colspan="5" style="padding:20px;">
								<?php echo sprintf( __( "برای استفاده از این درگاه باید واحد پول را بر روی « تومان » یا « ریال ایران » تنظیم کنید . %sبرای تنظیم واحد پول کلیک نمایید%s . ", "gravityformsir123pay" ), '<a href="admin.php?page=gf_settings">', "</a>" ); ?>
                            </td>
                        </tr>
					<?php } else if ( is_array( $settings ) && sizeof( $settings ) > 0 ) {
						foreach ( $settings as $setting ) {
							?>
                            <tr class='author-self status-inherit' valign="top">

                                <th scope="row" class="check-column"><input type="checkbox" name="feed[]"
                                                                            value="<?php echo $setting["id"] ?>"/></th>

                                <td><img style="cursor:pointer;width:25px"
                                         src="<?php echo esc_url( GFCommon::get_base_url() ) ?>/images/active<?php echo intval( $setting["is_active"] ) ?>.png"
                                         alt="<?php echo $setting["is_active"] ? __( "درگاه فعال است", "gravityformsir123pay" ) : __( "درگاه غیر فعال است", "gravityformsir123pay" ); ?>"
                                         title="<?php echo $setting["is_active"] ? __( "درگاه فعال است", "gravityformsir123pay" ) : __( "درگاه غیر فعال است", "gravityformsir123pay" ); ?>"
                                         onclick="ToggleActive(this, <?php echo $setting['id'] ?>); "/></td>

                                <td><?php echo $setting["id"] ?>
									<?php if ( $arg == 'per-form' ) { ?>
                                        <div class="row-actions">
                                                <span class="edit">
                                                    <a title="<?php _e( "ویرایش فید", "gravityformsir123pay" ) ?>"
                                                       href="admin.php?page=gf_ir123pay&view=edit&id=<?php echo $setting["id"] ?>"><?php _e( "ویرایش فید", "gravityformsir123pay" ) ?></a>
                                                    |
                                                </span>
                                            <span class="trash">
                                                    <a title="<?php _e( "حذف", "gravityformsir123pay" ) ?>"
                                                       href="javascript: if(confirm('<?php _e( "فید حذف شود؟ ", "gravityformsir123pay" ) ?> <?php _e( "\'Cancel\' برای انصراف, \'OK\' برای حذف کردن.", "gravityformsir123pay" ) ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e( "حذف", "gravityformsir123pay" ) ?></a>
                                                </span>
                                        </div>
									<?php } ?>
                                </td>

								<?php if ( $arg != 'per-form' ) { ?>
                                    <td class="column-title">
                                        <strong><a class="row-title"
                                                   href="admin.php?page=gf_ir123pay&view=edit&id=<?php echo $setting["id"] ?>"
                                                   title="<?php _e( "تنظیم مجدد درگاه", "gravityformsir123pay" ) ?>"><?php echo $setting["form_title"] ?></a></strong>

                                        <div class="row-actions">
                                            <span class="edit">
                                                <a title="<?php _e( "ویرایش فید", "gravityformsir123pay" ) ?>"
                                                   href="admin.php?page=gf_ir123pay&view=edit&id=<?php echo $setting["id"] ?>"><?php _e( "ویرایش فید", "gravityformsir123pay" ) ?></a>
                                                |
                                            </span>
                                            <span class="trash">
                                                <a title="<?php _e( "حذف فید", "gravityformsir123pay" ) ?>"
                                                   href="javascript: if(confirm('<?php _e( "فید حذف شود؟ ", "gravityformsir123pay" ) ?> <?php _e( "\'Cancel\' برای انصراف, \'OK\' برای حذف کردن.", "gravityformsir123pay" ) ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e( "حذف", "gravityformsir123pay" ) ?></a>
                                                |
                                            </span>
                                            <span class="view">
                                                <a title="<?php _e( "ویرایش فرم", "gravityformsir123pay" ) ?>"
                                                   href="admin.php?page=gf_edit_forms&id=<?php echo $setting["form_id"] ?>"><?php _e( "ویرایش فرم", "gravityformsir123pay" ) ?></a>
                                                |
                                            </span>
                                            <span class="view">
                                                <a title="<?php _e( "مشاهده صندوق ورودی", "gravityformsir123pay" ) ?>"
                                                   href="admin.php?page=gf_entries&view=entries&id=<?php echo $setting["form_id"] ?>"><?php _e( "صندوق ورودی", "gravityformsir123pay" ) ?></a>
                                                |
                                            </span>
                                            <span class="view">
                                                <a title="<?php _e( "نمودارهای فرم", "gravityformsir123pay" ) ?>"
                                                   href="admin.php?page=gf_ir123pay&view=stats&id=<?php echo $setting["form_id"] ?>"><?php _e( "نمودارهای فرم", "gravityformsir123pay" ) ?></a>
                                            </span>
                                        </div>
                                    </td>
								<?php } ?>


                                <td class="column-date">
									<?php
									if ( isset( $setting["meta"]["type"] ) && $setting["meta"]["type"] == 'subscription' ) {
										_e( "عضویت", "gravityformsir123pay" );
									} else {
										_e( "محصول معمولی یا فرم ارسال پست", "gravityformsir123pay" );
									}
									?>
                                </td>
                            </tr>
							<?php
						}
					} else {
						?>
                        <tr>
                            <td colspan="5" style="padding:20px;">
								<?php
								if ( $arg == 'per-form' ) {
									echo sprintf( __( "شما هیچ فید یک دو سه پی‌ای ندارید . %sیکی بسازید%s .", "gravityformsir123pay" ), '<a href="admin.php?page=gf_ir123pay&view=edit&fid=' . absint( rgget( "id" ) ) . '">', "</a>" );
								} else {
									echo sprintf( __( "شما هیچ فید یک دو سه پی‌ای ندارید . %sیکی بسازید%s .", "gravityformsir123pay" ), '<a href="admin.php?page=gf_ir123pay&view=edit">', "</a>" );
								}
								?>
                            </td>
                        </tr>
						<?php
					}
					?>
                    </tbody>
                </table>
            </form>
        </div>
        <script type="text/javascript">
            function DeleteSetting(id) {
                jQuery("#action_argument").val(id);
                jQuery("#action").val("delete");
                jQuery("#confirmation_list_form")[0].submit();
            }

            function ToggleActive(img, feed_id) {
                var is_active = img.src.indexOf("active1.png") >= 0;
                if (is_active) {
                    img.src = img.src.replace("active1.png", "active0.png");
                    jQuery(img).attr('title', '<?php _e( "درگاه غیر فعال است", "gravityformsir123pay" ) ?>').attr('alt', '<?php _e( "درگاه غیر فعال است", "gravityformsir123pay" ) ?>');
                }
                else {
                    img.src = img.src.replace("active0.png", "active1.png");
                    jQuery(img).attr('title', '<?php _e( "درگاه فعال است", "gravityformsir123pay" ) ?>').attr('alt', '<?php _e( "درگاه فعال است", "gravityformsir123pay" ) ?>');
                }
                var mysack = new sack(ajaxurl);
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar("action", "gf_ir123pay_update_feed_active");
                mysack.setVar("gf_ir123pay_update_feed_active", "<?php echo wp_create_nonce( "gf_ir123pay_update_feed_active" ) ?>");
                mysack.setVar("feed_id", feed_id);
                mysack.setVar("is_active", is_active ? 0 : 1);
                mysack.onError = function () {
                    alert('<?php _e( "خطای Ajax رخ داده است", "gravityformsir123pay" ) ?>')
                };
                mysack.runAJAX();
                return true;
            }
        </script>
		<?php
	}

	public static function update_feed_active() {
		check_ajax_referer( 'gf_ir123pay_update_feed_active', 'gf_ir123pay_update_feed_active' );
		$id   = absint( rgpost( 'feed_id' ) );
		$feed = GFIr123payData::get_feed( $id );
		GFIr123payData::update_feed( $id, $feed["form_id"], $_POST["is_active"], $feed["meta"] );
	}

	private static function Return_URL( $form_id, $lead_id ) {

		$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

		if ( $_SERVER['SERVER_PORT'] != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		$arr_params = array( 'id', 'lead', 'no' );
		$pageURL    = esc_url( remove_query_arg( $arr_params, $pageURL ) );

		$pageURL = str_replace( '#038;', '&', add_query_arg( array(
			'id'   => $form_id,
			'lead' => $lead_id
		), $pageURL ) );

		return apply_filters( self::$author . '_ir123pay_return_url', apply_filters( self::$author . '_gateway_return_url', $pageURL, $form_id, $lead_id, __CLASS__ ), $form_id, $lead_id, __CLASS__ );
	}

	private static function Return_URL_Ir123pay( $form_id, $lead_id ) {

		$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

		if ( $_SERVER['SERVER_PORT'] != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		$arr_params = array( 'id', 'lead', 'no' );
		$pageURL    = esc_url( remove_query_arg( $arr_params, $pageURL ) );

		$pageURL = str_replace( '#038;', '&', add_query_arg( array(
			'id'   => $form_id,
			'lead' => $lead_id
		), $pageURL ) );

		$pageURL = apply_filters( self::$author . '_ir123pay_return_url', apply_filters( self::$author . '_gateway_return_url', $pageURL, $form_id, $lead_id, __CLASS__ ), $form_id, $lead_id, __CLASS__ );
		$pageURL = apply_filters( self::$author . '_ir123pay_return_url_ir123pay', apply_filters( self::$author . '_gateway_return_url_ir123pay', self::URL_UTF8( urlencode( $pageURL ) ), $pageURL, $form_id, $lead_id, __CLASS__ ), $pageURL, $form_id, $lead_id, __CLASS__ );

		return $pageURL;
	}

	public static function URL_UTF8( $url ) {
		$encoded = '';
		$length  = mb_strlen( $url );
		for ( $i = 0; $i < $length; $i ++ ) {
			$encoded .= '%' . wordwrap( bin2hex( mb_substr( $url, $i, 1 ) ), 2, '%', true );
		}

		return $encoded;
	}

	public static function get_order_total( $form, $entry ) {

		$total = GFCommon::get_order_total( $form, $entry );
		$total = ( ! empty( $total ) && $total > 0 ) ? $total : 0;

		$config = self::get_config_by_entry( $entry );

		if ( ! empty( $config ) && isset( $config["meta"] ) && isset( $config["meta"]["shaparak"] ) ) {

			$currency = GFCommon::get_currency();

			if ( $currency == 'IRR' || $currency == 'IRT' ) {

				if ( $currency == 'IRR' && $total < 1000 && $total > 0 ) {
					if ( $config["meta"]["shaparak"] == "sadt" ) {
						$total = 1000;
					} else {
						$total = 0;
					}
				}

				if ( $currency == 'IRT' && $total < 100 && $total > 0 ) {
					if ( $config["meta"]["shaparak"] == "sadt" ) {
						$total = 100;
					} else {
						$total = 0;
					}
				}
			}
		}

		return apply_filters( self::$author . '_ir123pay_get_order_total', apply_filters( self::$author . '_gateway_get_order_total', $total, $form, $entry ), $form, $entry );
	}

	private static function get_mapped_field_list( $field_name, $selected_field, $fields ) {
		$str = "<select name='$field_name' id='$field_name'><option value=''></option>";
		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				$field_id    = $field[0];
				$field_label = esc_html( GFCommon::truncate_middle( $field[1], 40 ) );
				$selected    = $field_id == $selected_field ? "selected='selected'" : "";
				$str         .= "<option value='" . $field_id . "' " . $selected . ">" . $field_label . "</option>";
			}
		}
		$str .= "</select>";

		return $str;
	}

	private static function get_form_fields( $form ) {
		$fields = array();
		if ( is_array( $form["fields"] ) ) {
			foreach ( $form["fields"] as $field ) {
				if ( isset( $field["inputs"] ) && is_array( $field["inputs"] ) ) {
					foreach ( $field["inputs"] as $input ) {
						$fields[] = array( $input["id"], GFCommon::get_label( $field, $input["id"] ) );
					}
				} else if ( ! rgar( $field, 'displayOnly' ) ) {
					$fields[] = array( $field["id"], GFCommon::get_label( $field ) );
				}
			}
		}

		return $fields;
	}

	private static function get_customer_information_desc( $form, $config = null ) {
		$form_fields    = self::get_form_fields( $form );
		$selected_field = ! empty( $config["meta"]["customer_fields_desc"] ) ? $config["meta"]["customer_fields_desc"] : '';

		return self::get_mapped_field_list( 'ir123pay_customer_field_desc', $selected_field, $form_fields );
	}

	private static function get_customer_information_name( $form, $config = null ) {
		$form_fields    = self::get_form_fields( $form );
		$selected_field = ! empty( $config["meta"]["customer_fields_name"] ) ? $config["meta"]["customer_fields_name"] : '';

		return self::get_mapped_field_list( 'ir123pay_customer_field_name', $selected_field, $form_fields );
	}

	private static function get_customer_information_family( $form, $config = null ) {
		$form_fields    = self::get_form_fields( $form );
		$selected_field = ! empty( $config["meta"]["customer_fields_family"] ) ? $config["meta"]["customer_fields_family"] : '';

		return self::get_mapped_field_list( 'ir123pay_customer_field_family', $selected_field, $form_fields );
	}

	private static function get_customer_information_email( $form, $config = null ) {
		$form_fields    = self::get_form_fields( $form );
		$selected_field = ! empty( $config["meta"]["customer_fields_email"] ) ? $config["meta"]["customer_fields_email"] : '';

		return self::get_mapped_field_list( 'ir123pay_customer_field_email', $selected_field, $form_fields );
	}

	public static function payment_entry_detail( $form_id, $lead ) {

		$payment_gateway = rgar( $lead, "payment_method" );

		if ( ! empty( $payment_gateway ) && $payment_gateway == "ir123pay" ) {

			do_action( 'gf_gateway_entry_detail' );

			?>
            <hr/>
            <strong>
				<?php _e( 'اطلاعات تراکنش :', 'gravityformsir123pay' ) ?>
            </strong>
            <br/>
            <br/>
			<?php

			$transaction_type = rgar( $lead, "transaction_type" );
			$payment_status   = rgar( $lead, "payment_status" );
			$payment_amount   = rgar( $lead, "payment_amount" );

			if ( empty( $payment_amount ) ) {
				$form           = RGFormsModel::get_form_meta( $form_id );
				$payment_amount = GFCommon::get_order_total( $form, $lead );
			}

			$transaction_id = rgar( $lead, "transaction_id" );
			$payment_date   = rgar( $lead, "payment_date" );

			$date = new DateTime( $payment_date );
			$tzb  = get_option( 'gmt_offset' );
			$tzn  = abs( $tzb ) * 3600;
			$tzh  = intval( gmdate( "H", $tzn ) );
			$tzm  = intval( gmdate( "i", $tzn ) );

			if ( intval( $tzb ) < 0 ) {
				$date->sub( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			} else {
				$date->add( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			}

			$payment_date = $date->format( 'Y-m-d H:i:s' );
			$payment_date = GF_jdate( 'Y-m-d H:i:s', strtotime( $payment_date ), '', date_default_timezone_get(), 'en' );

			if ( $payment_status == 'Paid' ) {
				$payment_status_persian = __( 'موفق', 'gravityformsir123pay' );
			}

			if ( $payment_status == 'Active' ) {
				$payment_status_persian = __( 'موفق', 'gravityformsir123pay' );
			}

			if ( $payment_status == 'Cancelled' ) {
				$payment_status_persian = __( 'منصرف شده', 'gravityformsir123pay' );
			}

			if ( $payment_status == 'Failed' ) {
				$payment_status_persian = __( 'ناموفق', 'gravityformsir123pay' );
			}

			if ( $payment_status == 'Processing' ) {
				$payment_status_persian = __( 'معلق', 'gravityformsir123pay' );
			}

			if ( ! strtolower( rgpost( "save" ) ) || RGForms::post( "screen_mode" ) != "edit" ) {
				echo __( 'وضعیت پرداخت : ', 'gravityformsir123pay' ) . $payment_status_persian . '<br/><br/>';
				echo __( 'تاریخ پرداخت : ', 'gravityformsir123pay' ) . '<span style="">' . $payment_date . '</span><br/><br/>';
				echo __( 'مبلغ پرداختی : ', 'gravityformsir123pay' ) . GFCommon::to_money( $payment_amount, rgar( $lead, "currency" ) ) . '<br/><br/>';
				echo __( 'کد رهگیری : ', 'gravityformsir123pay' ) . $transaction_id . '<br/><br/>';
				echo __( 'درگاه پرداخت : یک دو سه پی', 'gravityformsir123pay' );
			} else {
				$payment_string = '';
				$payment_string .= '<select id="payment_status" name="payment_status">';
				$payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status_persian . '</option>';

				if ( $transaction_type == 1 ) {
					if ( $payment_status != "Paid" ) {
						$payment_string .= '<option value="Paid">' . __( 'موفق', 'gravityformsir123pay' ) . '</option>';
					}
				}

				if ( $transaction_type == 2 ) {
					if ( $payment_status != "Active" ) {
						$payment_string .= '<option value="Active">' . __( 'موفق', 'gravityformsir123pay' ) . '</option>';
					}
				}

				if ( ! $transaction_type ) {

					if ( $payment_status != "Paid" ) {
						$payment_string .= '<option value="Paid">' . __( 'موفق', 'gravityformsir123pay' ) . '</option>';
					}

					if ( $payment_status != "Active" ) {
						$payment_string .= '<option value="Active">' . __( 'موفق', 'gravityformsir123pay' ) . '</option>';
					}
				}

				if ( $payment_status != "Failed" ) {
					$payment_string .= '<option value="Failed">' . __( 'ناموفق', 'gravityformsir123pay' ) . '</option>';
				}

				if ( $payment_status != "Cancelled" ) {
					$payment_string .= '<option value="Cancelled">' . __( 'منصرف شده', 'gravityformsir123pay' ) . '</option>';
				}

				if ( $payment_status != "Processing" ) {
					$payment_string .= '<option value="Processing">' . __( 'معلق', 'gravityformsir123pay' ) . '</option>';
				}

				$payment_string .= '</select>';

				echo __( 'وضعیت پرداخت :', 'gravityformsir123pay' ) . $payment_string . '<br/><br/>';
				?>
                <div id="edit_payment_status_details" style="display:block">
                    <table>
                        <tr>
                            <td><?php _e( 'تاریخ پرداخت :', 'gravityformsir123pay' ) ?></td>
                            <td><input type="text" id="payment_date" name="payment_date"
                                       value="<?php echo $payment_date ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e( 'مبلغ پرداخت :', 'gravityformsir123pay' ) ?></td>
                            <td><input type="text" id="payment_amount" name="payment_amount"
                                       value="<?php echo $payment_amount ?>"></td>
                        </tr>
                        <tr>
                            <td><?php _e( 'شماره تراکنش :', 'gravityformsir123pay' ) ?></td>
                            <td><input type="text" id="ir123pay_transaction_id" name="ir123pay_transaction_id"
                                       value="<?php echo $transaction_id ?>"></td>
                        </tr>

                    </table>
                    <br/>
                </div>
				<?php
				echo __( 'درگاه پرداخت : یک دو سه پی (غیر قابل ویرایش)', 'gravityformsir123pay' );
			}

			echo '<br/>';
		}
	}

	public static function update_payment_entry( $form, $lead_id ) {

		check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

		do_action( 'gf_gateway_update_entry' );

		$lead = RGFormsModel::get_lead( $lead_id );

		$payment_gateway = rgar( $lead, "payment_method" );

		if ( empty( $payment_gateway ) ) {
			return;
		}

		if ( $payment_gateway != "ir123pay" ) {
			return;
		}

		$payment_status = rgpost( "payment_status" );
		if ( empty( $payment_status ) ) {
			$payment_status = rgar( $lead, "payment_status" );
		}

		$payment_amount       = rgpost( "payment_amount" );
		$payment_transaction  = rgpost( "ir123pay_transaction_id" );
		$payment_date_Checker = $payment_date = rgpost( "payment_date" );

		list( $date, $time ) = explode( " ", $payment_date );
		list( $Y, $m, $d ) = explode( "-", $date );
		list( $H, $i, $s ) = explode( ":", $time );
		$miladi = GF_jalali_to_gregorian( $Y, $m, $d );

		$date         = new DateTime( "$miladi[0]-$miladi[1]-$miladi[2] $H:$i:$s" );
		$payment_date = $date->format( 'Y-m-d H:i:s' );

		if ( empty( $payment_date_Checker ) ) {
			if ( ! empty( $lead["payment_date"] ) ) {
				$payment_date = $lead["payment_date"];
			} else {
				$payment_date = rgar( $lead, "date_created" );
			}
		} else {
			$payment_date = date( "Y-m-d H:i:s", strtotime( $payment_date ) );
			$date         = new DateTime( $payment_date );
			$tzb          = get_option( 'gmt_offset' );
			$tzn          = abs( $tzb ) * 3600;
			$tzh          = intval( gmdate( "H", $tzn ) );
			$tzm          = intval( gmdate( "i", $tzn ) );
			if ( intval( $tzb ) < 0 ) {
				$date->add( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			} else {
				$date->sub( new DateInterval( 'P0DT' . $tzh . 'H' . $tzm . 'M' ) );
			}
			$payment_date = $date->format( 'Y-m-d H:i:s' );
		}

		global $current_user;
		$user_id   = 0;
		$user_name = __( "مهمان", 'gravityformsir123pay' );
		if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		}

		$lead["payment_status"] = $payment_status;
		$lead["payment_amount"] = $payment_amount;
		$lead["payment_date"]   = $payment_date;
		$lead["transaction_id"] = $payment_transaction;
		GFAPI::update_entry( $lead );

		if ( $payment_status == 'Paid' || $payment_status == 'Active' ) {
			GFAPI::update_entry_property( $lead["id"], "is_fulfilled", 1 );
		} else {
			GFAPI::update_entry_property( $lead["id"], "is_fulfilled", 0 );
		}

		$new_status = '';
		switch ( rgar( $lead, "payment_status" ) ) {
			case "Active" :
				$new_status = __( 'موفق', 'gravityformsir123pay' );
				break;

			case "Paid" :
				$new_status = __( 'موفق', 'gravityformsir123pay' );
				break;

			case "Cancelled" :
				$new_status = __( 'منصرف شده', 'gravityformsir123pay' );
				break;

			case "Failed" :
				$new_status = __( 'ناموفق', 'gravityformsir123pay' );
				break;

			case "Processing" :
				$new_status = __( 'معلق', 'gravityformsir123pay' );
				break;
		}

		RGFormsModel::add_note( $lead["id"], $user_id, $user_name, sprintf( __( "اطلاعات تراکنش به صورت دستی ویرایش شد . وضعیت : %s - مبلغ : %s - کد رهگیری : %s - تاریخ : %s", "gravityformsir123pay" ), $new_status, GFCommon::to_money( $lead["payment_amount"], $lead["currency"] ), $payment_transaction, $lead["payment_date"] ) );

	}

	public static function settings_page() {

		if ( ! extension_loaded( 'curl' ) ) {
			_e( 'ماژول curl بر روی سرور شما فعال نیست. برای استفاده از درگاه باید آن را فعال نمایید. با مدیر هاست تماس بگیرید.', 'gravityformsrashapay' );

			return;
		}

		if ( rgpost( "uninstall" ) ) {
			check_admin_referer( "uninstall", "gf_ir123pay_uninstall" );
			self::uninstall();
			echo '<div class="updated fade" style="padding:20px;">' . __( "درگاه با موفقیت غیرفعال شد و اطلاعات مربوط به آن نیز از بین رفت برای فعالسازی مجدد میتوانید از طریق افزونه های وردپرس اقدام نمایید .", "gravityformsir123pay" ) . '</div>';

			return;
		} else if ( isset( $_POST["gf_ir123pay_submit"] ) ) {

			check_admin_referer( "update", "gf_ir123pay_update" );
			$settings = array(
				"merchant_id" => rgpost( 'gf_ir123pay_merchant_id' ),
				"mode"        => rgpost( 'gf_ir123pay_mode' ),
				"gname"       => rgpost( 'gf_ir123pay_gname' ),
			);
			update_option( "gf_ir123pay_settings", array_map( 'sanitize_text_field', $settings ) );
			if ( isset( $_POST["gf_ir123pay_configured"] ) ) {
				update_option( "gf_ir123pay_configured", sanitize_text_field( $_POST["gf_ir123pay_configured"] ) );
			} else {
				delete_option( "gf_ir123pay_configured" );
			}
		} else {
			$settings = get_option( "gf_ir123pay_settings" );
		}

		if ( ! empty( $_POST ) ) {

			if ( isset( $_POST["gf_ir123pay_configured"] ) && ( $Response = self::Request( 'valid_checker', '', '', '' ) ) && $Response != false ) {

				if ( $Response === true ) {
					echo '<div class="updated fade" style="padding:6px">' . __( "ارتباط با درگاه برقرار شد و اطلاعات وارد شده صحیح است .", "gravityformsir123pay" ) . '</div>';
				} else if ( $Response == 'sandbox' ) {
					echo '<div class="updated fade" style="padding:6px">' . __( "در حالت تستی نیاز به ورود اطلاعات صحیح نمی باشد .", "gravityformsir123pay" ) . '</div>';
				} else {
					echo '<div class="error fade" style="padding:6px">' . $Response . '</div>';
				}

			} else {
				echo '<div class="updated fade" style="padding:6px">' . __( "تنظیمات ذخیره شدند .", "gravityformsir123pay" ) . '</div>';
			}
		} else if ( isset( $_GET['subview'] ) && $_GET['subview'] == 'gf_ir123pay' && isset( $_GET['updated'] ) ) {
			echo '<div class="updated fade" style="padding:6px">' . __( "تنظیمات ذخیره شدند .", "gravityformsir123pay" ) . '</div>';
		}
		?>

        <form action="" method="post">

			<?php wp_nonce_field( "update", "gf_ir123pay_update" ) ?>

            <h3>
				<span>
				<i class="fa fa-credit-card"></i>
					<?php _e( "تنظیمات یک دو سه پی", "gravityformsir123pay" ) ?>
				</span>
            </h3>

            <table class="form-table">

                <tr>
                    <th scope="row"><label
                                for="gf_ir123pay_configured"><?php _e( "فعالسازی", "gravityformsir123pay" ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="gf_ir123pay_configured"
                               id="gf_ir123pay_configured" <?php echo get_option( "gf_ir123pay_configured" ) ? "checked='checked'" : "" ?>/>
                        <label class="inline"
                               for="gf_ir123pay_configured"><?php _e( "بله", "gravityformsir123pay" ); ?></label>
                    </td>
                </tr>


                <tr>
                    <th scope="row"><label
                                for="gf_ir123pay_mode"><?php _e( "مد کاری یک دو سه پی", "gravityformsir123pay" ); ?></label>
                    </th>
                    <td>

                        <input type="radio" name="gf_ir123pay_mode"
                               value="Iran" <?php echo rgar( $settings, 'mode' ) != "Test" ? "checked='checked'" : "" ?>/>
						<?php _e( "عملیاتی", "gravityformsir123pay" ); ?>

                    </td>
                </tr>

                <tr>
                    <th scope="row"><label
                                for="gf_ir123pay_merchant_id"><?php _e( "API", "gravityformsir123pay" ); ?></label>
                    </th>
                    <td>
                        <input style="width:350px;text-align:left;direction:ltr !important" type="text"
                               id="gf_ir123pay_merchant_id" name="gf_ir123pay_merchant_id"
                               value="<?php echo sanitize_text_field( rgar( $settings, 'merchant_id' ) ) ?>"/>
                    </td>
                </tr>

				<?php

				$gateway_title = __( "یک دو سه پی", "gravityformsir123pay" );

				if ( sanitize_text_field( rgar( $settings, 'gname' ) ) ) {
					$gateway_title = sanitize_text_field( $settings["gname"] );
				}

				?>
                <tr>
                    <th scope="row">
                        <label for="gf_ir123pay_gname">
							<?php _e( "عنوان", "gravityformsir123pay" ); ?>
							<?php gform_tooltip( 'gateway_name' ) ?>
                        </label>
                    </th>
                    <td>
                        <input style="width:350px;" type="text" id="gf_ir123pay_gname" name="gf_ir123pay_gname"
                               value="<?php echo $gateway_title; ?>"/>
                    </td>
                </tr>

                <tr>
                    <td colspan="2"><input style="font-family:tahoma !important;" type="submit"
                                           name="gf_ir123pay_submit"
                                           class="button-primary"
                                           value="<?php _e( "ذخیره تنظیمات", "gravityformsir123pay" ) ?>"/></td>
                </tr>

            </table>

        </form>

        <form action="" method="post">
			<?php

			wp_nonce_field( "uninstall", "gf_ir123pay_uninstall" );

			if ( self::has_access( "gravityforms_ir123pay_uninstall" ) ) {

				?>
                <div class="hr-divider"></div>
                <div class="delete-alert alert_red">

                    <h3>
                        <i class="fa fa-exclamation-triangle gf_invalid"></i>
						<?php _e( "غیر فعالسازی افزونه دروازه پرداخت یک دو سه پی", "gravityformsir123pay" ); ?>
                    </h3>

                    <div
                            class="gf_delete_notice"><?php _e( "تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به یک دو سه پی حذف خواهد شد", "gravityformsir123pay" ) ?></div>

					<?php
					$uninstall_button = '<input  style="font-family:tahoma !important;" type="submit" name="uninstall" value="' . __( "غیر فعال سازی درگاه یک دو سه پی", "gravityformsir123pay" ) . '" class="button" onclick="return confirm(\'' . __( "تذکر : بعد از غیرفعالسازی تمامی اطلاعات مربوط به یک دو سه پی حذف خواهد شد . آیا همچنان مایل به غیر فعالسازی میباشید؟", "gravityformsir123pay" ) . '\');"/>';
					echo apply_filters( "gform_ir123pay_uninstall_button", $uninstall_button );
					?>

                </div>

			<?php } ?>
        </form>
		<?php
	}

	public static function get_gname() {
		$settings = get_option( "gf_ir123pay_settings" );
		if ( isset( $settings["gname"] ) ) {
			$gname = $settings["gname"];
		} else {
			$gname = __( 'یک دو سه پی', 'gravityformsir123pay' );
		}

		return $gname;
	}

	private static function get_merchant_id() {
		$settings    = get_option( "gf_ir123pay_settings" );
		$merchant_id = isset( $settings["merchant_id"] ) ? $settings["merchant_id"] : '';

		return trim( $merchant_id );
	}

	private static function get_mode() {
		$settings = get_option( "gf_ir123pay_settings" );
		$mode     = isset( $settings["mode"] ) ? $settings["mode"] : '';

		return $mode;
	}

	private static function config_page() {

		wp_register_style( 'gform_admin_ir123pay', GFCommon::get_base_url() . '/css/admin.css' );
		wp_print_styles( array( 'jquery-ui-styles', 'gform_admin_ir123pay', 'wp-pointer' ) ); ?>

		<?php if ( is_rtl() ) { ?>
            <style type="text/css">
                table.gforms_form_settings th {
                    text-align: right !important;
                }
            </style>
		<?php } ?>

        <div class="wrap gforms_edit_form gf_browser_gecko">

			<?php
			$id        = ! rgempty( "ir123pay_setting_id" ) ? rgpost( "ir123pay_setting_id" ) : absint( rgget( "id" ) );
			$config    = empty( $id ) ? array( "meta"      => array(),
			                                   "is_active" => true
			) : GFIr123payData::get_feed( $id );
			$get_feeds = GFIr123payData::get_feeds();
			$form_name = '';


			$_get_form_id = rgget( 'fid' ) ? rgget( 'fid' ) : ( ! empty( $config["form_id"] ) ? $config["form_id"] : '' );

			foreach ( (array) $get_feeds as $get_feed ) {
				if ( $get_feed['id'] == $id ) {
					$form_name = $get_feed['form_title'];
				}
			}
			?>


            <h2 class="gf_admin_page_title"><?php _e( "پیکربندی درگاه یک دو سه پی", "gravityformsir123pay" ) ?>

				<?php if ( ! empty( $_get_form_id ) ) { ?>
                    <span class="gf_admin_page_subtitle">
					<span
                            class="gf_admin_page_formid"><?php echo sprintf( __( "فید: %s", "gravityformsir123pay" ), $id ) ?></span>
					<span
                            class="gf_admin_page_formname"><?php echo sprintf( __( "فرم: %s", "gravityformsir123pay" ), $form_name ) ?></span>
				</span>
				<?php } ?>

            </h2>
            <a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_ir123pay"
               style="margin:8px 9px;"><?php _e( "تنظیمات حساب یک دو سه پی", "gravityformsir123pay" ) ?></a>

			<?php
			if ( ! rgempty( "gf_ir123pay_submit" ) ) {
				check_admin_referer( "update", "gf_ir123pay_feed" );

				$config["form_id"]                     = absint( rgpost( "gf_ir123pay_form" ) );
				$config["meta"]["type"]                = rgpost( "gf_ir123pay_type" );
				$config["meta"]["addon"]               = rgpost( "gf_ir123pay_addon" );
				$config["meta"]["shaparak"]            = rgpost( "gf_ir123pay_shaparak" );
				$config["meta"]["update_post_action1"] = rgpost( 'gf_ir123pay_update_action1' );
				$config["meta"]["update_post_action2"] = rgpost( 'gf_ir123pay_update_action2' );

				if ( isset( $form["notifications"] ) ) {
					$config["meta"]["delay_notifications"]    = rgpost( 'gf_ir123pay_delay_notifications' );
					$config["meta"]["selected_notifications"] = rgpost( 'gf_ir123pay_selected_notifications' );
				} else {
					if ( isset( $config["meta"]["delay_notifications"] ) ) {
						unset( $config["meta"]["delay_notifications"] );
					}
					if ( isset( $config["meta"]["selected_notifications"] ) ) {
						unset( $config["meta"]["selected_notifications"] );
					}
				}

				$config["meta"]["gf_ir123pay_conf_1"] = rgpost( 'gf_ir123pay_conf_1' );
				$config["meta"]["gf_ir123pay_conf_2"] = rgpost( 'gf_ir123pay_conf_2' );
				$config["meta"]["gf_ir123pay_conf_3"] = rgpost( 'gf_ir123pay_conf_3' );

				$config["meta"]["gf_ir123pay_notif_1"] = rgpost( 'gf_ir123pay_notif_1' );
				$config["meta"]["gf_ir123pay_notif_2"] = rgpost( 'gf_ir123pay_notif_2' );
				$config["meta"]["gf_ir123pay_notif_3"] = rgpost( 'gf_ir123pay_notif_3' );
				$config["meta"]["gf_ir123pay_notif_4"] = rgpost( 'gf_ir123pay_notif_4' );

				$config["meta"]["ir123pay_conditional_enabled"]  = rgpost( 'gf_ir123pay_conditional_enabled' );
				$config["meta"]["ir123pay_conditional_field_id"] = rgpost( 'gf_ir123pay_conditional_field_id' );
				$config["meta"]["ir123pay_conditional_operator"] = rgpost( 'gf_ir123pay_conditional_operator' );
				$config["meta"]["ir123pay_conditional_value"]    = rgpost( 'gf_ir123pay_conditional_value' );

				$config["meta"]["desc_pm"]                = rgpost( "gf_ir123pay_desc_pm" );
				$config["meta"]["customer_fields_desc"]   = rgpost( "ir123pay_customer_field_desc" );
				$config["meta"]["customer_fields_name"]   = rgpost( "ir123pay_customer_field_name" );
				$config["meta"]["customer_fields_family"] = rgpost( "ir123pay_customer_field_family" );
				$config["meta"]["customer_fields_email"]  = rgpost( "ir123pay_customer_field_email" );


				$safe_data = array();
				foreach ( $config["meta"] as $key => $val ) {
					if ( ! is_array( $val ) ) {
						$safe_data[ $key ] = sanitize_text_field( $val );
					} else {
						$safe_data[ $key ] = array_map( 'sanitize_text_field', $val );
					}
				}
				$config["meta"] = $safe_data;

				$config = apply_filters( self::$author . '_gform_gateway_save_config', $config );
				$config = apply_filters( self::$author . '_gform_ir123pay_save_config', $config );

				$id = GFIr123payData::update_feed( $id, $config["form_id"], $config["is_active"], $config["meta"] );
				if ( ! headers_sent() ) {
					wp_redirect( admin_url( 'admin.php?page=gf_ir123pay&view=edit&id=' . $id . '&updated=true' ) );
					exit;
				} else {
					echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . admin_url( 'admin.php?page=gf_ir123pay&view=edit&id=' . $id . '&updated=true' ) . "'; };</script>";
					exit;
				}
				?>
                <div class="updated fade"
                     style="padding:6px"><?php echo sprintf( __( "فید به روز شد . %sبازگشت به لیست%s.", "gravityformsir123pay" ), "<a href='?page=gf_ir123pay'>", "</a>" ) ?></div>
				<?php
			}

			$_get_form_id = rgget( 'fid' ) ? rgget( 'fid' ) : ( ! empty( $config["form_id"] ) ? $config["form_id"] : '' );

			$form = array();
			if ( ! empty( $_get_form_id ) ) {
				$form = RGFormsModel::get_form_meta( $_get_form_id );
			}

			if ( rgget( 'updated' ) == 'true' ) {

				$id = empty( $id ) && isset( $_GET['id'] ) ? rgget( 'id' ) : $id;
				$id = absint( $id ); ?>

                <div class="updated fade"
                     style="padding:6px"><?php echo sprintf( __( "فید به روز شد . %sبازگشت به لیست%s . ", "gravityformsir123pay" ), "<a href='?page=gf_ir123pay'>", "</a>" ) ?></div>

				<?php
			}


			if ( ! empty( $_get_form_id ) ) { ?>

                <div id="gf_form_toolbar">
                    <ul id="gf_form_toolbar_links">

						<?php
						$menu_items = apply_filters( 'gform_toolbar_menu', GFForms::get_toolbar_menu_items( $_get_form_id ), $_get_form_id );
						echo GFForms::format_toolbar_menu_items( $menu_items ); ?>

                        <li class="gf_form_switcher">
                            <label for="export_form"><?php _e( 'یک فید انتخاب کنید', 'gravityformsir123pay' ) ?></label>
							<?php
							$feeds = GFIr123payData::get_feeds();
							if ( RG_CURRENT_VIEW != 'entry' ) { ?>
                                <select name="form_switcher" id="form_switcher"
                                        onchange="GF_SwitchForm(jQuery(this).val());">
                                    <option value=""><?php _e( 'تغییر فید یک دو سه پی', 'gravityformsir123pay' ) ?></option>
									<?php foreach ( $feeds as $feed ) {
										$selected = $feed["id"] == $id ? "selected='selected'" : ""; ?>
                                        <option
                                                value="<?php echo $feed["id"] ?>" <?php echo $selected ?> ><?php echo sprintf( __( 'فرم: %s (فید: %s)', 'gravityformsir123pay' ), $feed["form_title"], $feed["id"] ) ?></option>
									<?php } ?>
                                </select>
								<?php
							}
							?>
                        </li>
                    </ul>
                </div>
			<?php } ?>

            <div id="gform_tab_group" class="gform_tab_group vertical_tabs">
				<?php if ( ! empty( $_get_form_id ) ) { ?>
                    <ul id="gform_tabs" class="gform_tabs">
						<?php
						$title        = '';
						$get_form     = GFFormsModel::get_form_meta( $_get_form_id );
						$current_tab  = rgempty( 'subview', $_GET ) ? 'settings' : rgget( 'subview' );
						$current_tab  = ! empty( $current_tab ) ? $current_tab : ' ';
						$setting_tabs = GFFormSettings::get_tabs( $get_form['id'] );
						if ( ! $title ) {
							foreach ( $setting_tabs as $tab ) {
								$query = array(
									'page'    => 'gf_edit_forms',
									'view'    => 'settings',
									'subview' => $tab['name'],
									'id'      => $get_form['id']
								);
								$url   = add_query_arg( $query, admin_url( 'admin.php' ) );
								echo $tab['name'] == 'ir123pay' ? '<li class="active">' : '<li>';
								?>
                                <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $tab['label'] ) ?></a>
                                <span></span>
                                </li>
								<?php
							}
						}
						?>
                    </ul>
				<?php }
				$has_product = false;
				if ( isset( $form["fields"] ) ) {
					foreach ( $form["fields"] as $field ) {
						$shipping_field = GFAPI::get_fields_by_type( $form, array( 'shipping' ) );
						if ( $field["type"] == "product" || ! empty( $shipping_field ) ) {
							$has_product = true;
							break;
						}
					}
				} else if ( empty( $_get_form_id ) ) {
					$has_product = true;
				}
				?>
                <div id="gform_tab_container_<?php echo $_get_form_id ? $_get_form_id : 1 ?>"
                     class="gform_tab_container">
                    <div class="gform_tab_content" id="tab_<?php echo ! empty( $current_tab ) ? $current_tab : '' ?>">
                        <div id="form_settings" class="gform_panel gform_panel_form_settings">
                            <h3>
								<span>
									<i class="fa fa-credit-card"></i>
									<?php _e( "پیکربندی درگاه یک دو سه پی", "gravityformsir123pay" ); ?>
								</span>
                            </h3>
                            <form method="post" action="" id="gform_form_settings">

								<?php wp_nonce_field( "update", "gf_ir123pay_feed" ) ?>


                                <input type="hidden" name="ir123pay_setting_id" value="<?php echo $id ?>"/>
                                <table class="form-table gforms_form_settings" cellspacing="0" cellpadding="0">
                                    <tbody>
                                    <tr>
                                        <td colspan="2">
                                            <h4 class="gf_settings_subgroup_title">
												<?php _e( "پیکربندی درگاه یک دو سه پی", "gravityformsir123pay" ); ?>
                                            </h4>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>
											<?php _e( "انتخاب فرم", "gravityformsir123pay" ); ?>
                                        </th>
                                        <td>
                                            <select id="gf_ir123pay_form" name="gf_ir123pay_form"
                                                    onchange="GF_SwitchFid(jQuery(this).val());">
                                                <option
                                                        value=""><?php _e( "یک فرم انتخاب نمایید", "gravityformsir123pay" ); ?> </option>
												<?php
												$available_forms = GFIr123payData::get_available_forms();
												foreach ( $available_forms as $current_form ) {
													$selected = absint( $current_form->id ) == $_get_form_id ? 'selected="selected"' : ''; ?>
                                                    <option
                                                            value="<?php echo absint( $current_form->id ) ?>" <?php echo $selected; ?>><?php echo esc_html( $current_form->title ) ?></option>
													<?php
												}
												?>
                                            </select>
                                            <img
                                                    src="<?php echo esc_url( GFCommon::get_base_url() ) ?>/images/spinner.gif"
                                                    id="ir123pay_wait" style="display: none;"/>
                                        </td>
                                    </tr>

                                    </tbody>
                                </table>

								<?php if ( empty( $has_product ) || ! $has_product ) { ?>
                                    <div id="gf_ir123pay_invalid_product_form" class="gf_ir123pay_invalid_form"
                                         style="background-color:#FFDFDF; margin-top:4px; margin-bottom:6px;padding:18px; border:1px dotted #C89797;">
										<?php _e( "فرم انتخاب شده هیچ گونه فیلد قیمت گذاری ندارد، لطفا پس از افزودن این فیلدها مجددا اقدام نمایید.", "gravityformsir123pay" ) ?>
                                    </div>
								<?php } else { ?>
                                    <table class="form-table gforms_form_settings"
                                           id="ir123pay_field_group" <?php echo empty( $_get_form_id ) ? "style='display:none;'" : "" ?>
                                           cellspacing="0" cellpadding="0">
                                        <tbody>

                                        <tr>
                                            <th>
												<?php _e( "فرم ثبت نام", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <input type="checkbox" name="gf_ir123pay_type"
                                                       id="gf_ir123pay_type_subscription"
                                                       value="subscription" <?php echo rgar( $config['meta'], 'type' ) == "subscription" ? "checked='checked'" : "" ?>/>
                                                <label for="gf_ir123pay_type"></label>
                                                <span
                                                        class="description"><?php _e( 'در صورتی که تیک بزنید عملیات ثبت نام که توسط افزونه User Registration انجام خواهد شد تنها برای پرداخت های موفق عمل خواهد کرد' ); ?></span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="5">
                                                <h4 class="gf_settings_subgroup_title">
													<?php _e( "فیلد های ورودی یک دو سه پی", "gravityformsir123pay" ); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
												<?php _e( "توضیحات پرداخت", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <input type="text" name="gf_ir123pay_desc_pm" id="gf_ir123pay_desc_pm"
                                                       class="fieldwidth-1"
                                                       value="<?php echo rgar( $config["meta"], "desc_pm" ) ?>"/>
                                                <span
                                                        class="description"><?php _e( "شورت کد ها : {form_id} , {form_title} , {entry_id}", "gravityformsir123pay" ); ?></span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
												<?php _e( "توضیح تکمیلی", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td class="ir123pay_customer_fields_desc">
												<?php
												if ( ! empty( $form ) ) {
													echo self::get_customer_information_desc( $form, $config );
												}
												?>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
												<?php _e( "نام پرداخت کننده", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td class="ir123pay_customer_fields_name">
												<?php
												if ( ! empty( $form ) ) {
													echo self::get_customer_information_name( $form, $config );
												}
												?>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
												<?php _e( "نام خانوادگی پرداخت کننده", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td class="ir123pay_customer_fields_family">
												<?php
												if ( ! empty( $form ) ) {
													echo self::get_customer_information_family( $form, $config );
												}
												?>
                                            </td>
                                        </tr>


                                        <tr>
                                            <th>
												<?php _e( "ایمیل پرداخت کننده", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td class="ir123pay_customer_fields_email">
												<?php
												if ( ! empty( $form ) ) {
													echo self::get_customer_information_email( $form, $config );
												}
												?>
                                            </td>
                                        </tr>

										<?php $display_post_fields = ! empty( $form ) ? GFCommon::has_post_field( $form["fields"] ) : false; ?>

                                        <tr <?php echo $display_post_fields ? "" : "style='display:none;'" ?>>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
													<?php _e( "تنظیمات مربوط به وضعیت پست ها", "gravityformsir123pay" ); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <tr <?php echo $display_post_fields ? "" : "style='display:none;'" ?>>
                                            <th>
												<?php _e( "بعد ار پرداخت موفق", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <select id="gf_ir123pay_update_action1"
                                                        name="gf_ir123pay_update_action1">
                                                    <option
                                                            value="default" <?php echo rgar( $config["meta"], "update_post_action1" ) == "default" ? "selected='selected'" : "" ?>><?php _e( "وضعیت پیشفرض فرم", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="publish" <?php echo rgar( $config["meta"], "update_post_action1" ) == "publish" ? "selected='selected'" : "" ?>><?php _e( "منشتر شده", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="draft" <?php echo rgar( $config["meta"], "update_post_action1" ) == "draft" ? "selected='selected'" : "" ?>><?php _e( "پیشنویس", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="pending" <?php echo rgar( $config["meta"], "update_post_action1" ) == "pending" ? "selected='selected'" : "" ?>><?php _e( "در انتظار بررسی", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="private" <?php echo rgar( $config["meta"], "update_post_action1" ) == "private" ? "selected='selected'" : "" ?>><?php _e( "خصوصی", "gravityformsir123pay" ) ?></option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr <?php echo $display_post_fields ? "" : "style='display:none;'" ?>>
                                            <th>
												<?php _e( "بعد ار پرداخت ناموفق", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <select id="gf_ir123pay_update_action2"
                                                        name="gf_ir123pay_update_action2">
                                                    <option
                                                            value="dont" <?php echo rgar( $config["meta"], "update_post_action2" ) == "dont" ? "selected='selected'" : "" ?>><?php _e( "عدم ایجاد پست", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="default" <?php echo rgar( $config["meta"], "update_post_action2" ) == "default" ? "selected='selected'" : "" ?>><?php _e( "وضعیت پیشفرض فرم", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="publish" <?php echo rgar( $config["meta"], "update_post_action2" ) == "publish" ? "selected='selected'" : "" ?>><?php _e( "منشتر شده", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="draft" <?php echo rgar( $config["meta"], "update_post_action2" ) == "draft" ? "selected='selected'" : "" ?>><?php _e( "پیشنویس", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="pending" <?php echo rgar( $config["meta"], "update_post_action2" ) == "pending" ? "selected='selected'" : "" ?>><?php _e( "در انتظار بررسی", "gravityformsir123pay" ) ?></option>
                                                    <option
                                                            value="private" <?php echo rgar( $config["meta"], "update_post_action2" ) == "private" ? "selected='selected'" : "" ?>><?php _e( "خصوصی", "gravityformsir123pay" ) ?></option>
                                                </select>
                                            </td>
                                        </tr>

                                        <tr <?php echo ! isset( $form["confirmations"] ) ? "style='display:none;'" : "" ?>>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
													<?php _e( "تنظیمات تاییدیه ها", "gravityformsir123pay" ); ?>
                                                </h4>
                                            </td>
                                        </tr>

										<?php $confirmations = isset( $form['confirmations'] ) ? $form['confirmations'] : array(); ?>
                                        <tr id="gf_ir123pay_confirmations_1" <?php echo ! isset( $form["confirmations"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
												<?php _e( "بعد از پرداخت موفق", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
												<?php
												$selected_confirmations = ! empty( $config["meta"]["gf_ir123pay_conf_1"] ) ? $config["meta"]["gf_ir123pay_conf_1"] : array();
												foreach ( $confirmations as $confirmation ) { ?>
                                                    <li class="gf_ir123pay_confirmation">
                                                        <input id="gf_ir123pay_conf_1_<?php echo $confirmation['id'] ?>"
                                                               name="gf_ir123pay_conf_1[]" type="checkbox"
                                                               class="confirmation_checkbox"
                                                               value="<?php echo $confirmation['id'] ?>" <?php checked( true, in_array( $confirmation['id'], $selected_confirmations ) ) ?> />
                                                        <label for="gf_ir123pay_conf_1_<?php echo $confirmation['id'] ?>"
                                                               class="inline"
                                                               for="gf_ir123pay_selected_confirmations"><?php echo $confirmation['name']; ?></label>
                                                    </li>
												<?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_ir123pay_confirmations_2" <?php echo ! isset( $form["confirmations"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
												<?php _e( "بعد از پرداخت ناموفق", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
												<?php
												$selected_confirmations = ! empty( $config["meta"]["gf_ir123pay_conf_2"] ) ? $config["meta"]["gf_ir123pay_conf_2"] : array();
												foreach ( $confirmations as $confirmation ) { ?>
                                                    <li class="gf_ir123pay_confirmation">
                                                        <input id="gf_ir123pay_conf_2_<?php echo $confirmation['id'] ?>"
                                                               name="gf_ir123pay_conf_2[]" type="checkbox"
                                                               class="confirmation_checkbox"
                                                               value="<?php echo $confirmation['id'] ?>" <?php checked( true, in_array( $confirmation['id'], $selected_confirmations ) ) ?> />
                                                        <label for="gf_ir123pay_conf_2_<?php echo $confirmation['id'] ?>"
                                                               class="inline"
                                                               for="gf_ir123pay_selected_confirmations"><?php echo $confirmation['name']; ?></label>
                                                    </li>
												<?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_ir123pay_confirmations_3" <?php echo ! isset( $form["confirmations"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
												<?php _e( "بعد از انصراف", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
												<?php
												$selected_confirmations = ! empty( $config["meta"]["gf_ir123pay_conf_3"] ) ? $config["meta"]["gf_ir123pay_conf_3"] : array();
												foreach ( $confirmations as $confirmation ) { ?>
                                                    <li class="gf_ir123pay_confirmation">
                                                        <input id="gf_ir123pay_conf_3_<?php echo $confirmation['id'] ?>"
                                                               name="gf_ir123pay_conf_3[]" type="checkbox"
                                                               class="confirmation_checkbox"
                                                               value="<?php echo $confirmation['id'] ?>" <?php checked( true, in_array( $confirmation['id'], $selected_confirmations ) ) ?> />
                                                        <label for="gf_ir123pay_conf_3_<?php echo $confirmation['id'] ?>"
                                                               class="inline"
                                                               for="gf_ir123pay_selected_confirmations"><?php echo $confirmation['name']; ?></label>
                                                    </li>
												<?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_ir123pay_confirmations" <?php echo ! isset( $form["confirmations"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <br><br><?php _e( "توجه !", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <p class="description"><?php _e( "در صورتی که هیچ تاییدیه ای برای نمایش وجود نداشته باشد یا منطق شرطی سایر تاییدیه ها برقرار نباشد ، به صورت خودکار از تاییدیه پیشفرض استفاده خواهد شد. پس به منطق شرطی تاییدیه دقت نمایید!", "gravityformsir123pay" ); ?></p>
                                                <p class="description"><?php _e( "برای نمایش علت خطا میتوانید از شورت کد {fault} داخل متن تاییدیه ها استفاده نمایید.", "gravityformsir123pay" ); ?></p>
                                                <p class="description"><?php _e( "همچنین برچسب های مربوط به کد تراکنش و ... هم داخل لیست برچسب های تاییدیه ها و اعلان ها موجود می باشند.", "gravityformsir123pay" ); ?></p>
                                            </td>
                                        </tr>

                                        <tr <?php echo ! isset( $form["notifications"] ) ? "style='display:none;'" : "" ?>>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
													<?php _e( "تنظیمات اعلان ها", "gravityformsir123pay" ); ?>
                                                </h4>
                                            </td>
                                        </tr>

										<?php $notifications = GFCommon::get_notifications( 'form_submission', $form ); ?>
                                        <tr id="gf_ir123pay_notifications" <?php echo ! isset( $form["notifications"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
												<?php _e( "بلافاصله بعد از ثبت فرم", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
												<?php
												$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_1"] ) ? $config["meta"]["gf_ir123pay_notif_1"] : array();
												foreach ( $notifications as $notification ) { ?>
                                                    <li class="gf_ir123pay_notification">
                                                        <input id="gf_ir123pay_notif_1_<?php echo $notification['id'] ?>"
                                                               name="gf_ir123pay_notif_1[]" type="checkbox"
                                                               class="notification_checkbox"
                                                               value="<?php echo $notification['id'] ?>" <?php checked( true, in_array( $notification['id'], $selected_notifications ) ) ?> />
                                                        <label
                                                                for="gf_ir123pay_notif_1_<?php echo $notification['id'] ?>"
                                                                class="inline"
                                                                for="gf_ir123pay_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
												<?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_ir123pay_notifications" <?php echo ! isset( $form["notifications"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
												<?php _e( "بعد از پرداخت موفق", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
												<?php
												$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_2"] ) ? $config["meta"]["gf_ir123pay_notif_2"] : array();
												foreach ( $notifications as $notification ) { ?>
                                                    <li class="gf_ir123pay_notification">
                                                        <input id="gf_ir123pay_notif_2_<?php echo $notification['id'] ?>"
                                                               name="gf_ir123pay_notif_2[]" type="checkbox"
                                                               class="notification_checkbox"
                                                               value="<?php echo $notification['id'] ?>" <?php checked( true, in_array( $notification['id'], $selected_notifications ) ) ?> />
                                                        <label
                                                                for="gf_ir123pay_notif_2_<?php echo $notification['id'] ?>"
                                                                class="inline"
                                                                for="gf_ir123pay_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
												<?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_ir123pay_notifications" <?php echo ! isset( $form["notifications"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
												<?php _e( "بعد از پرداخت ناموفق", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
												<?php
												$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_3"] ) ? $config["meta"]["gf_ir123pay_notif_3"] : array();
												foreach ( $notifications as $notification ) { ?>
                                                    <li class="gf_ir123pay_notification">
                                                        <input id="gf_ir123pay_notif_3_<?php echo $notification['id'] ?>"
                                                               name="gf_ir123pay_notif_3[]" type="checkbox"
                                                               class="notification_checkbox"
                                                               value="<?php echo $notification['id'] ?>" <?php checked( true, in_array( $notification['id'], $selected_notifications ) ) ?> />
                                                        <label
                                                                for="gf_ir123pay_notif_3_<?php echo $notification['id'] ?>"
                                                                class="inline"
                                                                for="gf_ir123pay_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
												<?php } ?>
                                            </td>
                                        </tr>

                                        <tr id="gf_ir123pay_notifications" <?php echo ! isset( $form["notifications"] ) ? "style='display:none;'" : "" ?>>
                                            <th>
												<?php _e( "بعد از انصراف", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
												<?php
												$selected_notifications = ! empty( $config["meta"]["gf_ir123pay_notif_4"] ) ? $config["meta"]["gf_ir123pay_notif_4"] : array();
												foreach ( $notifications as $notification ) { ?>
                                                    <li class="gf_ir123pay_notification">
                                                        <input id="gf_ir123pay_notif_4_<?php echo $notification['id'] ?>"
                                                               name="gf_ir123pay_notif_4[]" type="checkbox"
                                                               class="notification_checkbox"
                                                               value="<?php echo $notification['id'] ?>" <?php checked( true, in_array( $notification['id'], $selected_notifications ) ) ?> />
                                                        <label
                                                                for="gf_ir123pay_notif_4_<?php echo $notification['id'] ?>"
                                                                class="inline"
                                                                for="gf_ir123pay_selected_notifications"><?php echo $notification['name']; ?></label>
                                                    </li>
												<?php } ?>
                                            </td>
                                        </tr>
                                        <tr id="gf_ir123pay_notifications" <?php echo ( ! isset( $form["confirmations"] ) && ! isset( $form["notifications"] ) ) ? "style='display:none;'" : "" ?>>
                                            <th>
                                                <br><?php _e( "توجه !", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <p class="description"><?php _e( 'در صورتی که مبلغ تراکنش 0 باشد، فرم به درگاه متصل نخواهد شد ولی تاییدیه و اعلان وضعیت "موفق" اعمال خواهد شد.', 'gravityformsir123pay' ); ?></p>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan="2">
                                                <h4 class="gf_settings_subgroup_title">
													<?php _e( "سایر تنظیمات درگاه", "gravityformsir123pay" ); ?>
                                                </h4>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th>
												<?php echo __( "سازگاری با ادان ها", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <input type="checkbox" name="gf_ir123pay_addon"
                                                       id="gf_ir123pay_addon_true"
                                                       value="true" <?php echo rgar( $config['meta'], 'addon' ) == "true" ? "checked='checked'" : "" ?>/>
                                                <label for="gf_ir123pay_addon"></label>
                                                <span
                                                        class="description"><?php _e( 'گرویتی فرم دارای ادان های مختلف وابسته به GFAddon نظیر ایمیل مارکتینگ و ... می باشد که دارای متد add_delayed_payment_support هستند. در صورتی که میخواهید این ادان ها تنها در صورت تراکنش موفق عمل کنند این گزینه را تیک بزنید.', 'gravityformsir123pay' ); ?></span>
                                            </td>
                                        </tr>

										<?php $minprice = GFCommon::get_currency() == 'IRT' ? __( "100 تومان", "gravityformsir123pay" ) : __( "1000 ریال", "gravityformsir123pay" ); ?>
                                        <tr>
                                            <th>
												<?php echo __( "قیمت بین 0 تا ", "gravityformsir123pay" ) . $minprice; ?>
                                            </th>
                                            <td>
                                                <input type="radio" name="gf_ir123pay_shaparak"
                                                       id="gf_ir123pay_shaparak_raygan"
                                                       value="raygan" <?php echo rgar( $config['meta'], 'shaparak' ) != "sadt" ? "checked" : "" ?>/>
                                                <label class="inline"
                                                       for="gf_ir123pay_shaparak_raygan"><?php _e( "رایگان شود", "gravityformsir123pay" ); ?></label>

                                                <input type="radio" name="gf_ir123pay_shaparak"
                                                       id="gf_ir123pay_shaparak_sadt"
                                                       value="sadt" <?php echo rgar( $config['meta'], 'shaparak' ) == "sadt" ? "checked" : "" ?>/>
                                                <label class="inline"
                                                       for="gf_ir123pay_shaparak_sadt"><?php echo $minprice . __( " شود ", "gravityformsir123pay" ); ?></label>
                                                <span
                                                        class="description"><?php _e( '(قابل استفاده برای زمانیکه منطق شرطی درگاه فعال نباشد)', 'gravityformsir123pay' ); ?></span>

                                            </td>
                                        </tr>


										<?php
										do_action( self::$author . '_gform_gateway_config', $config, $form );
										do_action( self::$author . '_gform_ir123pay_config', $config, $form );
										?>

                                        <tr>
                                            <th>
												<?php _e( "منطق شرطی", "gravityformsir123pay" ); ?>
                                            </th>
                                            <td>
                                                <input type="checkbox" id="gf_ir123pay_conditional_enabled"
                                                       name="gf_ir123pay_conditional_enabled" value="1"
                                                       onclick="if(this.checked){jQuery('#gf_ir123pay_conditional_container').fadeIn('fast');} else{ jQuery('#gf_ir123pay_conditional_container').fadeOut('fast'); }" <?php echo rgar( $config['meta'], 'ir123pay_conditional_enabled' ) ? "checked='checked'" : "" ?>/>
                                                <label
                                                        for="gf_ir123pay_conditional_enable"><?php _e( "فعالسازی این درگاه اگر شرط زیر برقرار باشد", "gravityformsir123pay" ); ?></label><br/>

                                                <table cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td>
                                                            <div
                                                                    id="gf_ir123pay_conditional_container" <?php echo ! rgar( $config['meta'], 'ir123pay_conditional_enabled' ) ? "style='display:none'" : "" ?>>
                                                                <div id="gf_ir123pay_conditional_fields"
                                                                     style="display:none">
                                                                    <select id="gf_ir123pay_conditional_field_id"
                                                                            name="gf_ir123pay_conditional_field_id"
                                                                            class="optin_select"
                                                                            onchange='jQuery("#gf_ir123pay_conditional_value_container").html(GetFieldValues(jQuery(this).val(), "", 20));'></select>
                                                                    <select id="gf_ir123pay_conditional_operator"
                                                                            name="gf_ir123pay_conditional_operator">
                                                                        <option
                                                                                value="is" <?php echo rgar( $config['meta'], 'ir123pay_conditional_operator' ) == "is" ? "selected='selected'" : "" ?>><?php _e( "هست", "gravityformsir123pay" ) ?></option>
                                                                        <option
                                                                                value="isnot" <?php echo rgar( $config['meta'], 'ir123pay_conditional_operator' ) == "isnot" ? "selected='selected'" : "" ?>><?php _e( "نیست", "gravityformsir123pay" ) ?></option>
                                                                        <option
                                                                                value=">" <?php echo rgar( $config['meta'], 'ir123pay_conditional_operator' ) == ">" ? "selected='selected'" : "" ?>><?php _e( "بزرگ تر است از", "gravityformsir123pay" ) ?></option>
                                                                        <option
                                                                                value="<" <?php echo rgar( $config['meta'], 'ir123pay_conditional_operator' ) == "<" ? "selected='selected'" : "" ?>><?php _e( "کوچک تر است از", "gravityformsir123pay" ) ?></option>
                                                                        <option
                                                                                value="contains" <?php echo rgar( $config['meta'], 'ir123pay_conditional_operator' ) == "contains" ? "selected='selected'" : "" ?>><?php _e( "شامل میشود ", "gravityformsir123pay" ) ?></option>
                                                                        <option
                                                                                value="starts_with" <?php echo rgar( $config['meta'], 'ir123pay_conditional_operator' ) == "starts_with" ? "selected='selected'" : "" ?>><?php _e( "شروع میشود با", "gravityformsir123pay" ) ?></option>
                                                                        <option
                                                                                value="ends_with" <?php echo rgar( $config['meta'], 'ir123pay_conditional_operator' ) == "ends_with" ? "selected='selected'" : "" ?>><?php _e( "تمام میشود با", "gravityformsir123pay" ) ?></option>
                                                                    </select>
                                                                    <div id="gf_ir123pay_conditional_value_container"
                                                                         name="gf_ir123pay_conditional_value_container"
                                                                         style="display:inline;"></div>
                                                                </div>
                                                                <div id="gf_ir123pay_conditional_message"
                                                                     style="display:none;background-color:#FFDFDF; margin-top:4px; margin-bottom:6px;padding:18px; border:1px dotted #C89797;">
																	<?php _e( "برای قرار دادن منطق شرطی ، باید فیلدهای فرم شما هم قابلیت منطق شرطی را داشته باشند . ", "gravityformsir123pay" ) ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <input type="submit" class="button-primary gfbutton"
                                                       name="gf_ir123pay_submit"
                                                       value="<?php _e( "ذخیره", "gravityformsir123pay" ); ?>"/>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
								<?php } ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            function GF_SwitchFid(fid) {
                jQuery("#ir123pay_wait").show();
                document.location = "?page=gf_ir123pay&view=edit&fid=" + fid;
            }

            function GF_SwitchForm(id) {
                if (id.length > 0) {
                    document.location = "?page=gf_ir123pay&view=edit&id=" + id;
                }
            }
			<?php
			if( ! empty( $_get_form_id )){ ?>
            form = <?php echo ! empty( $form ) ? GFCommon::json_encode( $form ) : array() ?> ;
            jQuery(document).ready(function () {
                var selectedField = "";
                var selectedValue = "";
				<?php if ( ! empty( $config["meta"]["ir123pay_conditional_field_id"] ) ) { ?>
                var selectedField = "<?php echo str_replace( '"', '\"', $config["meta"]["ir123pay_conditional_field_id"] )?>";
                var selectedValue = "<?php echo str_replace( '"', '\"', $config["meta"]["ir123pay_conditional_value"] )?>";
				<?php } ?>
                SetIr123payCondition(selectedField, selectedValue);
            });
			<?php
			}
			?>
            function SetIr123payCondition(selectedField, selectedValue) {
                jQuery("#gf_ir123pay_conditional_field_id").html(GetSelectableFields(selectedField, 20));
                var optinConditionField = jQuery("#gf_ir123pay_conditional_field_id").val();
                var checked = jQuery("#gf_ir123pay_conditional_enabled").attr('checked');
                if (optinConditionField) {
                    jQuery("#gf_ir123pay_conditional_message").hide();
                    jQuery("#gf_ir123pay_conditional_fields").show();
                    jQuery("#gf_ir123pay_conditional_value_container").html(GetFieldValues(optinConditionField, selectedValue, 20));
                    jQuery("#gf_ir123pay_conditional_value").val(selectedValue);
                }
                else {
                    jQuery("#gf_ir123pay_conditional_message").show();
                    jQuery("#gf_ir123pay_conditional_fields").hide();
                }
                if (!checked) jQuery("#gf_ir123pay_conditional_container").hide();

            }

            function GetFieldValues(fieldId, selectedValue, labelMaxCharacters) {
                if (!fieldId)
                    return "";
                var str = "";
                var field = GetFieldById(fieldId);
                if (!field)
                    return "";
                var isAnySelected = false;
                if (field["type"] == "post_category" && field["displayAllCategories"]) {
                    str += '<?php $dd = wp_dropdown_categories( array(
						"class"        => "optin_select",
						"orderby"      => "name",
						"id"           => "gf_ir123pay_conditional_value",
						"name"         => "gf_ir123pay_conditional_value",
						"hierarchical" => true,
						"hide_empty"   => 0,
						"echo"         => false
					) ); echo str_replace( "\n", "", str_replace( "'", "\\'", $dd ) ); ?>';
                }
                else if (field.choices) {
                    str += '<select id="gf_ir123pay_conditional_value" name="gf_ir123pay_conditional_value" class="optin_select">';
                    for (var i = 0; i < field.choices.length; i++) {
                        var fieldValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
                        var isSelected = fieldValue == selectedValue;
                        var selected = isSelected ? "selected='selected'" : "";
                        if (isSelected)
                            isAnySelected = true;
                        str += "<option value='" + fieldValue.replace(/'/g, "&#039;") + "' " + selected + ">" + TruncateMiddle(field.choices[i].text, labelMaxCharacters) + "</option>";
                    }
                    if (!isAnySelected && selectedValue) {
                        str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + TruncateMiddle(selectedValue, labelMaxCharacters) + "</option>";
                    }
                    str += "</select>";
                }
                else {
                    selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";
                    str += "<input type='text' placeholder='<?php _e( "یک مقدار وارد نمایید", "gravityformsir123pay" ); ?>' id='gf_ir123pay_conditional_value' name='gf_ir123pay_conditional_value' value='" + selectedValue.replace(/'/g, "&#039;") + "'>";
                }
                return str;
            }

            function GetFieldById(fieldId) {
                for (var i = 0; i < form.fields.length; i++) {
                    if (form.fields[i].id == fieldId)
                        return form.fields[i];
                }
                return null;
            }

            function TruncateMiddle(text, maxCharacters) {
                if (!text)
                    return "";
                if (text.length <= maxCharacters)
                    return text;
                var middle = parseInt(maxCharacters / 2);
                return text.substr(0, middle) + "..." + text.substr(text.length - middle, middle);
            }

            function GetSelectableFields(selectedFieldId, labelMaxCharacters) {
                var str = "";
                var inputType;
                for (var i = 0; i < form.fields.length; i++) {
                    fieldLabel = form.fields[i].adminLabel ? form.fields[i].adminLabel : form.fields[i].label;
                    inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
                    if (IsConditionalLogicField(form.fields[i])) {
                        var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
                        str += "<option value='" + form.fields[i].id + "' " + selected + ">" + TruncateMiddle(fieldLabel, labelMaxCharacters) + "</option>";
                    }
                }
                return str;
            }

            function IsConditionalLogicField(field) {
                inputType = field.inputType ? field.inputType : field.type;
				<?php
				$supported_fields = '"checkbox", "radio", "select", "text", "website", "textarea", "email", "hidden", "number", "phone", "multiselect", "post_title",
					"post_tags", "post_custom_field", "post_content", "post_excerpt"';
				$supported_fields = apply_filters( self::$author . '_gateways_supported_fields', $supported_fields );
				$supported_fields = apply_filters( self::$author . '_ir123pay_supported_fields', $supported_fields );
				?>
                var supported_fields = [<?php echo $supported_fields; ?>];
                var index = jQuery.inArray(inputType, supported_fields);
                return index >= 0;
            }
        </script>
		<?php
	}

	public static function Request( $confirmation, $form, $entry, $ajax ) {

		do_action( 'gf_gateway_request_1', $confirmation, $form, $entry, $ajax );
		do_action( 'gf_ir123pay_request_1', $confirmation, $form, $entry, $ajax );

		if ( apply_filters( 'gf_ir123pay_request_return', apply_filters( 'gf_gateway_request_return', false, $confirmation, $form, $entry, $ajax ), $confirmation, $form, $entry, $ajax ) ) {
			return $confirmation;
		}

		$valid_checker = $confirmation == 'valid_checker';
		$custom        = $confirmation == 'custom';

		global $current_user;
		$user_id   = 0;
		$user_name = __( 'مهمان', 'gravityformsir123pay' );

		if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		}

		if ( ! $valid_checker ) {

			$entry_id = $entry['id'];

			if ( ! $custom ) {

				if ( RGForms::post( "gform_submit" ) != $form['id'] ) {
					return $confirmation;
				}

				$config = self::get_active_config( $form );
				if ( empty( $config ) ) {
					return $confirmation;
				}

				unset( $entry["payment_status"] );
				unset( $entry["payment_amount"] );
				unset( $entry["payment_date"] );
				unset( $entry["transaction_id"] );
				unset( $entry["transaction_type"] );
				unset( $entry["is_fulfilled"] );
				GFAPI::update_entry( $entry );

				gform_update_meta( $entry['id'], 'ir123pay_feed_id', $config['id'] );
				gform_update_meta( $entry['id'], 'payment_type', 'form' );
				gform_update_meta( $entry['id'], 'payment_gateway', self::get_gname() );
				GFAPI::update_entry_property( $entry["id"], "payment_method", "ir123pay" );
				GFAPI::update_entry_property( $entry["id"], "payment_status", 'Processing' );
				GFAPI::update_entry_property( $entry["id"], "is_fulfilled", 0 );

				switch ( $config["meta"]["type"] ) {
					case "subscription" :
						GFAPI::update_entry_property( $entry["id"], "transaction_type", 2 );
						break;

					default :
						GFAPI::update_entry_property( $entry["id"], "transaction_type", 1 );
						break;
				}

				if ( GFCommon::has_post_field( $form["fields"] ) && ! empty( $config["meta"]["update_post_action2"] ) && $config["meta"]["update_post_action2"] != 'dont' ) {

					switch ( $config["meta"]["update_post_action2"] ) {

						case "publish" :
							$form['postStatus'] = 'publish';
							break;

						case "draft" :
							$form['postStatus'] = 'draft';
							break;

						case "private" :
							$form['postStatus'] = 'private';
							break;

						default :
							$form['postStatus'] = 'pending';
							break;
					}

					RGFormsModel::create_post( $form, $entry );

				}

				$Amount = self::get_order_total( $form, $entry );
				$Amount = apply_filters( self::$author . "_gform_form_gateway_price_{$form['id']}", apply_filters( self::$author . "_gform_form_gateway_price", $Amount, $form, $entry ), $form, $entry );
				$Amount = apply_filters( self::$author . "_gform_form_ir123pay_price_{$form['id']}", apply_filters( self::$author . "_gform_form_ir123pay_price", $Amount, $form, $entry ), $form, $entry );
				$Amount = apply_filters( self::$author . "_gform_gateway_price_{$form['id']}", apply_filters( self::$author . "_gform_gateway_price", $Amount, $form, $entry ), $form, $entry );
				$Amount = apply_filters( self::$author . "_gform_ir123pay_price_{$form['id']}", apply_filters( self::$author . "_gform_ir123pay_price", $Amount, $form, $entry ), $form, $entry );

				if ( empty( $Amount ) || ! $Amount || $Amount == 0 ) {
					return self::redirect_confirmation( add_query_arg( array( 'no' => 'true' ), self::Return_URL( $form['id'], $entry['id'] ) ), $ajax );
				} else {

					$Desc1 = '';
					if ( ! empty( $config["meta"]["desc_pm"] ) ) {
						$Desc1 = str_replace( array( '{entry_id}', '{form_title}', '{form_id}' ), array(
							$entry['id'],
							$form['title'],
							$form['id']
						), $config["meta"]["desc_pm"] );
					}
					$Desc2 = '';
					if ( rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_desc"] ) ) ) {
						$Desc2 = rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_desc"] ) );
					}

					if ( ! empty( $Desc1 ) && ! empty( $Desc2 ) ) {
						$Description = $Desc1 . ' - ' . $Desc2;
					} else if ( ! empty( $Desc1 ) && empty( $Desc2 ) ) {
						$Description = $Desc1;
					} else if ( ! empty( $Desc2 ) && empty( $Desc1 ) ) {
						$Description = $Desc2;
					} else {
						$Description = ' ';
					}
					$Description = sanitize_text_field( $Description );

					$name = '';
					if ( rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_name"] ) ) ) {
						$name = rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_name"] ) );
					}
					$family = '';
					if ( rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_family"] ) ) ) {
						$family = rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_family"] ) );
					}
					$Paymenter = sanitize_text_field( $name . ' ' . $family );

					$Email = '';
					if ( rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_email"] ) ) ) {
						$Email = sanitize_text_field( rgpost( 'input_' . str_replace( ".", "_", $config["meta"]["customer_fields_email"] ) ) );
					}

				}

				self::send_notification( "form_submission", $form, $entry, 'submit', $config );
			} else {

				$Amount = gform_get_meta( rgar( $entry, 'id' ), 'fbnm_part_price_' . $form['id'] );
				$Amount = apply_filters( self::$author . "_gform_custom_gateway_price_{$form['id']}", apply_filters( self::$author . "_gform_custom_gateway_price", $Amount, $form, $entry ), $form, $entry );
				$Amount = apply_filters( self::$author . "_gform_custom_ir123pay_price_{$form['id']}", apply_filters( self::$author . "_gform_custom_ir123pay_price", $Amount, $form, $entry ), $form, $entry );
				$Amount = apply_filters( self::$author . "_gform_gateway_price_{$form['id']}", apply_filters( self::$author . "_gform_gateway_price", $Amount, $form, $entry ), $form, $entry );
				$Amount = apply_filters( self::$author . "_gform_ir123pay_price_{$form['id']}", apply_filters( self::$author . "_gform_ir123pay_price", $Amount, $form, $entry ), $form, $entry );

				$Description = gform_get_meta( rgar( $entry, 'id' ), 'fbnm_part_desc_' . $form['id'] );
				$Description = apply_filters( self::$author . '_gform_ir123pay_gateway_desc_', apply_filters( self::$author . '_gform_custom_gateway_desc_', $Description, $form, $entry ), $form, $entry );

				$Paymenter = gform_get_meta( rgar( $entry, 'id' ), 'fbnm_part_name_' . $form['id'] );
				$Email     = gform_get_meta( rgar( $entry, 'id' ), 'fbnm_part_email_' . $form['id'] );


				unset( $entry["payment_status"] );
				unset( $entry["payment_amount"] );
				unset( $entry["transaction_id"] );
				unset( $entry["payment_date"] );
				unset( $entry["transaction_type"] );
				unset( $entry["is_fulfilled"] );
				$entry_id = GFAPI::add_entry( $entry );
				$entry    = RGFormsModel::get_lead( $entry_id );

				do_action( 'gf_gateway_request_add_entry', $confirmation, $form, $entry, $ajax );
				do_action( 'gf_ir123pay_request_add_entry', $confirmation, $form, $entry, $ajax );

				gform_update_meta( $entry_id, 'payment_gateway', self::get_gname() );
				gform_update_meta( $entry_id, 'payment_type', 'custom' );
				GFAPI::update_entry_property( $entry_id, "payment_method", "ir123pay" );
				GFAPI::update_entry_property( $entry_id, "payment_status", 'Processing' );
				GFAPI::update_entry_property( $entry_id, "is_fulfilled", 0 );
				GFAPI::update_entry_property( $entry_id, "transaction_type", 1 );
			}

			$callback_url = urlencode( self::Return_URL_Ir123pay( $form['id'], $entry_id ) );
		}


		do_action( 'gf_gateway_request_2', $confirmation, $form, $entry, $ajax );
		do_action( 'gf_ir123pay_request_2', $confirmation, $form, $entry, $ajax );

		$currency = GFCommon::get_currency();

		$amount = ( $currency != 'IRR' && ! $custom ) ? $Amount * 10 : $Amount;

		try {

			$merchant_id = self::get_merchant_id();

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/create/payment' );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&amount=$amount&callback_url=$callback_url" );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			$response = curl_exec( $ch );
			curl_close( $ch );

			$result = json_decode( $response );

			if ( $result->status ) {

				if ( $valid_checker ) {
					return true;
				} else {
					return self::redirect_confirmation( $result->payment_url, $ajax );
				}

			} else {
				$Message = self::Fault( ( $result->message ) );
			}
		} catch ( Exception $ex ) {
			$Message = $ex->getMessage();
		}

		if ( ! empty( $Message ) && $Message ) {

			$confirmation = $Fault_Response = $Message;

			if ( $valid_checker ) {
				return $Fault_Response;
			}

			GFAPI::update_entry_property( $entry_id, 'payment_status', 'Failed' );
			RGFormsModel::add_note( $entry_id, $user_id, $user_name, sprintf( __( 'خطا در اتصال به درگاه رخ داده است : %s', "gravityformsir123pay" ), $Fault_Response ) );

			if ( ! $custom ) {
				self::send_notification( "form_submission", $form, $entry, 'failed', $config );
			}
		}

		$default_anchor = 0;
		$anchor         = gf_apply_filters( 'gform_confirmation_anchor', $form['id'], $default_anchor ) ? "<a id='gf_{$form['id']}' name='gf_{$form['id']}' class='gform_anchor' ></a>" : '';
		$nl2br          = ! empty( $form['confirmation'] ) && rgar( $form['confirmation'], 'disableAutoformat' ) ? false : true;
		$cssClass       = rgar( $form, 'cssClass' );

		return $confirmation = empty( $confirmation ) ? "{$anchor} " : "{$anchor}<div id='gform_confirmation_wrapper_{$form['id']}' class='gform_confirmation_wrapper {$cssClass}'><div id='gform_confirmation_message_{$form['id']}' class='gform_confirmation_message_{$form['id']} gform_confirmation_message'>" . GFCommon::replace_variables( $confirmation, $form, $entry, false, true, $nl2br ) . '</div></div>';
	}

	public static function Verify() {

		if ( apply_filters( 'gf_gateway_ir123pay_return', apply_filters( 'gf_gateway_verify_return', false ) ) ) {
			return;
		}

		if ( ! self::is_gravityforms_supported() ) {
			return;
		}

		if ( empty( $_GET['id'] ) || empty( $_GET['lead'] ) || ! is_numeric( rgget( 'id' ) ) || ! is_numeric( rgget( 'lead' ) ) ) {
			return;
		}

		$form_id = $_GET['id'];
		$lead_id = $_GET['lead'];

		$lead = RGFormsModel::get_lead( $lead_id );

		if ( isset( $lead["payment_method"] ) && $lead["payment_method"] == 'ir123pay' ) {

			$form = RGFormsModel::get_form_meta( $form_id );

			$payment_type = gform_get_meta( $lead["id"], 'payment_type' );
			gform_delete_meta( $lead['id'], 'payment_type' );

			if ( $payment_type != 'custom' ) {
				$config = self::get_config_by_entry( $lead );
				if ( empty( $config ) ) {
					return;
				}
			} else {
				$config = apply_filters( self::$author . '_gf_ir123pay_config', apply_filters( self::$author . '_gf_gateway_config', array(), $form, $lead ), $form, $lead );
			}


			if ( ! empty( $lead["payment_date"] ) ) {
				return;
			}

			global $current_user;
			$user_id   = 0;
			$user_name = __( "مهمان", "gravityformsir123pay" );
			if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
				$user_id   = $current_user->ID;
				$user_name = $user_data->display_name;
			}

			$transaction_type = 1;
			if ( ! empty( $config["meta"]["type"] ) && $config["meta"]["type"] == 'subscription' ) {
				$transaction_type = 2;
			}

			if ( $payment_type == 'custom' ) {
				$Amount = $Total = gform_get_meta( $lead["id"], 'fbnm_part_price_' . $form_id );
			} else {
				$Amount = $Total = self::get_order_total( $form, $lead );
			}
			$Total_Money = GFCommon::to_money( $Total, $lead["currency"] );
			$currency    = GFCommon::get_currency();

			$free = false;
			if ( empty( $_GET['no'] ) || $_GET['no'] != 'true' ) {

				$amount = ( $currency != 'IRR' ) ? $Amount * 10 : $Amount;

				try {

					$merchant_id = self::get_merchant_id();
					$State       = isset( $_REQUEST['State'] ) ? sanitize_text_field( $_POST['State'] ) : '';
					$RefNum      = isset( $_REQUEST['RefNum'] ) ? sanitize_text_field( $_POST['RefNum'] ) : '';

					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/verify/payment' );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&RefNum=$RefNum" );
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					$response = curl_exec( $ch );
					curl_close( $ch );

					$result = json_decode( $response );

					if ( $State != 'OK' ) {
						$Status  = 'cancelled';
						$Message = '';
					} elseif ( $result->status AND $amount == $result->amount ) {
						$Status  = 'completed';
						$Message = '';
					} else {
						$Status  = 'failed';
						$Message = self::Fault( $result->message );
					}

				} catch ( Exception $ex ) {
					$Message = $ex->getMessage();
					$Status  = 'failed';
				}
				$Transaction_ID = ! empty( $RefNum ) ? $RefNum : '-';
			} else {
				$Status         = 'completed';
				$Message        = '';
				$Transaction_ID = apply_filters( self::$author . '_gf_rand_transaction_id', rand( 1000000000, 9999999999 ), $form, $lead );
				$free           = true;
			}

			$Status         = ! empty( $Status ) ? $Status : 'failed';
			$transaction_id = ! empty( $Transaction_ID ) ? $Transaction_ID : '';
			$transaction_id = apply_filters( self::$author . '_gf_real_transaction_id', $transaction_id, $Status, $form, $lead );

			$lead["payment_date"]     = gmdate( "Y-m-d H:i:s" );
			$lead["transaction_id"]   = $transaction_id;
			$lead["transaction_type"] = $transaction_type;

			if ( $Status == 'completed' ) {

				$lead["is_fulfilled"]   = 1;
				$lead["payment_amount"] = $Total;

				if ( $transaction_type == 2 ) {
					$lead["payment_status"] = "Active";
					if ( apply_filters( self::$author . '_gf_ir123pay_create_user', apply_filters( self::$author . '_gf_ir123pay_create_user', ( $payment_type != 'custom' ), $form, $lead ), $form, $lead ) ) {
						self::Creat_User( $form, $lead );
					}
					RGFormsModel::add_note( $lead["id"], $user_id, $user_name, __( "تغییرات اطلاعات فیلدها فقط در همین پیام ورودی اعمال خواهد شد و بر روی وضعیت کاربر تاثیری نخواهد داشت .", "gravityformsir123pay" ) );
				} else {
					$lead["payment_status"] = "Paid";
				}

				if ( $free == true ) {
					unset( $lead["payment_status"] );
					unset( $lead["payment_amount"] );
					unset( $lead["payment_method"] );
					unset( $lead["is_fulfilled"] );
					gform_delete_meta( $lead['id'], 'payment_gateway' );
					$Note = sprintf( __( 'وضعیت پرداخت : رایگان - بدون نیاز به درگاه پرداخت', "gravityformsir123pay" ) );
				} else {
					$Note = sprintf( __( 'وضعیت پرداخت : موفق - مبلغ پرداختی : %s - کد تراکنش : %s', "gravityformsir123pay" ), $Total_Money, $transaction_id );
				}

				GFAPI::update_entry( $lead );


				if ( apply_filters( self::$author . '_gf_ir123pay_post', apply_filters( self::$author . '_gf_gateway_post', ( $payment_type != 'custom' ), $form, $lead ), $form, $lead ) ) {

					$has_post = GFCommon::has_post_field( $form["fields"] ) ? true : false;

					if ( empty( $lead["post_id"] ) && $has_post ) {
						RGFormsModel::create_post( $form, $lead );
						$lead = RGFormsModel::get_lead( $lead_id );
					}

					if ( ! empty( $lead["post_id"] ) && $has_post ) {

						$post       = get_post( $lead["post_id"] );
						$old_status = $post->post_status;

						if ( ! empty( $config["meta"]["update_post_action1"] ) ) {

							switch ( $config["meta"]["update_post_action1"] ) {

								case "publish" :
									$new_status = 'publish';
									break;

								case "draft" :
									$new_status = 'draft';
									break;

								case "pending" :
									$new_status = 'pending';
									break;

								case "private" :
									$new_status = 'private';
									break;

								default:
									$new_status = rgar( $form, 'postStatus' );
									break;
							}
						} else {
							$new_status = rgar( $form, 'postStatus' );
						}

						if ( $new_status != $old_status ) {
							global $wpdb;
							$wpdb->update( $wpdb->posts, array( 'post_status' => $new_status ), array( 'ID' => $post->ID ) );
							clean_post_cache( $post->ID );
							wp_transition_post_status( $new_status, $old_status, $post );
						}
					}
				}

				if ( class_exists( 'GFFBNMSMS' ) && method_exists( 'GFFBNMSMS', 'sendsms_By_FBNM' ) ) {
					GFFBNMSMS::sendsms_By_FBNM( $lead, $form );
				}

				do_action( "gform_ir123pay_fulfillment", $lead, $config, $transaction_id, $Total );
				do_action( "gform_gateway_fulfillment", $lead, $config, $transaction_id, $Total );
				do_action( "gform_paypal_fulfillment", $lead, $config, $transaction_id, $Total );
			} else if ( $Status == 'cancelled' ) {
				$lead["payment_status"] = "Cancelled";
				$lead["payment_amount"] = 0;
				$lead["is_fulfilled"]   = 0;
				GFAPI::update_entry( $lead );

				$Note = sprintf( __( 'وضعیت پرداخت : منصرف شده - مبلغ قابل پرداخت : %s - کد تراکنش : %s', "gravityformsir123pay" ), $Total_Money, $transaction_id );
			} else {
				$lead["payment_status"] = "Failed";
				$lead["payment_amount"] = 0;
				$lead["is_fulfilled"]   = 0;
				GFAPI::update_entry( $lead );

				$Note = sprintf( __( 'وضعیت پرداخت : ناموفق - مبلغ قابل پرداخت : %s - کد تراکنش : %s - علت خطا : %s', "gravityformsir123pay" ), $Total_Money, $transaction_id, $Message );
			}

			$lead = RGFormsModel::get_lead( $lead_id );
			RGFormsModel::add_note( $lead["id"], $user_id, $user_name, $Note );
			do_action( 'gform_post_payment_status', $config, $lead, strtolower( $Status ), $transaction_id, '', $Total, '', '' );
			do_action( 'gform_post_payment_status_' . __CLASS__, $config, $form, $lead, strtolower( $Status ), $transaction_id, '', $Total, '', '' );


			if ( apply_filters( self::$author . '_gf_ir123pay_verify', apply_filters( self::$author . '_gf_gateway_verify', ( $payment_type != 'custom' ), $form, $lead ), $form, $lead ) ) {
				self::send_notification( "form_submission", $form, $lead, strtolower( $Status ), $config );
				self::confirmation( $form, $lead, '', strtolower( $Status ), $Message, $config );
			}
		}
	}

	private static function Fault( $err_code ) {
		return $err_code;
	}

}