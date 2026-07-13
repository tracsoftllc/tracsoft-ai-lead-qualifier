<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Notion_Client {
	public static function create_hot_lead( $session, $contact, $summary, $settings ) {
		if ( empty( $settings['create_notion_hot'] ) ) {
			return array( 'status' => 'disabled', 'error' => '' );
		}
		if ( empty( $settings['notion_api_key'] ) || empty( $settings['notion_database_id'] ) ) {
			return array( 'status' => 'skipped_missing_settings', 'error' => 'Notion API key or database ID is missing.' );
		}
		$schema = self::database_schema( $settings );
		if ( is_wp_error( $schema ) ) {
			return array( 'status' => 'failed', 'error' => $schema->get_error_message() );
		}
		$properties = self::build_properties( $session, $contact, $summary, $settings, $schema );
		$response = wp_remote_post(
			'https://api.notion.com/v1/pages',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['notion_api_key'],
					'Content-Type' => 'application/json',
					'Notion-Version' => '2022-06-28',
				),
				'body' => wp_json_encode(
					array(
						'parent' => array( 'database_id' => $settings['notion_database_id'] ),
						'properties' => $properties,
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'status' => 'failed', 'error' => $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array( 'status' => 'failed', 'error' => wp_remote_retrieve_body( $response ) );
		}
		return array( 'status' => 'created', 'error' => '' );
	}

	public static function test_connection( $settings ) {
		if ( empty( $settings['notion_api_key'] ) || empty( $settings['notion_database_id'] ) ) {
			return new WP_Error( 'missing_settings', 'Notion API key or database ID is missing.' );
		}
		$response = wp_remote_get(
			'https://api.notion.com/v1/databases/' . rawurlencode( $settings['notion_database_id'] ),
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['notion_api_key'],
					'Notion-Version' => '2022-06-28',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300 ? true : new WP_Error( 'notion_error', wp_remote_retrieve_body( $response ) );
	}

	private static function database_schema( $settings ) {
		$response = wp_remote_get(
			'https://api.notion.com/v1/databases/' . rawurlencode( $settings['notion_database_id'] ),
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['notion_api_key'],
					'Notion-Version' => '2022-06-28',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'notion_schema_failed', wp_remote_retrieve_body( $response ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $data['properties'] ?? array();
	}

	private static function build_properties( $session, $contact, $summary, $settings, $schema ) {
		$answers = $session['answers'] ?? array();
		$scoring = $session['scoring'] ?? array();
		$score = $scoring['total_score'] ?? 0;
		$breakdown = $scoring['scores'] ?? array();
		$transcript = self::format_transcript( $session['transcript'] ?? array() );
		$notes = "Service interest: " . ( $answers['service_bucket'] ?? '' ) . "\nLead score and status: $score / Hot Lead\nBudget fit: " . ( $answers['budget'] ?? '' ) . "\nTimeline: " . ( $answers['timeline'] ?? '' ) . "\nPain point summary: " . ( $answers['pain_point'] ?? '' ) . "\nDecision role: " . ( $answers['decision_role'] ?? '' ) . "\nRelationship fit: " . ( $answers['relationship_fit'] ?? '' ) . "\nFull conversation summary: $summary\nRecommended next action: Call and/or email this lead as soon as possible.";
		$values = array(
			'status' => 'Hot Lead',
			'priority' => 'High',
			'discovery_notes' => $notes,
			'company_name' => $contact['company_name'],
			'contact_name' => $contact['name'],
			'email' => $contact['email'],
			'phone' => $contact['phone'],
			'last_contact' => gmdate( 'Y-m-d' ),
			'lead_source' => 'AI website qualification bot',
			'lead_score' => (string) $score,
			'service_bucket' => $answers['service_bucket'] ?? '',
			'timeline' => $answers['timeline'] ?? '',
			'budget_range' => $answers['budget'] ?? '',
			'decision_role' => $answers['decision_role'] ?? '',
			'relationship_fit' => $answers['relationship_fit'] ?? '',
			'recommended_next_action' => 'Call and/or email this lead as soon as possible.',
			'conversation_transcript' => $transcript . "\n\nScore breakdown:\n" . print_r( $breakdown, true ),
		);
		$properties = array();
		foreach ( $settings['notion_mapping'] as $plugin_key => $notion_property ) {
			if ( empty( $notion_property ) || ! isset( $values[ $plugin_key ] ) || ! isset( $schema[ $notion_property ] ) ) {
				continue;
			}
			$properties[ $notion_property ] = self::notion_property( $values[ $plugin_key ], $schema[ $notion_property ]['type'] ?? 'rich_text' );
		}
		if ( ! self::has_title_property( $properties, $schema ) ) {
			foreach ( $schema as $name => $definition ) {
				if ( 'title' === ( $definition['type'] ?? '' ) ) {
					$properties[ $name ] = self::notion_property( $contact['company_name'] ?: $contact['name'], 'title' );
					break;
				}
			}
		}
		return $properties;
	}

	private static function has_title_property( $properties, $schema ) {
		foreach ( $properties as $name => $value ) {
			if ( isset( $schema[ $name ] ) && 'title' === ( $schema[ $name ]['type'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	private static function notion_property( $value, $type ) {
		$value = (string) $value;
		$short = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 1900 ) : substr( $value, 0, 1900 );
		switch ( $type ) {
			case 'title':
				return array( 'title' => array( array( 'text' => array( 'content' => $short ) ) ) );
			case 'email':
				return array( 'email' => sanitize_email( $value ) );
			case 'phone_number':
				return array( 'phone_number' => sanitize_text_field( $value ) );
			case 'number':
				return array( 'number' => is_numeric( $value ) ? 0 + $value : null );
			case 'date':
				return array( 'date' => array( 'start' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : gmdate( 'Y-m-d' ) ) );
			case 'select':
				return array( 'select' => array( 'name' => $short ) );
			case 'status':
				return array( 'status' => array( 'name' => $short ) );
			case 'url':
				return array( 'url' => esc_url_raw( $value ) );
			case 'rich_text':
			default:
				return array( 'rich_text' => array( array( 'text' => array( 'content' => $short ) ) ) );
		}
	}

	public static function format_transcript( $transcript ) {
		$lines = array();
		foreach ( $transcript as $entry ) {
			$lines[] = ucfirst( sanitize_text_field( $entry['role'] ?? 'message' ) ) . ': ' . sanitize_textarea_field( $entry['message'] ?? '' );
		}
		return implode( "\n", $lines );
	}
}
