<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_tracsoft_lb_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_tracsoft_lb_test_openai', array( __CLASS__, 'test_openai' ) );
		add_action( 'admin_post_tracsoft_lb_test_notion', array( __CLASS__, 'test_notion' ) );
		add_action( 'admin_post_tracsoft_lb_test_email', array( __CLASS__, 'test_email' ) );
		add_action( 'admin_post_tracsoft_lb_reset_prompt', array( __CLASS__, 'reset_prompt' ) );
	}

	public static function menu() {
		add_menu_page( 'Tracsoft Lead Bot', 'Tracsoft Lead Bot', 'manage_options', 'tracsoft-lead-bot', array( __CLASS__, 'page' ), 'dashicons-format-chat', 56 );
	}

	public static function enqueue( $hook ) {
		if ( 'toplevel_page_tracsoft-lead-bot' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'tracsoft-lb-admin', TRACSOFT_LB_URL . 'assets/css/admin.css', array( 'wp-color-picker' ), TRACSOFT_LB_VERSION );
		wp_enqueue_script( 'tracsoft-lb-admin', TRACSOFT_LB_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), TRACSOFT_LB_VERSION, true );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tracsoft-ai-lead-qualifier' ) );
		}
		$settings = Tracsoft_LB_Settings::get();
		$active_tab = sanitize_key( $_GET['tab'] ?? 'general' );
		$logs = 'logs' === $active_tab ? Tracsoft_LB_Logger::latest( 100 ) : array();
		require TRACSOFT_LB_DIR . 'templates/admin-page.php';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'tracsoft-ai-lead-qualifier' ) );
		}
		check_admin_referer( 'tracsoft_lb_save' );
		$settings = Tracsoft_LB_Settings::get();
		$posted = wp_unslash( $_POST['tracsoft_lb'] ?? array() );
		Tracsoft_LB_Settings::update( array_replace_recursive( $settings, $posted ) );
		self::redirect_with_notice( 'saved' );
	}

	public static function test_openai() {
		self::guard_action( 'tracsoft_lb_test_openai' );
		$result = Tracsoft_LB_OpenAI_Client::test_connection( Tracsoft_LB_Settings::get() );
		self::redirect_with_notice( is_wp_error( $result ) ? 'openai_failed' : 'openai_ok', is_wp_error( $result ) ? $result->get_error_message() : '', 'openai' );
	}

	public static function test_notion() {
		self::guard_action( 'tracsoft_lb_test_notion' );
		$result = Tracsoft_LB_Notion_Client::test_connection( Tracsoft_LB_Settings::get() );
		self::redirect_with_notice( is_wp_error( $result ) ? 'notion_failed' : 'notion_ok', is_wp_error( $result ) ? $result->get_error_message() : '', 'notion' );
	}

	public static function test_email() {
		self::guard_action( 'tracsoft_lb_test_email' );
		$result = Tracsoft_LB_Email_Alerts::send_test( Tracsoft_LB_Settings::get() );
		self::redirect_with_notice( is_wp_error( $result ) ? 'email_failed' : 'email_ok', is_wp_error( $result ) ? $result->get_error_message() : '', 'email' );
	}

	public static function reset_prompt() {
		self::guard_action( 'tracsoft_lb_reset_prompt' );
		$settings = Tracsoft_LB_Settings::get();
		$settings['system_prompt'] = Tracsoft_LB_Settings::default_prompt();
		Tracsoft_LB_Settings::update( $settings );
		self::redirect_with_notice( 'prompt_reset' );
	}

	private static function guard_action( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'tracsoft-ai-lead-qualifier' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect_with_notice( $notice, $message = '', $tab = '' ) {
		if ( $message ) {
			set_transient( 'tracsoft_lb_admin_notice_' . get_current_user_id(), sanitize_textarea_field( $message ), MINUTE_IN_SECONDS );
		}
		$args = array( 'page' => 'tracsoft-lead-bot', 'notice' => $notice );
		if ( $tab ) {
			$args['tab'] = sanitize_key( $tab );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
