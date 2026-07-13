<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Scorer {
	public static function score( $session, $settings ) {
		$scoring = $settings['scoring'];
		$answers = $session['answers'] ?? array();
		$service = $answers['service_bucket'] ?? 'unsure';
		$scores = array(
			'budget' => self::score_budget( $service, $answers['budget'] ?? '', $scoring ),
			'timeline' => self::score_timeline( $answers['timeline'] ?? '' ),
			'pain_point' => self::score_pain_point( $service, $answers['pain_point'] ?? '' ),
			'decision_role' => self::score_decision_role( $answers['decision_role'] ?? '' ),
			'relationship_fit' => self::score_relationship_fit( $answers['relationship_fit'] ?? '' ),
		);
		foreach ( $scores as $key => $item ) {
			$weight = (int) ( $scoring['weights'][ $key ] ?? $item['max'] );
			$scores[ $key ]['points'] = (int) round( ( $item['points'] / max( 1, $item['max'] ) ) * $weight );
			$scores[ $key ]['max'] = $weight;
		}
		$total = 0;
		foreach ( $scores as $item ) {
			$total += (int) $item['points'];
		}
		return array(
			'scores' => $scores,
			'total_score' => min( 100, max( 0, $total ) ),
			'lead_status' => self::status_for_score( $total, $scoring['thresholds'] ),
		);
	}

	public static function status_for_score( $score, $thresholds ) {
		if ( $score >= (int) $thresholds['hot'] ) {
			return 'hot';
		}
		if ( $score >= (int) $thresholds['qualified'] ) {
			return 'qualified';
		}
		if ( $score >= (int) $thresholds['nurture'] ) {
			return 'nurture';
		}
		return 'low_fit';
	}

	public static function classify_service_from_text( $text ) {
		$text = strtolower( $text );
		$matches = array(
			'website' => array( 'website', 'site', 'redesign', 'seo', 'conversion', 'homepage', 'web' ),
			'marketing' => array( 'marketing', 'social', 'ads', 'content', 'email', 'campaign', 'leads', 'brand' ),
			'ai_training' => array( 'ai', 'chatgpt', 'training', 'workshop', 'prompt', 'automation ideas' ),
			'custom_software' => array( 'software', 'automation', 'workflow', 'spreadsheet', 'portal', 'system', 'reporting', 'data entry' ),
		);
		foreach ( $matches as $service => $terms ) {
			foreach ( $terms as $term ) {
				if ( false !== strpos( $text, $term ) ) {
					return $service;
				}
			}
		}
		return 'unsure';
	}

	private static function score_timeline( $answer ) {
		$value = strtolower( $answer );
		if ( self::contains_any( $value, array( 'ready now', 'within 30', '30 days', 'immediately', 'asap', 'now' ) ) ) {
			return self::item( 25, 25, 'Ready now or within 30 days.' );
		}
		if ( self::contains_any( $value, array( '1-3', '1 to 3', 'one to three', 'month or two', 'few months' ) ) ) {
			return self::item( 18, 25, 'Target timeline is 1-3 months.' );
		}
		if ( self::contains_any( $value, array( '3-6', '3 to 6', 'three to six' ) ) ) {
			return self::item( 10, 25, 'Target timeline is 3-6 months.' );
		}
		if ( self::contains_any( $value, array( '6+', 'six+', '6 months', 'researching', 'not sure', 'someday', 'later' ) ) ) {
			return self::item( 5, 25, 'Longer timeline or still researching.' );
		}
		return self::item( 0, 25, 'No clear timeline was provided.' );
	}

	private static function score_budget( $service, $answer, $scoring ) {
		$value = strtolower( str_replace( array( ',', '$' ), '', $answer ) );
		if ( self::contains_any( $value, array( 'not sure', 'no budget', 'very low', 'cheap', 'free' ) ) ) {
			return self::item( 0, 20, 'No clear budget or very low budget.' );
		}
		if ( 'ai_training' === $service ) {
			if ( self::contains_any( $value, array( 'larger team', 'consulting engagement', 'team training' ) ) ) {
				return self::item( 20, 20, 'Larger team training or consulting engagement.' );
			}
			if ( self::contains_any( $value, array( '997+', 'workshop' ) ) || self::extract_amount( $value ) >= (int) $scoring['budgets']['ai_training']['workshop_minimum'] ) {
				return self::item( 15, 20, 'Workshop-level budget fit.' );
			}
			if ( self::contains_any( $value, array( 'below 997', 'under 997', 'interested' ) ) ) {
				return self::item( 5, 20, 'Interested but below workshop minimum.' );
			}
			return self::item( 0, 20, 'Budget fit is unclear.' );
		}
		$strong = (int) ( $scoring['budgets'][ $service ]['strong'] ?? 10000 );
		$minimum = (int) ( $scoring['budgets'][ $service ]['minimum'] ?? 4500 );
		$amount = self::extract_amount( $value );
		if ( $amount >= $strong || false !== strpos( $value, (string) $strong . '+' ) ) {
			return self::item( 20, 20, 'Budget is in the strong-fit range.' );
		}
		if ( $amount >= $minimum ) {
			return self::item( 15, 20, 'Budget is in the viable project range.' );
		}
		if ( $amount > 0 || self::contains_any( $value, array( 'below', 'under', 'serious', 'interested' ) ) ) {
			return self::item( 5, 20, 'Budget is below target but there may be serious interest.' );
		}
		return self::item( 0, 20, 'No usable budget fit was provided.' );
	}

	private static function score_pain_point( $service, $answer ) {
		$value = strtolower( $answer );
		if ( '' === trim( $value ) ) {
			return self::item( 0, 20, 'No meaningful pain point stated.' );
		}
		$high_fit = array(
			'website' => array( 'outdated', 'conversions', 'hard to update', 'not generating leads', 'poor seo', 'poor user experience', 'brand', 'business goals' ),
			'marketing' => array( 'inconsistent', 'strategy', 'lead generation', 'ad results', 'engagement', 'content system', 'ongoing marketing partner' ),
			'ai_training' => array( 'use ai', 'save time', 'workflow', 'staff training', 'adoption', 'operations' ),
			'custom_software' => array( 'manual', 'spreadsheet', 'disconnected', 'portal', 'bottleneck', 'reporting', 'data entry', 'inefficiency' ),
			'unsure' => array( 'improve', 'fix', 'grow', 'leads', 'systems', 'workflow', 'marketing', 'website' ),
		);
		if ( self::contains_any( $value, $high_fit[ $service ] ?? $high_fit['unsure'] ) && self::contains_any( $value, array( 'urgent', 'need', 'problem', 'struggling', 'not working', 'wasting', 'losing', 'chaos', 'broken' ) ) ) {
			return self::item( 20, 20, 'Clear urgent problem Tracsoft directly solves.' );
		}
		if ( self::contains_any( $value, $high_fit[ $service ] ?? $high_fit['unsure'] ) ) {
			return self::item( 15, 20, 'Clear problem that appears relevant for Tracsoft.' );
		}
		if ( strlen( $value ) > 12 ) {
			return self::item( 8, 20, 'General interest without a highly specific pain point.' );
		}
		return self::item( 0, 20, 'No meaningful pain point stated.' );
	}

	private static function score_decision_role( $answer ) {
		$value = strtolower( $answer );
		if ( self::contains_any( $value, array( 'final decision', 'owner', 'executive', 'budget holder', 'ceo', 'founder', 'president' ) ) ) {
			return self::item( 20, 20, 'Final decision-maker or budget holder.' );
		}
		if ( self::contains_any( $value, array( 'strong influencer', 'leadership', 'gathering info for leadership', 'recommend' ) ) ) {
			return self::item( 12, 20, 'Strong influencer gathering information.' );
		}
		if ( self::contains_any( $value, array( 'helping gather', 'coordinator', 'staff', 'assistant' ) ) ) {
			return self::item( 6, 20, 'Staff member or coordinator with limited influence.' );
		}
		return self::item( 0, 20, 'Role in the decision process is unclear.' );
	}

	private static function score_relationship_fit( $answer ) {
		$value = strtolower( $answer );
		if ( self::contains_any( $value, array( 'long-term', 'long term', 'partner', 'growth', 'ongoing' ) ) ) {
			return self::item( 15, 15, 'Wants a long-term partner to solve problems and support growth.' );
		}
		if ( self::contains_any( $value, array( 'open to ongoing', 'specific project now', 'support afterward' ) ) ) {
			return self::item( 10, 15, 'Specific project now with openness to ongoing support.' );
		}
		if ( self::contains_any( $value, array( 'one-time', 'one time', 'well-scoped', 'single project' ) ) ) {
			return self::item( 5, 15, 'One-time project only, but potentially meaningful.' );
		}
		if ( self::contains_any( $value, array( 'quick fix', 'lowest-price', 'cheapest', 'cheap' ) ) ) {
			return self::item( 0, 15, 'Quick fix or lowest-price vendor fit.' );
		}
		return self::item( 0, 15, 'Use case or relationship fit is unclear.' );
	}

	private static function item( $points, $max, $reason ) {
		return array( 'points' => (int) $points, 'max' => (int) $max, 'reason' => $reason );
	}

	private static function contains_any( $haystack, $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function extract_amount( $text ) {
		if ( preg_match( '/([0-9]+(?:\.[0-9]+)?)(k| thousand)?/i', $text, $matches ) ) {
			$amount = (float) $matches[1];
			if ( ! empty( $matches[2] ) ) {
				$amount *= 1000;
			}
			return $amount;
		}
		return 0;
	}
}
