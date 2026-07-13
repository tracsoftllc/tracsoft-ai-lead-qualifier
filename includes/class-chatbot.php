<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Chatbot {
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ) );
	}

	public static function should_display() {
		$settings = Tracsoft_LB_Settings::get();
		if ( empty( $settings['enabled'] ) || is_admin() ) {
			return false;
		}
		$current_id = get_queried_object_id();
		$include = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $settings['include_pages'] ) ) );
		$exclude = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $settings['exclude_pages'] ) ) );
		if ( in_array( $current_id, $exclude, true ) ) {
			return false;
		}
		if ( empty( $settings['display_all_pages'] ) && ! in_array( $current_id, $include, true ) ) {
			return false;
		}
		return true;
	}

	public static function enqueue() {
		if ( ! self::should_display() ) {
			return;
		}
		$settings = Tracsoft_LB_Settings::get();
		wp_enqueue_style( 'tracsoft-lb-chatbot', TRACSOFT_LB_URL . 'assets/css/chatbot.css', array(), TRACSOFT_LB_VERSION );
		wp_enqueue_script( 'tracsoft-lb-chatbot', TRACSOFT_LB_URL . 'assets/js/chatbot.js', array(), TRACSOFT_LB_VERSION, true );
		wp_localize_script( 'tracsoft-lb-chatbot', 'TracsoftLeadBot', array(
			'restUrl' => esc_url_raw( rest_url( 'tracsoft-lead-bot/v1' ) ),
			'title' => $settings['widget_title'],
			'buttonLabel' => $settings['button_label'],
			'siteIconUrl' => esc_url_raw( TRACSOFT_LB_URL . 'assets/img/tracsoft-icon-white.png' ),
			'privacyNote' => $settings['privacy_note'],
			'position' => $settings['position'],
			'primaryColor' => $settings['primary_color'],
			'secondaryColor' => $settings['secondary_color'],
		) );
	}

	public static function render() {
		if ( ! self::should_display() ) {
			return;
		}
		echo '<div id="tracsoft-lead-bot-root" class="tracsoft-lead-bot-root" aria-live="polite"></div>';
	}
}
