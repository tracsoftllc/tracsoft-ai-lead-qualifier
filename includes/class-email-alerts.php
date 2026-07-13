<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Email_Alerts {
	private static $last_mail_error = '';

	public static function send_hot_lead( $session, $contact, $summary, $notion_status, $settings ) {
		if ( empty( $settings['email_hot'] ) ) {
			return array( 'status' => 'disabled', 'error' => '' );
		}
		$answers = $session['answers'] ?? array();
		$scoring = $session['scoring'] ?? array();
		$tokens = array(
			'{{lead_score}}' => (string) ( $scoring['total_score'] ?? 0 ),
			'{{service_bucket}}' => $answers['service_bucket'] ?? '',
			'{{contact_name}}' => $contact['name'],
			'{{company_name}}' => $contact['company_name'],
			'{{email}}' => $contact['email'],
			'{{phone}}' => $contact['phone'],
			'{{summary}}' => $summary,
			'{{budget_range}}' => $answers['budget'] ?? '',
			'{{timeline}}' => $answers['timeline'] ?? '',
			'{{decision_role}}' => $answers['decision_role'] ?? '',
			'{{relationship_fit}}' => $answers['relationship_fit'] ?? '',
			'{{score_breakdown}}' => print_r( $scoring['scores'] ?? array(), true ),
			'{{transcript}}' => Tracsoft_LB_Notion_Client::format_transcript( $session['transcript'] ?? array() ),
			'{{notion_status}}' => $notion_status,
		);
		$subject = strtr( $settings['email_subject'], $tokens );
		$body = strtr( $settings['email_body'], $tokens );
		if ( false !== strpos( $notion_status, 'failed' ) ) {
			$body .= "\n\nWarning: Notion sync failed.";
		}
		$result = self::send_with_wordpress_mail( sanitize_email( $settings['email_recipient'] ), sanitize_text_field( $subject ), $body, $settings );
		return $result['sent'] ? array( 'status' => 'sent', 'error' => '' ) : array( 'status' => 'failed', 'error' => $result['error'] );
	}

	public static function send_test( $settings ) {
		$result = self::send_with_wordpress_mail(
			sanitize_email( $settings['email_recipient'] ),
			'Tracsoft Lead Bot Test Email',
			"This is a test email from the Tracsoft AI Lead Qualifier.\n\nThis message was sent using the built-in WordPress wp_mail() system.",
			$settings
		);
		return $result['sent'] ? true : new WP_Error( 'email_failed', $result['error'] );
	}

	public static function capture_mail_error( $error ) {
		if ( is_wp_error( $error ) ) {
			self::$last_mail_error = $error->get_error_message();
			$data = $error->get_error_data();
			if ( ! empty( $data ) ) {
				self::$last_mail_error .= ' ' . wp_json_encode( $data );
			}
		}
	}

	private static function send_with_wordpress_mail( $to, $subject, $body, $settings ) {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return array( 'sent' => false, 'error' => 'The email recipient is missing or invalid.' );
		}

		self::$last_mail_error = '';
		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_mail_error' ) );
		$sent = wp_mail( $to, $subject, $body, self::headers( $settings ) );
		remove_action( 'wp_mail_failed', array( __CLASS__, 'capture_mail_error' ) );

		if ( $sent ) {
			return array( 'sent' => true, 'error' => '' );
		}

		return array(
			'sent' => false,
			'error' => self::$last_mail_error ? self::$last_mail_error : 'WordPress wp_mail() returned false. The site may need a working mail transport or SMTP configuration.',
		);
	}

	private static function headers( $settings ) {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$from_email = sanitize_email( $settings['email_from_email'] ?? '' );
		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			$from_name = sanitize_text_field( $settings['email_from_name'] ?? get_bloginfo( 'name' ) );
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}
		return $headers;
	}
}
