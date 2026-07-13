<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_REST_API {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( 'tracsoft-lead-bot/v1', '/start', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'start' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'tracsoft-lead-bot/v1', '/message', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'message' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( 'tracsoft-lead-bot/v1', '/hot-lead-contact', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'hot_lead_contact' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function start() {
		$settings = Tracsoft_LB_Settings::get();
		$session_id = wp_generate_uuid4();
		$session = array(
			'id' => $session_id,
			'stage' => 'service_selection',
			'answers' => array(),
			'transcript' => array(),
			'scoring' => array(),
			'created_at' => time(),
		);
		self::add_transcript( $session, 'bot', $settings['flow']['opening_question'] );
		self::save_session( $session_id, $session );
		return rest_ensure_response( array(
			'session_id' => $session_id,
			'bot_reply' => $settings['flow']['opening_question'],
			'quick_replies' => array_values( $settings['flow']['service_labels'] ),
			'conversation_stage' => 'service_selection',
			'lead_status' => 'unknown',
			'score' => 0,
		) );
	}

	public static function message( WP_REST_Request $request ) {
		$settings = Tracsoft_LB_Settings::get();
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
		$message = sanitize_textarea_field( $request->get_param( 'message' ) );
		$selected = sanitize_text_field( $request->get_param( 'selected_option' ) );
		$session = self::get_session( $session_id );
		if ( ! $session ) {
			return new WP_REST_Response( array( 'message' => 'Conversation expired. Please start again.' ), 400 );
		}
		$user_text = $selected ?: $message;
		self::add_transcript( $session, 'user', $user_text );
		$faq = self::maybe_faq_response( $session, $user_text, $settings );
		if ( $faq ) {
			self::add_transcript( $session, 'bot', $faq['bot_reply'] );
			self::save_session( $session_id, $session );
			return rest_ensure_response( $faq );
		}
		$response = self::advance( $session, $user_text, $settings );
		self::add_transcript( $session, 'bot', $response['bot_reply'] );
		self::save_session( $session_id, $session );
		return rest_ensure_response( $response );
	}

	public static function hot_lead_contact( WP_REST_Request $request ) {
		$settings = Tracsoft_LB_Settings::get();
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
		$session = self::get_session( $session_id );
		if ( ! $session || 'hot_contact_collection' !== ( $session['stage'] ?? '' ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Hot lead contact collection is not active for this conversation.' ), 400 );
		}
		$contact = array(
			'name' => sanitize_text_field( $request->get_param( 'name' ) ),
			'company_name' => sanitize_text_field( $request->get_param( 'company_name' ) ),
			'email' => sanitize_email( $request->get_param( 'email' ) ),
			'phone' => sanitize_text_field( $request->get_param( 'phone' ) ),
		);
		if ( empty( $contact['name'] ) || empty( $contact['company_name'] ) || empty( $contact['email'] ) || empty( $contact['phone'] ) || ! is_email( $contact['email'] ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Please provide name, company name, a valid email, and phone number.' ), 400 );
		}
		self::add_transcript( $session, 'user', 'Provided hot lead contact details.' );
		$summary = Tracsoft_LB_OpenAI_Client::summarize_hot_lead( $session, $settings );
		$notion = Tracsoft_LB_Notion_Client::create_hot_lead( $session, $contact, $summary, $settings );
		$email = Tracsoft_LB_Email_Alerts::send_hot_lead( $session, $contact, $summary, $notion['status'], $settings );
		$session['stage'] = 'routed';
		$session['contact'] = $contact;
		self::save_session( $session_id, $session );
		$errors = array();
		if ( ! empty( $notion['error'] ) ) {
			$errors['notion'] = $notion['error'];
		}
		if ( ! empty( $email['error'] ) ) {
			$errors['email'] = $email['error'];
		}
		Tracsoft_LB_Logger::insert( array(
			'lead_status' => 'hot',
			'lead_score' => (int) ( $session['scoring']['total_score'] ?? 0 ),
			'service_bucket' => $session['answers']['service_bucket'] ?? '',
			'contact_name' => $contact['name'],
			'company_name' => $contact['company_name'],
			'email' => $contact['email'],
			'phone' => $contact['phone'],
			'summary' => $summary,
			'score_breakdown_json' => wp_json_encode( $session['scoring']['scores'] ?? array() ),
			'transcript_json' => wp_json_encode( $session['transcript'] ?? array() ),
			'notion_status' => $notion['status'],
			'email_status' => $email['status'],
			'errors_json' => wp_json_encode( $errors ),
		) );
		$reply = self::replace_urls( $settings['flow']['hot_thank_you'], $settings );
		self::add_transcript( $session, 'bot', $reply );
		self::save_session( $session_id, $session );
		return rest_ensure_response( array( 'success' => true, 'message' => $reply ) );
	}

	private static function advance( &$session, $user_text, $settings ) {
		$flow = $settings['flow'];
		switch ( $session['stage'] ) {
			case 'service_selection':
				$service = self::service_from_option( $user_text, $flow['service_labels'] );
				$session['answers']['service_bucket'] = $service;
				$session['stage'] = 'pain_point';
				return self::response( $flow['pain_questions'][ $service ] ?? $flow['pain_questions']['unsure'], array(), 'pain_point', 'unknown', 0 );
			case 'pain_point':
				$session['answers']['pain_point'] = $user_text;
				if ( 'unsure' === ( $session['answers']['service_bucket'] ?? 'unsure' ) ) {
					$classified = Tracsoft_LB_Scorer::classify_service_from_text( $user_text );
					$session['answers']['service_bucket'] = $classified;
				}
				$session['stage'] = 'timeline';
				return self::response( $flow['timeline_question'], array( 'Ready now / within 30 days', '1-3 months', '3-6 months', '6+ months / just researching', 'Not sure yet' ), 'timeline', 'unknown', 0 );
			case 'timeline':
				$session['answers']['timeline'] = $user_text;
				$session['stage'] = 'budget';
				$service = $session['answers']['service_bucket'] ?? 'unsure';
				return self::response( $flow['budget_questions'][ $service ] ?? $flow['budget_questions']['unsure'], self::budget_replies( $service ), 'budget', 'unknown', 0 );
			case 'budget':
				$session['answers']['budget'] = $user_text;
				$session['stage'] = 'decision_role';
				return self::response( $flow['decision_question'], array( "I'm the final decision-maker", "I'm a strong influencer / gathering info for leadership", "I'm helping gather information", 'Not sure' ), 'decision_role', 'unknown', 0 );
			case 'decision_role':
				$session['answers']['decision_role'] = $user_text;
				$session['stage'] = 'relationship_fit';
				return self::response( $flow['relationship_question'], array( 'Long-term partner', 'Specific project now, but open to ongoing support', 'One-time project only', 'Quick fix / lowest-price option' ), 'relationship_fit', 'unknown', 0 );
			case 'relationship_fit':
				$session['answers']['relationship_fit'] = $user_text;
				$session['scoring'] = Tracsoft_LB_Scorer::score( $session, $settings );
				return self::routing_response( $session, $settings );
			default:
				return self::response( self::replace_urls( $flow['qualified_message'], $settings ), array(), 'routed', $session['scoring']['lead_status'] ?? 'unknown', $session['scoring']['total_score'] ?? 0, $settings['consultation_url'] );
		}
	}

	private static function routing_response( &$session, $settings ) {
		$status = $session['scoring']['lead_status'];
		$score = $session['scoring']['total_score'];
		$flow = $settings['flow'];
		if ( 'hot' === $status && ! empty( $settings['collect_hot_contact'] ) ) {
			$session['stage'] = 'hot_contact_collection';
			return self::response( $flow['hot_message'], array(), 'hot_contact_collection', 'hot', $score );
		}
		$session['stage'] = 'routed';
		if ( 'qualified' === $status || 'hot' === $status ) {
			return self::response( self::replace_urls( $flow['qualified_message'], $settings ), array(), 'routed', $status, $score, $settings['consultation_url'] );
		}
		if ( 'nurture' === $status ) {
			return self::response( self::replace_urls( $flow['nurture_message'], $settings ), array(), 'routed', $status, $score, $settings['newsletter_url'] );
		}
		return self::response( self::replace_urls( $flow['low_fit_message'], $settings ), array(), 'routed', $status, $score, $settings['newsletter_url'] );
	}

	private static function response( $reply, $quick_replies, $stage, $status, $score, $routing_url = '' ) {
		return array(
			'bot_reply' => $reply,
			'quick_replies' => $quick_replies,
			'conversation_stage' => $stage,
			'lead_status' => $status,
			'score' => (int) $score,
			'routing_url' => $routing_url,
		);
	}

	private static function maybe_faq_response( $session, $user_text, $settings ) {
		if ( in_array( $session['stage'] ?? '', array( 'hot_contact_collection', 'routed' ), true ) ) {
			return false;
		}
		$text = strtolower( $user_text );
		$is_question = false !== strpos( $text, '?' ) || preg_match( '/^\s*(what|can|do you|does tracsoft|does|how much|cost|price|why|who)\b/', $text );
		if ( ! $is_question ) {
			return false;
		}
		$snippet = self::knowledge_match( $text, $settings['knowledge'] );
		if ( ! $snippet ) {
			return false;
		}
		$prompt = self::current_prompt( $session, $settings );
		return self::response( $snippet . "\n\n" . $prompt['message'], $prompt['quick_replies'], $session['stage'], 'unknown', $session['scoring']['total_score'] ?? 0 );
	}

	private static function knowledge_match( $text, $knowledge ) {
		$map = array(
			'Website Development' => array( 'website', 'web', 'redesign', 'seo' ),
			'Marketing Services' => array( 'marketing', 'social', 'ads', 'content', 'email', 'seo' ),
			'AI Training' => array( 'ai', 'training', 'workshop', 'consulting' ),
			'Custom Software' => array( 'software', 'automation', 'workflow', 'portal' ),
			'Ideal Client' => array( 'clients', 'work best', 'fit' ),
			'Differentiator' => array( 'why', 'different', 'work with' ),
			'Tracsoft Overview' => array( 'what does tracsoft', 'what do you do', 'tracsoft do' ),
		);
		foreach ( $map as $title => $terms ) {
			foreach ( $terms as $term ) {
				if ( false !== strpos( $text, $term ) ) {
					foreach ( $knowledge as $item ) {
						if ( ! empty( $item['enabled'] ) && strtolower( $item['title'] ) === strtolower( $title ) ) {
							return $item['content'];
						}
					}
				}
			}
		}
		return false;
	}

	private static function current_prompt( $session, $settings ) {
		$stage = $session['stage'] ?? 'service_selection';
		$flow = $settings['flow'];
		$service = $session['answers']['service_bucket'] ?? 'unsure';
		if ( 'service_selection' === $stage ) {
			return array( 'message' => $flow['opening_question'], 'quick_replies' => array_values( $flow['service_labels'] ) );
		}
		if ( 'pain_point' === $stage ) {
			return array( 'message' => $flow['pain_questions'][ $service ] ?? $flow['pain_questions']['unsure'], 'quick_replies' => array() );
		}
		if ( 'timeline' === $stage ) {
			return array( 'message' => $flow['timeline_question'], 'quick_replies' => array( 'Ready now / within 30 days', '1-3 months', '3-6 months', '6+ months / just researching', 'Not sure yet' ) );
		}
		if ( 'budget' === $stage ) {
			return array( 'message' => $flow['budget_questions'][ $service ] ?? $flow['budget_questions']['unsure'], 'quick_replies' => self::budget_replies( $service ) );
		}
		if ( 'decision_role' === $stage ) {
			return array( 'message' => $flow['decision_question'], 'quick_replies' => array( "I'm the final decision-maker", "I'm a strong influencer / gathering info for leadership", "I'm helping gather information", 'Not sure' ) );
		}
		return array( 'message' => $flow['relationship_question'], 'quick_replies' => array( 'Long-term partner', 'Specific project now, but open to ongoing support', 'One-time project only', 'Quick fix / lowest-price option' ) );
	}

	private static function service_from_option( $text, $labels ) {
		foreach ( $labels as $key => $label ) {
			if ( strtolower( $text ) === strtolower( $label ) ) {
				return $key;
			}
		}
		return Tracsoft_LB_Scorer::classify_service_from_text( $text );
	}

	private static function budget_replies( $service ) {
		$replies = array(
			'website' => array( '$10,000+', '$4,500-$9,999', 'Below $4,500', 'Not sure yet' ),
			'marketing' => array( '$3,500+/month', '$1,200-$3,499/month', 'Below $1,200/month', 'Not sure yet' ),
			'ai_training' => array( 'Larger team training / consulting engagement', '$997+ workshop', 'Below $997', 'Not sure yet' ),
			'custom_software' => array( '$10,000+', '$7,500-$9,999', 'Below $7,500', 'Not sure yet' ),
			'unsure' => array( 'Strong budget', 'Moderate budget', 'Limited budget', 'Not sure yet' ),
		);
		return $replies[ $service ] ?? $replies['unsure'];
	}

	private static function replace_urls( $text, $settings ) {
		return strtr( $text, array( '{{consultation_url}}' => $settings['consultation_url'], '{{newsletter_url}}' => $settings['newsletter_url'] ) );
	}

	private static function add_transcript( &$session, $role, $message ) {
		$session['transcript'][] = array( 'role' => $role, 'message' => $message, 'time' => current_time( 'mysql' ) );
	}

	private static function save_session( $session_id, $session ) {
		set_transient( TRACSOFT_LB_SESSION_PREFIX . $session_id, $session, 2 * HOUR_IN_SECONDS );
	}

	private static function get_session( $session_id ) {
		if ( empty( $session_id ) ) {
			return false;
		}
		return get_transient( TRACSOFT_LB_SESSION_PREFIX . $session_id );
	}
}
