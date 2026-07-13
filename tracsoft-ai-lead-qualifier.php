<?php
/**
 * Plugin Name: Tracsoft AI Lead Qualifier
 * Description: AI-assisted lead qualification chatbot for Tracsoft.com.
 * Version: 1.0.1
 * Author: Tracsoft
 * Text Domain: tracsoft-ai-lead-qualifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRACSOFT_LB_VERSION', '1.0.1' );
define( 'TRACSOFT_LB_FILE', __FILE__ );
define( 'TRACSOFT_LB_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRACSOFT_LB_URL', plugin_dir_url( __FILE__ ) );
define( 'TRACSOFT_LB_OPTION', 'tracsoft_lb_settings' );
define( 'TRACSOFT_LB_SESSION_PREFIX', 'tracsoft_lb_session_' );

require_once TRACSOFT_LB_DIR . 'includes/class-settings.php';
require_once TRACSOFT_LB_DIR . 'includes/class-scorer.php';
require_once TRACSOFT_LB_DIR . 'includes/class-openai-client.php';
require_once TRACSOFT_LB_DIR . 'includes/class-notion-client.php';
require_once TRACSOFT_LB_DIR . 'includes/class-email-alerts.php';
require_once TRACSOFT_LB_DIR . 'includes/class-logger.php';
require_once TRACSOFT_LB_DIR . 'includes/class-rest-api.php';
require_once TRACSOFT_LB_DIR . 'includes/class-chatbot.php';
require_once TRACSOFT_LB_DIR . 'includes/class-admin.php';
require_once TRACSOFT_LB_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Tracsoft_LB_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Tracsoft_LB_Plugin', 'deactivate' ) );

Tracsoft_LB_Plugin::instance()->init();
