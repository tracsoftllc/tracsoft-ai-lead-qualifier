<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Logger {
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'tracsoft_lead_bot_logs';
	}

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			lead_status varchar(50) NOT NULL DEFAULT '',
			lead_score int(11) NOT NULL DEFAULT 0,
			service_bucket varchar(100) NOT NULL DEFAULT '',
			contact_name varchar(255) NOT NULL DEFAULT '',
			company_name varchar(255) NOT NULL DEFAULT '',
			email varchar(255) NOT NULL DEFAULT '',
			phone varchar(100) NOT NULL DEFAULT '',
			summary longtext NULL,
			score_breakdown_json longtext NULL,
			transcript_json longtext NULL,
			notion_status varchar(100) NOT NULL DEFAULT '',
			email_status varchar(100) NOT NULL DEFAULT '',
			errors_json longtext NULL,
			PRIMARY KEY  (id)
		) $charset;";
		dbDelta( $sql );
	}

	public static function insert( $data ) {
		global $wpdb;
		self::create_table();
		$defaults = array(
			'created_at' => current_time( 'mysql' ),
			'lead_status' => '',
			'lead_score' => 0,
			'service_bucket' => '',
			'contact_name' => '',
			'company_name' => '',
			'email' => '',
			'phone' => '',
			'summary' => '',
			'score_breakdown_json' => '',
			'transcript_json' => '',
			'notion_status' => '',
			'email_status' => '',
			'errors_json' => '',
		);
		$wpdb->insert( self::table_name(), wp_parse_args( $data, $defaults ) );
		return $wpdb->insert_id;
	}

	public static function latest( $limit = 50 ) {
		global $wpdb;
		self::create_table();
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY created_at DESC LIMIT %d', absint( $limit ) ) );
	}
}
