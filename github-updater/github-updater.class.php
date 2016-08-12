<?php
/**
 Github updater class for wordpress plugins and themes

 @package github-updater

	Copyright 2016 Mehdi Lahlou (mehdi.lahlou@free.fr)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( ! class_exists( 'GitHubUpdater' ) ) {

	/**
	 * GitHubUpdater class
	 */
	class GitHubUpdater {

		/**
		 * Plugin slug.
		 *
		 * @var string $slug Plugin slug.
		 */
		private $slug;
		/**
		 * Wordpress plugin data.
		 *
		 * @var array $plugin_data Wordpress plugin data.
		 */
		private $plugin_data;
		/**
		 * GitHub username.
		 *
		 * @var string $username GitHub username.
		 */
		private $username;
		/**
		 * GitHub repo name.
		 *
		 * @var string $repo GitHub repo name.
		 */
		private $repo;
		/**
		 * __FILE__ of our plugin.
		 *
		 * @var string $plugin_file __FILE__ of our plugin.
		 */
		private $plugin_file;
		/**
		 * Holds data from GitHub.
		 *
		 * @var array $github_api_result Holds data from GitHub.
		 */
		private $github_api_result;
		/**
		 * GitHub private repo token.
		 *
		 * @var string $access_token GitHub private repo token.
		 */
		private $access_token;

		/**
		 * Constructor
		 *
		 * @param string $plugin_file         The plugin's php file.
		 * @param string $github_user_name    Github username of hosted plugin.
		 * @param string $github_project_name Github project name of hosted plugin.
		 * @param string $access_token        Optional github access token for private plugins.
		 */
		function __construct( $plugin_file, $github_user_name, $github_project_name, $access_token = '' ) {
				$this->plugin_file = $plugin_file;
				$this->username = $github_user_name;
				$this->repo = $github_project_name;
				$this->access_token = $access_token;

				add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_transient' ) );
				add_filter( 'plugins_api', array( $this, 'set_plugin_info' ), 10, 3 );
				add_filter( 'upgrader_pre_install', array( $this, 'pre_install' ), 10, 3 );
				add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
		}

		/**
		 * Get information regarding our plugin from WordPress
		 */
		private function init_plugin_data() {
			$this->slug = plugin_basename( $this->plugin_file );
			$this->plugin_data = get_plugin_data( $this->plugin_file );
		}

		/**
		 * Get information regarding our plugin from GitHub
		 */
		private function get_repo_release_info() {
			// Only do this once.
			if ( 0 && ! empty( $this->github_api_result ) ) {
				return;
			}

			// Query the GitHub API.
			$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases";

			// We need the access token for private repos.
			if ( ! empty( $this->access_token ) ) {
				$url = add_query_arg( array( 'access_token' => $this->access_token ), $url );
			}

			// Get the results.
			$this->github_api_result = wp_remote_retrieve_body( wp_remote_get( $url ) );
			if ( ! empty( $this->github_api_result ) ) {
				$this->github_api_result = @json_decode( $this->github_api_result );
			}

			// Use only the latest release.
			if ( is_array( $this->github_api_result ) && ! empty( $this->github_api_result ) ) {
				$this->github_api_result = $this->github_api_result[0];
			}
		}

		/**
		 * Push in plugin version information to get the update notification
		 *
		 * @param object $transient Wordpress transient object.
		 */
		public function set_transient( $transient ) {
			// If we have checked the plugin data before, don't re-check.
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			// If tag name is empty, return.
			if ( ! isset( $this->github_api_result->tag_name ) ) {
				return $transient;
			}

			// Get plugin & GitHub release information.
			$this->init_plugin_data();
			$this->get_repo_release_info();

			// Check the versions if we need to do an update.
			$do_update = version_compare( $this->github_api_result->tag_name, $transient->checked[ $this->slug ] );

			// Update the transient to include our updated plugin data.
			if ( $do_update ) {
				$package = $this->github_api_result->zipball_url;

				// Include the access token for private GitHub repos.
				if ( ! empty( $this->access_token ) ) {
					$package = add_query_arg( array( 'access_token' => $this->access_token ), $package );
				}

				$obj = new stdClass();
				$obj->slug = $this->slug;
				$obj->new_version = $this->github_api_result->tag_name;
				$obj->url = $this->plugin_data['PluginURI'];
				$obj->package = $package;

				$transient->response[ $this->slug ] = $obj;
			}

			return $transient;
		}

		/**
		 * Push in plugin version information to display in the details lightbox
		 *
		 * @param false|object|array $result The result object or array. Default false.
		 * @param string             $action The type of information being requested from the Plugin Install API.
		 * @param object             $args   Plugin API arguments.
		 */
		public function set_plugin_info( $result, $action, $args ) {
			// Get plugin & GitHub release information.
			$this->init_plugin_data();
			$this->get_repo_release_info();

			// If nothing is found, do nothing.
			if ( empty( $args->slug ) || $args->slug != $this->slug ) {
				return $result;
			}

			// Add our plugin information.
			$args->last_updated = $this->github_api_result->published_at;
			$args->slug = $this->slug;
			$args->plugin_name  = $this->plugin_data['Name'];
			$args->version = $this->github_api_result->tag_name;
			$args->author = $this->plugin_data['AuthorName'];
			$args->homepage = $this->plugin_data['PluginURI'];

			// This is our release download zip file.
			$download_link = $this->github_api_result->zipball_url;

			// Include the access token for private GitHub repos.
			if ( ! empty( $this->access_token ) ) {
				$download_link = add_query_arg(
					array( 'access_token' => $this->access_token ),
					$download_link
				);
			}
			$args->download_link = $download_link;

			// We're going to parse the GitHub markdown release notes, include the parser.
			// TODO: replace by CommonMark parser.
			// require_once( plugin_dir_path( __FILE__ ) . "Parsedown.php" );.
			// Create tabs in the lightbox.
			$args->sections = array(
				'description' => $this->plugin_data['Description'],
				'changelog' => class_exists( 'Parsedown' )
					? Parsedown::instance()->parse( $this->github_api_result->body )
					: $this->github_api_result->body,
			);

			// Gets the required version of WP if available.
			$matches = null;
			preg_match( '/requires:\s([\d\.]+)/i', $this->github_api_result->body, $matches );
			if ( ! empty( $matches ) ) {
				if ( is_array( $matches ) ) {
					if ( count( $matches ) > 1 ) {
						$args->requires = $matches[1];
					}
				}
			}

			// Gets the tested version of WP if available.
			$matches = null;
			preg_match( '/tested:\s([\d\.]+)/i', $this->github_api_result->body, $matches );
			if ( ! empty( $matches ) ) {
				if ( is_array( $matches ) ) {
					if ( count( $matches ) > 1 ) {
						$args->tested = $matches[1];
					}
				}
			}

			return $args;
		}

		/**
		 * Perform check before installation starts.
		 *
		 * @param  bool|WP_Error $response   Response.
		 * @param  array         $hook_extra Extra arguments passed to hooked filters.
		 */
		public function pre_install( $response, $hook_extra ) {
			// Get plugin information.
			$this->init_plugin_data();

			// Check if the plugin was installed before...
			$this->plugin_activated = is_plugin_active( $this->slug );
		}

		/**
		 * Perform additional actions to successfully install our plugin
		 *
		 * @param bool  $response   Install response.
		 * @param array $hook_extra Extra arguments passed to hooked filters.
		 * @param array $result     Installation result data.
		 */
		public function post_install( $response, $hook_extra, $result ) {
			global $wp_filesystem;

			// Since we are hosted in GitHub, our plugin folder would have a dirname of
			// reponame-tagname change it to our original one.
			$plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->slug );
			$wp_filesystem->move( $result['destination'], $plugin_folder );
			$result['destination'] = $plugin_folder;

			// Re-activate plugin if needed.
			if ( $this->plugin_activated ) {
				$activate = activate_plugin( $this->slug );
			}

			return $result;
		}
	}

}
