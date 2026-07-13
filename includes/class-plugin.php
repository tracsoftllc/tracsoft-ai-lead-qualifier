<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Plugin {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		Tracsoft_LB_Admin::init();
		Tracsoft_LB_Chatbot::init();
		Tracsoft_LB_REST_API::init();
		Tracsoft_LB_Updater::init();
	}

	public static function activate() {
		$existing = get_option( TRACSOFT_LB_OPTION );
		if ( ! is_array( $existing ) ) {
			add_option( TRACSOFT_LB_OPTION, Tracsoft_LB_Settings::defaults(), '', false );
		} else {
			update_option( TRACSOFT_LB_OPTION, Tracsoft_LB_Settings::merge_defaults( $existing ), false );
		}
		Tracsoft_LB_Logger::create_table();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
