<?php

// Prevent loading this file directly and/or if the class is already defined
if ( ! defined( 'ABSPATH' ) || class_exists( 'Ncoa_Ads_Bitbucket_Updater' ) )
	return;

/**
 * Checks a Bitbucket Cloud repository branch for new plugin versions and
 * lets WordPress install them through the normal plugin update screen.
 *
 * Based on WP_GitHub_Updater 1.6 by Joachim Kudish (GPL-2.0+).
 *
 * The version is read from the "Version:" header of the main plugin file on
 * the configured branch. Bump that header to publish an update.
 *
 * Private repositories: define NCOA_ADS_BITBUCKET_TOKEN in wp-config.php with
 * either a Bitbucket repository access token (sent as a Bearer token) or
 * "email:api_token" for an Atlassian API token (sent as Basic auth).
 *
 * @package Ncoa_Ads
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class Ncoa_Ads_Bitbucket_Updater {

	/**
	 * Seconds to cache remote data between checks.
	 */
	const CACHE_TTL = 21600; // 6 hours

	/**
	 * @var array the config for the updater
	 */
	public $config;

	/**
	 * @var array config keys missing from the initialization of this instance
	 */
	public $missing_config;

	/**
	 * @var object|false repository data from the Bitbucket API, cached per request
	 */
	private $repo_data;

	/**
	 * @param array $config the configuration required for the updater to work
	 */
	public function __construct( $config = array() ) {

		$defaults = array(
			'slug'               => '',
			'proper_folder_name' => '',
			'workspace'          => '',
			'repo'               => '',
			'branch'             => 'main',
			'sslverify'          => true,
			'access_token'       => '',
			'readme'             => 'README.md',
			'timeout'            => 5,
		);

		$this->config = wp_parse_args( $config, $defaults );

		if ( ! $this->has_minimum_config() ) {
			$message  = 'The Bitbucket Updater was initialized without the minimum required configuration. Missing params: ';
			$message .= implode( ',', $this->missing_config );
			_doing_it_wrong( __CLASS__, esc_html( $message ), '1.1.7' );
			return;
		}

		$this->set_defaults();

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );

		// Add auth header / sslverify to WordPress's own zip download request
		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 10, 2 );
	}

	public function has_minimum_config() {
		$this->missing_config = array();

		foreach ( array( 'slug', 'proper_folder_name', 'workspace', 'repo', 'branch', 'requires', 'tested' ) as $param ) {
			if ( empty( $this->config[ $param ] ) )
				$this->missing_config[] = $param;
		}

		return empty( $this->missing_config );
	}

	/**
	 * Skip the transient cache and hit Bitbucket on every check.
	 * Only for debugging: Bitbucket rate-limits API requests.
	 *
	 * @return bool
	 */
	public function overrule_transients() {
		return ( defined( 'NCOA_ADS_FORCE_UPDATE_CHECK' ) && NCOA_ADS_FORCE_UPDATE_CHECK );
	}

	public function set_defaults() {
		$ws   = rawurlencode( $this->config['workspace'] );
		$repo = rawurlencode( $this->config['repo'] );
		$branch = rawurlencode( $this->config['branch'] );

		$this->config['api_url']  = "https://api.bitbucket.org/2.0/repositories/{$ws}/{$repo}";
		$this->config['src_url']  = $this->config['api_url'] . "/src/{$branch}/";
		$this->config['repo_url'] = "https://bitbucket.org/{$ws}/{$repo}";
		$this->config['zip_url']  = $this->config['repo_url'] . "/get/{$branch}.zip";

		$this->config['cache_key'] = 'ncoa_ads_bb_' . md5( $this->config['slug'] );

		if ( ! isset( $this->config['new_version'] ) )
			$this->config['new_version'] = $this->get_new_version();

		if ( ! isset( $this->config['last_updated'] ) )
			$this->config['last_updated'] = $this->get_date();

		if ( ! isset( $this->config['description'] ) )
			$this->config['description'] = $this->get_description();

		$plugin_data = $this->get_plugin_data();

		if ( ! isset( $this->config['plugin_name'] ) )
			$this->config['plugin_name'] = $plugin_data['Name'];

		if ( ! isset( $this->config['version'] ) )
			$this->config['version'] = $plugin_data['Version'];

		if ( ! isset( $this->config['author'] ) )
			$this->config['author'] = $plugin_data['Author'];

		if ( ! isset( $this->config['homepage'] ) )
			$this->config['homepage'] = $plugin_data['PluginURI'];
	}

	/**
	 * Authorization header value for the configured token, or '' for none.
	 *
	 * @return string
	 */
	private function auth_header() {
		$token = $this->config['access_token'];

		if ( empty( $token ) )
			return '';

		// "email:api_token" => Basic auth, otherwise a repository access token
		if ( false !== strpos( $token, ':' ) )
			return 'Basic ' . base64_encode( $token );

		return 'Bearer ' . $token;
	}

	/**
	 * Callback for http_request_args: authenticates WordPress's download of
	 * the update zip, which is made outside this class.
	 *
	 * @param array  $args
	 * @param string $url
	 * @return array
	 */
	public function http_request_args( $args, $url ) {
		if ( $this->config['zip_url'] !== $url )
			return $args;

		$args['sslverify'] = $this->config['sslverify'];

		$auth = $this->auth_header();
		if ( $auth ) {
			if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) )
				$args['headers'] = array();
			$args['headers']['Authorization'] = $auth;
		}

		return $args;
	}

	/**
	 * GET a Bitbucket URL with auth.
	 *
	 * @param string $url
	 * @return array|WP_Error
	 */
	public function remote_get( $url ) {
		$args = array(
			'sslverify' => $this->config['sslverify'],
			'timeout'   => $this->config['timeout'],
			'headers'   => array(),
		);

		$auth = $this->auth_header();
		if ( $auth )
			$args['headers']['Authorization'] = $auth;

		return wp_remote_get( $url, $args );
	}

	/**
	 * Body of a successful response, or false.
	 *
	 * @param array|WP_Error $response
	 * @return string|false
	 */
	private function response_body( $response ) {
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) )
			return false;

		$body = wp_remote_retrieve_body( $response );
		return ( '' === $body ) ? false : $body;
	}

	/**
	 * Get the version on the Bitbucket branch.
	 *
	 * @return string|false
	 */
	public function get_new_version() {
		$key     = $this->config['cache_key'] . '_new_version';
		$version = get_site_transient( $key );

		if ( ! $this->overrule_transients() && ! empty( $version ) )
			return $version;

		$version = false;

		// Version: header of the main plugin file
		$body = $this->response_body( $this->remote_get( $this->config['src_url'] . basename( $this->config['slug'] ) ) );
		if ( $body && preg_match( '/^[\s\*#@]*Version:\s*(\S+)/mi', $body, $matches ) )
			$version = trim( $matches[1] );

		// Fallback: ~Current Version:x.y.z~ in the readme
		if ( false === $version ) {
			$body = $this->response_body( $this->remote_get( $this->config['src_url'] . $this->config['readme'] ) );
			if ( $body && preg_match( '#^\s*`*~Current Version\:\s*([^~]*)~#im', $body, $matches ) )
				$version = trim( $matches[1] );
		}

		if ( false !== $version )
			set_site_transient( $key, $version, self::CACHE_TTL );

		return $version;
	}

	/**
	 * Repository metadata from the Bitbucket API.
	 *
	 * @return object|false
	 */
	public function get_repo_data() {
		if ( ! empty( $this->repo_data ) )
			return $this->repo_data;

		$key  = $this->config['cache_key'] . '_repo_data';
		$data = get_site_transient( $key );

		if ( $this->overrule_transients() || empty( $data ) ) {
			$body = $this->response_body( $this->remote_get( $this->config['api_url'] ) );
			$data = $body ? json_decode( $body ) : false;

			if ( ! is_object( $data ) )
				return false;

			set_site_transient( $key, $data, self::CACHE_TTL );
		}

		$this->repo_data = $data;
		return $data;
	}

	/**
	 * @return string|false Y-m-d of the last repository update
	 */
	public function get_date() {
		$data = $this->get_repo_data();
		return ( ! empty( $data->updated_on ) ) ? date( 'Y-m-d', strtotime( $data->updated_on ) ) : false;
	}

	/**
	 * @return string|false the repository description
	 */
	public function get_description() {
		$data = $this->get_repo_data();
		return ( ! empty( $data->description ) ) ? $data->description : false;
	}

	/**
	 * @return array header data of the installed plugin
	 */
	public function get_plugin_data() {
		include_once ABSPATH . '/wp-admin/includes/plugin.php';
		return get_plugin_data( WP_PLUGIN_DIR . '/' . $this->config['slug'] );
	}

	/**
	 * Add our update to the plugin update transient when Bitbucket has a newer version.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function api_check( $transient ) {
		if ( empty( $transient->checked ) || empty( $this->config['new_version'] ) )
			return $transient;

		if ( 1 === version_compare( $this->config['new_version'], $this->config['version'] ) ) {
			$response              = new stdClass;
			$response->new_version = $this->config['new_version'];
			$response->slug        = $this->config['proper_folder_name'];
			$response->plugin      = $this->config['slug'];
			$response->url         = $this->config['repo_url'];
			$response->package     = $this->config['zip_url'];

			$transient->response[ $this->config['slug'] ] = $response;
		}

		return $transient;
	}

	/**
	 * Plugin details popup on the Plugins screen.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object|array
	 */
	public function get_plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) )
			return $result;

		if ( $args->slug !== $this->config['proper_folder_name'] && $args->slug !== $this->config['slug'] )
			return $result;

		$info                = new stdClass;
		$info->name          = $this->config['plugin_name'];
		$info->slug          = $this->config['proper_folder_name'];
		$info->version       = $this->config['new_version'];
		$info->author        = $this->config['author'];
		$info->homepage      = $this->config['homepage'];
		$info->requires      = $this->config['requires'];
		$info->tested        = $this->config['tested'];
		$info->downloaded    = 0;
		$info->last_updated  = $this->config['last_updated'];
		$info->sections      = array( 'description' => (string) $this->config['description'] );
		$info->download_link = $this->config['zip_url'];

		return $info;
	}

	/**
	 * Bitbucket zips unpack to "workspace-repo-<hash>/". Move that to the
	 * plugin's real folder and reactivate it.
	 *
	 * @param bool  $response
	 * @param array $hook_extra
	 * @param array $result
	 * @return array
	 */
	public function upgrader_post_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		// Only act on updates of this plugin
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->config['slug'] )
			return $result;

		$proper_destination = WP_PLUGIN_DIR . '/' . $this->config['proper_folder_name'];

		if ( untrailingslashit( $result['destination'] ) !== $proper_destination ) {
			$wp_filesystem->move( $result['destination'], $proper_destination, true );
			$result['destination']      = $proper_destination;
			$result['destination_name'] = $this->config['proper_folder_name'];
		}

		$activate = activate_plugin( WP_PLUGIN_DIR . '/' . $this->config['slug'] );

		$fail    = __( 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'ncoa-ads' );
		$success = __( 'Plugin reactivated successfully.', 'ncoa-ads' );
		echo is_wp_error( $activate ) ? esc_html( $fail ) : esc_html( $success );

		return $result;
	}
}
