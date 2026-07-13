<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tracsoft_LB_Updater {
	const CACHE_KEY = 'tracsoft_lb_github_release';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_update_source' ), 10, 4 );
	}

	public static function check_for_update( $transient ) {
		if ( empty( $transient->checked ) || ! isset( $transient->checked[ self::plugin_basename() ] ) ) {
			return $transient;
		}

		$release = self::latest_release();
		if ( ! $release || empty( $release['version'] ) || ! version_compare( $release['version'], TRACSOFT_LB_VERSION, '>' ) ) {
			return $transient;
		}

		$transient->response[ self::plugin_basename() ] = (object) array(
			'id'          => self::plugin_basename(),
			'slug'        => self::plugin_slug(),
			'plugin'      => self::plugin_basename(),
			'new_version' => $release['version'],
			'url'         => self::github_url(),
			'package'     => $release['package'],
			'tested'      => '',
			'requires'    => '',
		);

		return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::plugin_slug() !== $args->slug ) {
			return $result;
		}

		$release = self::latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Tracsoft AI Lead Qualifier',
			'slug'          => self::plugin_slug(),
			'version'       => $release['version'],
			'author'        => '<a href="https://tracsoft.com/">Tracsoft</a>',
			'homepage'      => self::github_url(),
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => 'AI-assisted lead qualification chatbot for Tracsoft.com.',
				'changelog'   => ! empty( $release['notes'] ) ? wp_kses_post( wpautop( $release['notes'] ) ) : 'See the GitHub release for details.',
			),
		);
	}

	public static function normalize_update_source( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || self::plugin_basename() !== $hook_extra['plugin'] ) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . self::plugin_slug();
		if ( $source === $target || ! is_dir( $source ) ) {
			return $source;
		}

		global $wp_filesystem;
		if ( $wp_filesystem && ! $wp_filesystem->exists( $target ) && $wp_filesystem->move( $source, $target ) ) {
			return $target;
		}

		return $source;
	}

	private static function latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$release = self::fetch_latest_github_release();
		if ( ! $release ) {
			$release = self::fetch_latest_github_tag();
		}

		set_site_transient( self::CACHE_KEY, $release ? $release : array(), 6 * HOUR_IN_SECONDS );
		return $release;
	}

	private static function fetch_latest_github_release() {
		$data = self::github_get( 'https://api.github.com/repos/' . TRACSOFT_LB_GITHUB_OWNER . '/' . TRACSOFT_LB_GITHUB_REPO . '/releases/latest' );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			return false;
		}

		return self::release_from_tag(
			$data['tag_name'],
			! empty( $data['body'] ) ? $data['body'] : '',
			! empty( $data['html_url'] ) ? $data['html_url'] : self::github_url()
		);
	}

	private static function fetch_latest_github_tag() {
		$data = self::github_get( 'https://api.github.com/repos/' . TRACSOFT_LB_GITHUB_OWNER . '/' . TRACSOFT_LB_GITHUB_REPO . '/tags' );
		if ( ! is_array( $data ) || empty( $data[0]['name'] ) ) {
			return false;
		}

		usort( $data, array( __CLASS__, 'compare_tags' ) );
		return self::release_from_tag( $data[0]['name'], '', self::github_url() . '/releases/tag/' . rawurlencode( $data[0]['name'] ) );
	}

	private static function github_get( $url ) {
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Tracsoft-AI-Lead-Qualifier/' . TRACSOFT_LB_VERSION,
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : false;
	}

	private static function release_from_tag( $tag, $notes, $url ) {
		$version = ltrim( $tag, 'vV' );
		if ( ! preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return false;
		}

		return array(
			'version' => $version,
			'tag'     => $tag,
			'notes'   => $notes,
			'url'     => $url,
			'package' => 'https://github.com/' . TRACSOFT_LB_GITHUB_OWNER . '/' . TRACSOFT_LB_GITHUB_REPO . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip',
		);
	}

	private static function compare_tags( $a, $b ) {
		$a_version = ! empty( $a['name'] ) ? ltrim( $a['name'], 'vV' ) : '0';
		$b_version = ! empty( $b['name'] ) ? ltrim( $b['name'], 'vV' ) : '0';
		return version_compare( $b_version, $a_version );
	}

	private static function plugin_basename() {
		return plugin_basename( TRACSOFT_LB_FILE );
	}

	private static function plugin_slug() {
		return dirname( self::plugin_basename() );
	}

	private static function github_url() {
		return 'https://github.com/' . TRACSOFT_LB_GITHUB_OWNER . '/' . TRACSOFT_LB_GITHUB_REPO;
	}
}
