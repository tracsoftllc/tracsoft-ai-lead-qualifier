<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Settings {
	public static function get() {
		return self::merge_defaults( get_option( TRACSOFT_LB_OPTION, array() ) );
	}

	public static function update( $settings ) {
		update_option( TRACSOFT_LB_OPTION, self::sanitize( self::merge_defaults( $settings ) ), false );
	}

	public static function merge_defaults( $settings ) {
		return array_replace_recursive( self::defaults(), is_array( $settings ) ? $settings : array() );
	}

	public static function defaults() {
		return array(
			'enabled' => 1,
			'display_all_pages' => 1,
			'include_pages' => '',
			'exclude_pages' => '',
			'widget_title' => 'Tracsoft Lead Bot',
			'intro_message' => "Hi! I'm here to help point you in the right direction. What kind of help are you looking for right now?",
			'position' => 'bottom_right',
			'primary_color' => '#0f766e',
			'secondary_color' => '#f97316',
			'button_label' => 'Need help?',
			'privacy_note' => "We'll only ask for contact information if it looks like Tracsoft may be a strong fit and a personal follow-up would be helpful.",
			'consultation_url' => 'https://tracsoft.com/consultation/',
			'newsletter_url' => 'https://tracsoft.com/newsletter/',
			'collect_hot_contact' => 1,
			'create_notion_hot' => 1,
			'email_hot' => 1,
			'openai_api_key' => '',
			'openai_model' => 'gpt-4.1-mini',
			'openai_temperature' => '0.4',
			'openai_max_tokens' => '700',
			'notion_api_key' => '',
			'notion_database_id' => '',
			'notion_mapping' => self::default_notion_mapping(),
			'email_recipient' => 'alan@tracsoft.com',
			'email_from_name' => get_bloginfo( 'name' ),
			'email_from_email' => get_option( 'admin_email' ),
			'email_subject' => 'Hot Lead Alert: {{service_bucket}} - Score {{lead_score}}',
			'email_body' => self::default_email_body(),
			'analytics_enabled' => 0,
			'system_prompt' => self::default_prompt(),
			'flow' => self::default_flow(),
			'scoring' => self::default_scoring(),
			'knowledge' => self::default_knowledge(),
		);
	}

	public static function service_options() {
		return array(
			'website' => 'Website development or redesign',
			'marketing' => 'Marketing help, like social media, ads, SEO, content, or email',
			'ai_training' => 'AI training or AI consulting',
			'custom_software' => 'Custom software or automation',
			'unsure' => "I'm not sure yet",
		);
	}

	public static function default_flow() {
		return array(
			'opening_question' => "Hi! I'm here to help point you in the right direction. What kind of help are you looking for right now?",
			'service_labels' => self::service_options(),
			'pain_questions' => array(
				'website' => "What's not working about your current website, or what do you need the new website to help you accomplish?",
				'marketing' => "What's the biggest marketing challenge you're trying to solve right now?",
				'ai_training' => 'What would you like your team to be able to do better with AI?',
				'custom_software' => 'What process, workflow, or task are you trying to improve or automate?',
				'unsure' => 'What are you trying to improve, fix, or figure out in your business right now?',
			),
			'timeline_question' => "What's your ideal timeline for getting started?",
			'budget_questions' => array(
				'website' => 'Do you have a rough budget in mind for the website project?',
				'marketing' => 'Do you have a rough monthly budget in mind for marketing support?',
				'ai_training' => 'Do you have a rough budget in mind for AI training or consulting?',
				'custom_software' => 'Do you have a rough budget in mind for the software or automation project?',
				'unsure' => 'Do you have a rough budget in mind?',
			),
			'decision_question' => 'Are you the person who would make the final decision on this, or are you helping gather information for someone else on your team?',
			'relationship_question' => 'Are you mainly looking for a long-term partner to help solve problems and support growth, or are you looking for help with a one-time project?',
			'hot_message' => "Based on what you shared, this sounds like a strong fit for Tracsoft. Alan may want to follow up personally so we can help you take the next step quickly.\n\nWhat's the best name, company name, email, and phone number for him to reach you?",
			'hot_thank_you' => "Thank you. I'll send this to Alan so he can follow up. You can also go ahead and schedule a consultation here:\n\n{{consultation_url}}",
			'qualified_message' => "Based on what you shared, it sounds like Tracsoft may be a good fit. The best next step is to schedule a consultation so we can learn more and point you in the right direction.\n\nYou can schedule that here:\n\n{{consultation_url}}",
			'nurture_message' => "Thanks for sharing that. It sounds like you may still be exploring or not quite ready for a full project yet, and that's completely fine.\n\nA good next step would be to connect with Tracsoft and learn more through our newsletter. We share practical ideas around websites, marketing, AI, and business growth.\n\nYou can join here:\n\n{{newsletter_url}}",
			'low_fit_message' => "Thanks for sharing that. Based on what you said, this may not be the right time for a consultation yet, but we'd still love to stay connected and be helpful as you keep learning.\n\nYou can join the Tracsoft newsletter here:\n\n{{newsletter_url}}",
		);
	}

	public static function default_scoring() {
		return array(
			'weights' => array( 'budget' => 20, 'timeline' => 25, 'pain_point' => 20, 'decision_role' => 20, 'relationship_fit' => 15 ),
			'thresholds' => array( 'hot' => 90, 'qualified' => 70, 'nurture' => 40, 'low_fit_below' => 40 ),
			'budgets' => array(
				'website' => array( 'strong' => 10000, 'minimum' => 4500 ),
				'marketing' => array( 'strong' => 3500, 'minimum' => 1200 ),
				'ai_training' => array( 'strong_label' => 'Larger team training / consulting engagement', 'workshop_minimum' => 997 ),
				'custom_software' => array( 'strong' => 10000, 'minimum' => 7500 ),
			),
		);
	}

	public static function default_knowledge() {
		return array(
			array( 'title' => 'Tracsoft Overview', 'enabled' => 1, 'content' => 'Tracsoft helps businesses and nonprofits grow through website development, digital marketing, AI training, and custom software solutions. We work best with organizations that want a long-term partner who can help solve problems, improve systems, and support growth.' ),
			array( 'title' => 'Website Development', 'enabled' => 1, 'content' => 'Tracsoft builds and redesigns websites that are designed to support business goals, improve user experience, generate leads, and make it easier for organizations to communicate clearly online. Website projects typically start at $4,500, with many strong-fit projects around $10,000.' ),
			array( 'title' => 'Marketing Services', 'enabled' => 1, 'content' => 'Tracsoft provides ongoing marketing support, including social media, content, ads, SEO, email, and strategy. Marketing retainers typically start around $1,200 per month, with strong-fit clients often around $3,500 per month.' ),
			array( 'title' => 'AI Training', 'enabled' => 1, 'content' => 'Tracsoft helps teams understand and apply AI in practical ways. This may include workshops, AI workflow planning, prompt development, and consulting around how to use AI to save time and improve operations. Workshops start around $997.' ),
			array( 'title' => 'Custom Software', 'enabled' => 1, 'content' => 'Tracsoft builds custom software and automation solutions for organizations that need better systems, better workflows, or tools that do not exist off the shelf. Projects typically begin around $7,500 to $10,000 and vary based on complexity.' ),
			array( 'title' => 'Ideal Client', 'enabled' => 1, 'content' => 'Tracsoft works best with businesses and nonprofits that want a long-term partner, not just a one-time vendor. The best-fit clients are trying to solve real problems, improve operations, grow their audience, generate leads, or communicate more effectively.' ),
			array( 'title' => 'Differentiator', 'enabled' => 1, 'content' => 'Tracsoft focuses on relationships, trust, and long-term partnership. We want to help clients solve problems and grow, not just complete one-off tasks.' ),
		);
	}

	public static function default_notion_mapping() {
		$fields = array( 'Account Owner', 'Status', 'Priority', 'Discovery Notes', 'Estimated Value', 'Company Name', 'Contact Name', 'Email', 'Phone', 'Company Website', 'Last Contact', 'Lead Source', 'Discovery Call', 'Lead Score', 'Service Bucket', 'Timeline', 'Budget Range', 'Decision Role', 'Relationship Fit', 'Recommended Next Action', 'Conversation Transcript' );
		$mapping = array();
		foreach ( $fields as $field ) {
			$mapping[ sanitize_key( $field ) ] = $field;
		}
		return $mapping;
	}

	public static function default_email_body() {
		return "A new hot lead came through the Tracsoft AI Lead Qualifier.\n\nLead score: {{lead_score}}\nService bucket: {{service_bucket}}\nContact name: {{contact_name}}\nCompany name: {{company_name}}\nEmail: {{email}}\nPhone: {{phone}}\n\nPain point summary:\n{{summary}}\n\nBudget range: {{budget_range}}\nTimeline: {{timeline}}\nDecision-maker status: {{decision_role}}\nRelationship fit: {{relationship_fit}}\n\nScore breakdown:\n{{score_breakdown}}\n\nFull conversation transcript:\n{{transcript}}\n\nNotion sync status: {{notion_status}}\n\nRecommended next action: Call and/or email this lead as soon as possible.";
	}

	public static function default_prompt() {
		return "You are the Tracsoft AI Lead Qualification Assistant on Tracsoft.com.\n\nYour primary job is to help website visitors determine whether Tracsoft is the right partner for their needs. You qualify visitors for one of four service paths: website development, marketing services, AI training/consulting, or custom software/automation.\n\nYou are not a general support bot. You may answer basic questions about Tracsoft services when those answers help the visitor decide whether to take the next step. Keep those answers brief, helpful, and focused. After answering, guide the visitor back to the qualification flow.\n\nTone: friendly, clear, conversational, helpful, professional, relationship-focused.\n\nImportant rules:\n* Ask one question at a time.\n* Do not ask for name, email, phone number, company name, website, or location during the initial qualification flow.\n* Only ask for contact details if the lead scores as a hot lead.\n* Do not use geography as a qualification factor.\n* Do not overwhelm the visitor with long lists of questions.\n* Do not make the visitor feel like a transaction.\n* Do not promise exact pricing, exact timelines, or guaranteed results.\n* Do not tell the visitor they are unqualified.\n* If the visitor is not ready, invite them to connect through the newsletter.\n* If the visitor is qualified, direct them to the consultation page.\n* If the visitor is a hot lead, collect contact information for immediate follow-up.\n\nQualification categories:\n1. Budget fit\n2. Timeline / urgency\n3. Pain point relevance\n4. Decision-making role\n5. Use case / relationship fit\n\nScore leads out of 100:\n* Budget fit: 20 points\n* Timeline: 25 points\n* Pain point relevance: 20 points\n* Decision-making role: 20 points\n* Use case / relationship fit: 15 points\n\nLead statuses:\n* 90-100: Hot Lead\n* 70-89: Qualified Lead\n* 40-69: Nurture Lead\n* 0-39: Low Fit Lead\n\nFor hot leads, ask for name, company name, email, and phone number, then tell them Alan may follow up personally and also provide the consultation link.\n\nFor qualified leads, do not collect contact info in chat. Send them to the consultation page.\n\nFor nurture and low-fit leads, send them to the newsletter page and invite them to stay connected.\n\nAlways keep the conversation helpful and natural.";
	}

	public static function sanitize( $settings ) {
		$settings['enabled'] = empty( $settings['enabled'] ) ? 0 : 1;
		$settings['display_all_pages'] = empty( $settings['display_all_pages'] ) ? 0 : 1;
		$settings['collect_hot_contact'] = empty( $settings['collect_hot_contact'] ) ? 0 : 1;
		$settings['create_notion_hot'] = empty( $settings['create_notion_hot'] ) ? 0 : 1;
		$settings['email_hot'] = empty( $settings['email_hot'] ) ? 0 : 1;
		$settings['analytics_enabled'] = empty( $settings['analytics_enabled'] ) ? 0 : 1;
		foreach ( array( 'include_pages', 'exclude_pages', 'widget_title', 'intro_message', 'button_label', 'privacy_note', 'openai_model', 'notion_database_id', 'email_recipient', 'email_from_name', 'email_from_email', 'email_subject' ) as $key ) {
			$settings[ $key ] = isset( $settings[ $key ] ) ? sanitize_text_field( $settings[ $key ] ) : '';
		}
		foreach ( array( 'consultation_url', 'newsletter_url' ) as $key ) {
			$settings[ $key ] = esc_url_raw( $settings[ $key ] );
		}
		foreach ( array( 'primary_color', 'secondary_color' ) as $key ) {
			$settings[ $key ] = sanitize_hex_color( $settings[ $key ] );
		}
		$settings['position'] = in_array( $settings['position'], array( 'bottom_right', 'bottom_left' ), true ) ? $settings['position'] : 'bottom_right';
		$settings['openai_temperature'] = (string) floatval( $settings['openai_temperature'] );
		$settings['openai_max_tokens'] = (string) absint( $settings['openai_max_tokens'] );
		$settings['openai_api_key'] = sanitize_text_field( $settings['openai_api_key'] );
		$settings['notion_api_key'] = sanitize_text_field( $settings['notion_api_key'] );
		$settings['system_prompt'] = wp_kses_post( $settings['system_prompt'] );
		$settings['email_body'] = wp_kses_post( $settings['email_body'] );
		$settings['flow'] = self::sanitize_deep_textarea( $settings['flow'] );
		$settings['scoring'] = self::sanitize_numbers_deep( $settings['scoring'] );
		$settings['knowledge'] = self::sanitize_knowledge( $settings['knowledge'] );
		$settings['notion_mapping'] = self::sanitize_deep_text( $settings['notion_mapping'] );
		return $settings;
	}

	private static function sanitize_deep_textarea( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_deep_textarea' ), $value );
		}
		return sanitize_textarea_field( $value );
	}

	private static function sanitize_deep_text( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_deep_text' ), $value );
		}
		return sanitize_text_field( $value );
	}

	private static function sanitize_numbers_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'sanitize_numbers_deep' ), $value );
		}
		return is_numeric( $value ) ? 0 + $value : sanitize_text_field( $value );
	}

	private static function sanitize_knowledge( $items ) {
		$clean = array();
		if ( ! is_array( $items ) ) {
			return $clean;
		}
		foreach ( $items as $item ) {
			$clean[] = array(
				'title' => sanitize_text_field( $item['title'] ?? '' ),
				'enabled' => empty( $item['enabled'] ) ? 0 : 1,
				'content' => sanitize_textarea_field( $item['content'] ?? '' ),
			);
		}
		return $clean;
	}
}
