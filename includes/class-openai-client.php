<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_OpenAI_Client {
	public static function is_configured( $settings ) {
		return ! empty( $settings['openai_api_key'] );
	}

	public static function summarize_hot_lead( $session, $settings ) {
		if ( ! self::is_configured( $settings ) ) {
			return self::local_summary( $session );
		}
		$body = array(
			'model' => $settings['openai_model'],
			'temperature' => (float) $settings['openai_temperature'],
			'max_tokens' => (int) $settings['openai_max_tokens'],
			'messages' => array(
				array( 'role' => 'system', 'content' => $settings['system_prompt'] . "\n\nReturn a concise internal hot-lead summary only. Do not include markdown." ),
				array( 'role' => 'user', 'content' => wp_json_encode( $session ) ),
			),
		);
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['openai_api_key'],
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::local_summary( $session );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return sanitize_textarea_field( $data['choices'][0]['message']['content'] ?? self::local_summary( $session ) );
	}

	public static function test_connection( $settings ) {
		if ( ! self::is_configured( $settings ) ) {
			return new WP_Error( 'missing_key', 'OpenAI API key is missing.' );
		}
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $settings['openai_api_key'], 'Content-Type' => 'application/json' ),
				'body' => wp_json_encode( array( 'model' => $settings['openai_model'], 'messages' => array( array( 'role' => 'user', 'content' => 'Reply OK.' ) ), 'max_tokens' => 5 ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300 ? true : new WP_Error( 'openai_error', wp_remote_retrieve_body( $response ) );
	}

	public static function local_summary( $session ) {
		$answers = $session['answers'] ?? array();
		return sprintf(
			'Service: %s. Pain point: %s. Timeline: %s. Budget: %s. Decision role: %s. Relationship fit: %s.',
			$answers['service_bucket'] ?? 'unknown',
			$answers['pain_point'] ?? 'not provided',
			$answers['timeline'] ?? 'not provided',
			$answers['budget'] ?? 'not provided',
			$answers['decision_role'] ?? 'not provided',
			$answers['relationship_fit'] ?? 'not provided'
		);
	}
}
