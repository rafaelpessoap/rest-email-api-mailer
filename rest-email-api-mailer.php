<?php
/**
 * Plugin Name:       REST Email API Mailer
 * Plugin URI:        https://github.com/rafaelpessoap/rest-email-api-mailer
 * Description:       Replaces the default wp_mail() with delivery via the transactional email REST API hosted at platform.cyberpersons.com (the email service used by Cyberpanel hosting). Includes smart delivery tracking, account statistics dashboard, and graceful fallback to the standard PHP mailer when disabled. Independent open-source plugin — not affiliated with, endorsed by or sponsored by Cyberpanel or CyberPersons LLC.
 * Version:           2.2.0
 * Author:            Rafael Pessoa
 * Author URI:        https://arsenalcraft.com.br
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rest-email-api-mailer
 * Domain Path:       /languages
 * Requires at least: 6.1
 * Tested up to:      6.9
 * Requires PHP:      7.4
 *
 * @package Restemap_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Restemap_Plugin' ) ) {

	/**
	 * Main plugin class.
	 *
	 * Singleton that wires up admin UI, settings, email sending, delivery
	 * tracking cron and logging.
	 */
	final class Restemap_Plugin {

		const VERSION      = '2.2.0';
		const TEXT_DOMAIN  = 'rest-email-api-mailer';
		const API_BASE     = 'https://platform.cyberpersons.com/email/v1';
		const SLUG         = 'restemap';
		const CAP          = 'manage_options';
		const CRON_HOOK    = 'restemap_check_delivery';

		const OPT_API_KEY    = 'restemap_api_key';
		const OPT_FROM_EMAIL = 'restemap_from_email';
		const OPT_FROM_NAME  = 'restemap_from_name';
		const OPT_ENABLED    = 'restemap_enabled';
		const OPT_PENDING    = 'restemap_pending_messages';
		const OPT_STATS      = 'restemap_account_stats';
		const OPT_MIGRATED   = 'restemap_migrated_from_legacy';

		/**
		 * Singleton instance.
		 *
		 * @var Restemap_Plugin|null
		 */
		private static $instance = null;

		/**
		 * Get the singleton instance.
		 *
		 * @return Restemap_Plugin
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Bootstrap hooks.
		 *
		 * Translations are loaded automatically by WordPress via the
		 * just-in-time loader. Since WP 6.1 it also inspects the plugin's
		 * own `languages/` folder when `Domain Path` is declared in the
		 * header, so no explicit `load_plugin_textdomain()` call is needed
		 * for any WordPress version we support (6.1+).
		 */
		private function __construct() {
			add_action( 'admin_init', array( $this, 'maybe_migrate_legacy_options' ) );
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_post_restemap_test', array( $this, 'handle_test_email' ) );
			add_action( 'admin_post_restemap_check_now', array( $this, 'handle_check_now' ) );
			add_action( self::CRON_HOOK, array( $this, 'check_delivery_status' ) );
			add_filter( 'pre_wp_mail', array( __CLASS__, 'filter_pre_wp_mail' ), 10, 2 );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_settings_action_link' ) );
		}

		/**
		 * Add a "Settings" shortcut on the Plugins list page next to the
		 * Activate/Deactivate link.
		 *
		 * @param array $links Existing action links.
		 * @return array
		 */
		public static function add_settings_action_link( $links ) {
			$settings_url  = admin_url( 'options-general.php?page=' . self::SLUG );
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'rest-email-api-mailer' )
			);
			array_unshift( $links, $settings_link );
			return $links;
		}

		/**
		 * Activation handler.
		 *
		 * Creates the protected log directory under uploads.
		 */
		public static function activate() {
			self::ensure_log_dir();
		}

		/**
		 * Deactivation handler.
		 *
		 * Clears scheduled cron events. Options are preserved so re-activation
		 * does not lose configuration; use uninstall.php for full cleanup.
		 */
		public static function deactivate() {
			$next = wp_next_scheduled( self::CRON_HOOK );
			if ( $next ) {
				wp_unschedule_event( $next, self::CRON_HOOK );
			}
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		/**
		 * One-time migration from previous option prefixes.
		 *
		 * Two generations of legacy keys may exist on a given install:
		 *   - `cyberpanel_email_*` (v2.0.0 – v2.1.0)
		 *   - `cyberpersons_*`     (pre-2.0.0, internal only)
		 *
		 * Both sets are copied into the current `restemap_*` keys (preferring
		 * the newer set when both exist) and then deleted. The previously
		 * scheduled cron event under the old hook name is unscheduled and a
		 * new one rescheduled under the current hook so delivery tracking
		 * keeps running uninterrupted. Runs silently in admin_init.
		 */
		public function maybe_migrate_legacy_options() {
			if ( get_option( self::OPT_MIGRATED ) ) {
				return;
			}

			$keys = array(
				'api_key'           => self::OPT_API_KEY,
				'from_email'        => self::OPT_FROM_EMAIL,
				'from_name'         => self::OPT_FROM_NAME,
				'enabled'           => self::OPT_ENABLED,
				'pending_messages'  => self::OPT_PENDING,
				'account_stats'     => self::OPT_STATS,
			);

			foreach ( $keys as $suffix => $current ) {
				$cyberpanel   = get_option( 'cyberpanel_email_' . $suffix, null );
				$cyberpersons = get_option( 'cyberpersons_' . $suffix, null );
				$value        = ( null !== $cyberpanel ) ? $cyberpanel : $cyberpersons;

				if ( null !== $value && false === get_option( $current, false ) ) {
					update_option( $current, $value, false );
				}
				delete_option( 'cyberpanel_email_' . $suffix );
				delete_option( 'cyberpersons_' . $suffix );
			}

			// Drop the previous generation's own migration marker.
			delete_option( 'cyberpanel_email_migrated_from_legacy' );

			// Move any scheduled cron from previous hook names to the current one.
			$legacy_hooks = array( 'cyberpanel_email_check_delivery', 'cyberpersons_check_delivery' );
			foreach ( $legacy_hooks as $old_hook ) {
				$next = wp_next_scheduled( $old_hook );
				if ( $next ) {
					wp_unschedule_event( $next, $old_hook );
					if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
						wp_schedule_single_event( max( $next, time() + 60 ), self::CRON_HOOK );
					}
				}
				wp_clear_scheduled_hook( $old_hook );
			}

			update_option( self::OPT_MIGRATED, self::VERSION, false );
		}

		/**
		 * Return the API key. Allows override via wp-config constant so the
		 * secret can live outside the database.
		 *
		 * @return string
		 */
		public static function get_api_key() {
			if ( defined( 'RESTEMAP_API_KEY' ) && '' !== RESTEMAP_API_KEY ) {
				return (string) RESTEMAP_API_KEY;
			}
			return (string) get_option( self::OPT_API_KEY, '' );
		}

		/**
		 * PHP-exit guard written as the first line of the log file so that,
		 * even if a web server serves it directly (Nginx/LiteSpeed without
		 * honoring .htaccess), the response is an empty PHP output.
		 */
		const LOG_GUARD = "<?php exit; ?>\n";

		/**
		 * Resolve the protected log file path under the uploads directory.
		 *
		 * The file uses a .log.php extension so the PHP interpreter handles it
		 * before any static-file disclosure is possible. Returns an empty
		 * string if WordPress cannot determine the uploads location, in which
		 * case the caller skips logging gracefully.
		 *
		 * @return string Absolute path (may not yet exist), or '' on failure.
		 */
		public static function get_log_file() {
			$uploads = wp_upload_dir( null, false );
			if ( empty( $uploads['basedir'] ) ) {
				return '';
			}
			return trailingslashit( $uploads['basedir'] ) . 'restemap/restemap.log.php';
		}

		/**
		 * Ensure the protected log directory and file exist. Defense in depth:
		 *  - The log file itself starts with a PHP-exit guard so a direct
		 *    request returns empty output on any stack.
		 *  - Apache and IIS get extra deny rules via .htaccess / web.config.
		 *  - An index.php keeps Apache from listing the folder.
		 */
		private static function ensure_log_dir() {
			$log_file = self::get_log_file();
			if ( '' === $log_file ) {
				return;
			}
			$dir = dirname( $log_file );

			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			$htaccess = $dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				@file_put_contents(
					$htaccess,
					"# Deny direct access\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"
				);
			}

			$index = $dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}

			$web_config = $dir . '/web.config';
			if ( ! file_exists( $web_config ) ) {
				@file_put_contents(
					$web_config,
					"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n"
				);
			}

			if ( ! file_exists( $log_file ) ) {
				@file_put_contents( $log_file, self::LOG_GUARD, LOCK_EX );
			}
		}

		/**
		 * Schedule a single delivery check 3 minutes from now when nothing is
		 * already queued.
		 */
		public static function schedule_check() {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time() + 180, self::CRON_HOOK );
			}
		}

		/**
		 * Register the options page.
		 */
		public function add_admin_menu() {
			add_options_page(
				__( 'Email API Mailer', 'rest-email-api-mailer' ),
				__( 'Email API Mailer', 'rest-email-api-mailer' ),
				self::CAP,
				self::SLUG,
				array( $this, 'settings_page' )
			);
		}

		/**
		 * Register persisted options with sanitize callbacks.
		 */
		public function register_settings() {
			register_setting(
				'restemap',
				self::OPT_API_KEY,
				array(
					'type'              => 'string',
					'sanitize_callback' => array( $this, 'sanitize_api_key' ),
					'default'           => '',
				)
			);
			register_setting(
				'restemap',
				self::OPT_FROM_EMAIL,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
					'default'           => '',
				)
			);
			register_setting(
				'restemap',
				self::OPT_FROM_NAME,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				)
			);
			register_setting(
				'restemap',
				self::OPT_ENABLED,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( $this, 'sanitize_bool' ),
					'default'           => false,
				)
			);
		}

		/**
		 * API key sanitizer. Accepts only alphanumerics, dashes and underscores.
		 *
		 * @param string $value Raw value.
		 * @return string
		 */
		public function sanitize_api_key( $value ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $value ) {
				return '';
			}
			if ( ! preg_match( '/^[A-Za-z0-9_\-]{8,256}$/', $value ) ) {
				add_settings_error(
					self::OPT_API_KEY,
					'invalid_api_key',
					__( 'The API key contains invalid characters.', 'rest-email-api-mailer' )
				);
				return (string) get_option( self::OPT_API_KEY, '' );
			}
			return $value;
		}

		/**
		 * Coerce an option value to boolean.
		 *
		 * Uses rest_sanitize_boolean() so submitted strings like "false", "0",
		 * "no" and "off" are correctly stored as the boolean false (a plain
		 * (bool) cast would treat them as the truthy non-empty string).
		 *
		 * @param mixed $value Raw value.
		 * @return bool
		 */
		public function sanitize_bool( $value ) {
			return rest_sanitize_boolean( $value );
		}

		/**
		 * Render the settings page.
		 */
		public function settings_page() {
			if ( ! current_user_can( self::CAP ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'rest-email-api-mailer' ) );
			}

			$api_key_from_constant = defined( 'RESTEMAP_API_KEY' ) && '' !== RESTEMAP_API_KEY;
			$api_key               = $api_key_from_constant ? '' : (string) get_option( self::OPT_API_KEY, '' );
			$from_email            = (string) get_option( self::OPT_FROM_EMAIL, '' );
			$from_name             = (string) get_option( self::OPT_FROM_NAME, '' );
			$enabled               = (bool) get_option( self::OPT_ENABLED, false );
			$next_check            = wp_next_scheduled( self::CRON_HOOK );
			$pending               = (array) get_option( self::OPT_PENDING, array() );
			$stats                 = (array) get_option( self::OPT_STATS, array() );

			$notice_type = '';
			$notice_msg  = '';
			$notice      = get_transient( self::admin_notice_key() );
			if ( is_array( $notice ) ) {
				delete_transient( self::admin_notice_key() );
				$notice_type = ( isset( $notice['type'] ) && 'success' === $notice['type'] ) ? 'success' : 'error';
				$notice_msg  = isset( $notice['message'] ) ? (string) $notice['message'] : '';
			}

			$current_user_email = '';
			$current_user       = wp_get_current_user();
			if ( $current_user && ! empty( $current_user->user_email ) ) {
				$current_user_email = $current_user->user_email;
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'REST Email API Mailer', 'rest-email-api-mailer' ); ?></h1>

				<?php if ( $notice_type && $notice_msg ) : ?>
					<div class="notice notice-<?php echo 'success' === $notice_type ? 'success' : 'error'; ?> is-dismissible">
						<p><?php echo esc_html( $notice_msg ); ?></p>
					</div>
				<?php endif; ?>

				<?php $this->render_stats_panel( $stats ); ?>

				<form method="post" action="options.php">
					<?php settings_fields( 'restemap' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable', 'rest-email-api-mailer' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPT_ENABLED ); ?>" value="1" <?php checked( $enabled, true ); ?>>
									<?php esc_html_e( 'Send emails through the Cyberpanel API', 'rest-email-api-mailer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When disabled, WordPress uses its default mailer (PHP mail / SMTP).', 'rest-email-api-mailer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'API Key', 'rest-email-api-mailer' ); ?></th>
							<td>
								<?php if ( $api_key_from_constant ) : ?>
									<p><code><?php esc_html_e( 'Defined via RESTEMAP_API_KEY in wp-config.php', 'rest-email-api-mailer' ); ?></code></p>
								<?php else : ?>
									<input type="password" name="<?php echo esc_attr( self::OPT_API_KEY ); ?>" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" autocomplete="new-password">
									<p class="description"><?php esc_html_e( 'API key created in your Cyberpanel account. For extra security, define RESTEMAP_API_KEY in wp-config.php instead of storing it in the database.', 'rest-email-api-mailer' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sender Email', 'rest-email-api-mailer' ); ?></th>
							<td>
								<input type="email" name="<?php echo esc_attr( self::OPT_FROM_EMAIL ); ?>" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Default sender address (must belong to a verified domain on the Cyberpanel account).', 'rest-email-api-mailer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Sender Name', 'rest-email-api-mailer' ); ?></th>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPT_FROM_NAME ); ?>" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Name displayed as the sender.', 'rest-email-api-mailer' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Settings', 'rest-email-api-mailer' ) ); ?>
				</form>

				<hr>
				<h2><?php esc_html_e( 'Send Test Email', 'rest-email-api-mailer' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="restemap_test">
					<?php wp_nonce_field( 'restemap_test' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Recipient', 'rest-email-api-mailer' ); ?></th>
							<td>
								<input type="email" name="test_to" value="<?php echo esc_attr( $current_user_email ); ?>" class="regular-text" required>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Send Test', 'rest-email-api-mailer' ), 'secondary' ); ?>
				</form>

				<hr>
				<h2><?php esc_html_e( 'Delivery Tracking', 'rest-email-api-mailer' ); ?></h2>
				<p>
					<strong><?php esc_html_e( 'Next check:', 'rest-email-api-mailer' ); ?></strong>
					<?php
					if ( $next_check ) {
						echo esc_html( wp_date( 'd/m/Y H:i:s', $next_check ) );
					} else {
						esc_html_e( 'None scheduled (one will be created on the next email send).', 'rest-email-api-mailer' );
					}
					?>
					&nbsp;|&nbsp;
					<strong><?php esc_html_e( 'Pending messages:', 'rest-email-api-mailer' ); ?></strong>
					<?php echo (int) count( $pending ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<input type="hidden" name="action" value="restemap_check_now">
					<?php wp_nonce_field( 'restemap_check_now' ); ?>
					<?php submit_button( __( 'Check Now', 'rest-email-api-mailer' ), 'secondary', 'submit', false ); ?>
				</form>

				<?php $this->render_log(); ?>
			</div>
			<?php
		}

		/**
		 * Render the account stats panel.
		 *
		 * @param array $stats Cached stats payload.
		 */
		private function render_stats_panel( $stats ) {
			if ( empty( $stats ) || empty( $stats['fetched_at'] ) ) {
				echo '<div class="notice notice-info"><p>' . esc_html__( 'Account statistics are not yet available. They will be fetched during the next delivery check.', 'rest-email-api-mailer' ) . '</p></div>';
				return;
			}

			$data       = isset( $stats['data'] ) && is_array( $stats['data'] ) ? $stats['data'] : array();
			$this_month = isset( $data['this_month'] ) && is_array( $data['this_month'] ) ? $data['this_month'] : array();

			$plan       = isset( $data['plan'] ) ? (string) $data['plan'] : 'N/A';
			$status_acc = isset( $data['status'] ) ? (string) $data['status'] : '';
			$limit      = isset( $data['monthly_limit'] ) ? (int) $data['monthly_limit'] : 0;
			$sent       = isset( $data['emails_sent_this_month'] )
				? (int) $data['emails_sent_this_month']
				: ( isset( $data['emails_sent'] ) ? (int) $data['emails_sent'] : 0 );
			$remaining  = isset( $data['emails_remaining'] ) ? (int) $data['emails_remaining'] : max( 0, $limit - $sent );
			$reputation = isset( $data['reputation_score'] )
				? $data['reputation_score']
				: ( isset( $data['reputation'] ) ? $data['reputation'] : 'N/A' );
			$domains    = isset( $data['domains_verified'] ) ? $data['domains_verified'] : '—';
			$rate_min   = isset( $data['rate_limits']['per_minute'] ) ? $data['rate_limits']['per_minute'] : '—';
			$rate_hour  = isset( $data['rate_limits']['per_hour'] ) ? $data['rate_limits']['per_hour'] : '—';
			$rate_day   = isset( $data['rate_limits']['per_day'] ) ? $data['rate_limits']['per_day'] : '—';

			$delivered = isset( $this_month['delivered'] ) ? $this_month['delivered'] : '—';
			$bounced   = isset( $this_month['bounced'] ) ? $this_month['bounced'] : '—';
			$opened    = isset( $this_month['opened'] ) ? $this_month['opened'] : '—';
			$clicked   = isset( $this_month['clicked'] ) ? $this_month['clicked'] : '—';

			$pct_used  = $limit > 0 ? round( ( $sent / $limit ) * 100, 1 ) : 0;
			$bar_color = $pct_used > 90 ? '#d63638' : ( $pct_used > 70 ? '#dba617' : '#00a32a' );

			$fetched_str = wp_date( 'd/m/Y H:i', (int) $stats['fetched_at'] );
			?>
			<div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:16px 20px;margin:15px 0;border-radius:4px;">
				<h2 style="margin:0 0 12px;"><?php esc_html_e( 'Account Dashboard', 'rest-email-api-mailer' ); ?></h2>
				<div style="display:flex;flex-wrap:wrap;gap:20px;">
					<div style="flex:1;min-width:220px;">
						<h3 style="margin:0 0 8px;font-size:14px;color:#1d2327;"><?php esc_html_e( 'Account', 'rest-email-api-mailer' ); ?></h3>
						<table style="width:100%;border-collapse:collapse;">
							<tr>
								<td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Plan', 'rest-email-api-mailer' ); ?></td>
								<td style="padding:4px 8px;">
									<strong><?php echo esc_html( ucfirst( $plan ) ); ?></strong>
									<?php if ( '' !== $status_acc ) : ?>
										<span style="color:#00a32a;font-size:12px;">(<?php echo esc_html( $status_acc ); ?>)</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr><td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Reputation', 'rest-email-api-mailer' ); ?></td><td style="padding:4px 8px;"><strong><?php echo esc_html( (string) $reputation ); ?></strong>/100</td></tr>
							<tr><td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Verified domains', 'rest-email-api-mailer' ); ?></td><td style="padding:4px 8px;"><strong><?php echo esc_html( (string) $domains ); ?></strong></td></tr>
							<tr><td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Rate limit', 'rest-email-api-mailer' ); ?></td><td style="padding:4px 8px;"><span style="font-size:12px;"><?php echo esc_html( (string) $rate_min ); ?>/min &bull; <?php echo esc_html( (string) $rate_hour ); ?>/h &bull; <?php echo esc_html( (string) $rate_day ); ?>/<?php esc_html_e( 'day', 'rest-email-api-mailer' ); ?></span></td></tr>
						</table>
					</div>
					<div style="flex:1;min-width:220px;">
						<h3 style="margin:0 0 8px;font-size:14px;color:#1d2327;"><?php esc_html_e( 'Monthly Usage', 'rest-email-api-mailer' ); ?></h3>
						<table style="width:100%;border-collapse:collapse;">
							<tr><td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Sent', 'rest-email-api-mailer' ); ?></td><td style="padding:4px 8px;"><strong><?php echo esc_html( number_format_i18n( $sent ) ); ?></strong> / <?php echo esc_html( number_format_i18n( $limit ) ); ?></td></tr>
							<tr><td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Remaining', 'rest-email-api-mailer' ); ?></td><td style="padding:4px 8px;"><strong><?php echo esc_html( number_format_i18n( $remaining ) ); ?></strong></td></tr>
							<tr><td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Delivered', 'rest-email-api-mailer' ); ?></td><td style="padding:4px 8px;"><strong style="color:#00a32a;"><?php echo esc_html( (string) $delivered ); ?></strong></td></tr>
							<tr><td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Bounces', 'rest-email-api-mailer' ); ?></td><td style="padding:4px 8px;"><strong style="color:<?php echo ( is_numeric( $bounced ) && (int) $bounced > 0 ) ? '#d63638' : '#00a32a'; ?>;"><?php echo esc_html( (string) $bounced ); ?></strong></td></tr>
						</table>
					</div>
					<div style="flex:1;min-width:220px;">
						<h3 style="margin:0 0 8px;font-size:14px;color:#1d2327;"><?php esc_html_e( 'Engagement', 'rest-email-api-mailer' ); ?></h3>
						<table style="width:100%;border-collapse:collapse;">
							<tr>
								<td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Opened', 'rest-email-api-mailer' ); ?></td>
								<td style="padding:4px 8px;">
									<strong style="color:#2271b1;"><?php echo esc_html( (string) $opened ); ?></strong>
									<?php if ( is_numeric( $opened ) && is_numeric( $delivered ) && (int) $delivered > 0 ) : ?>
										<span style="font-size:12px;color:#646970;">(<?php echo esc_html( (string) round( ( (int) $opened / (int) $delivered ) * 100, 1 ) ); ?>%)</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td style="padding:4px 8px;color:#646970;"><?php esc_html_e( 'Clicked', 'rest-email-api-mailer' ); ?></td>
								<td style="padding:4px 8px;">
									<strong style="color:#2271b1;"><?php echo esc_html( (string) $clicked ); ?></strong>
									<?php if ( is_numeric( $clicked ) && is_numeric( $delivered ) && (int) $delivered > 0 ) : ?>
										<span style="font-size:12px;color:#646970;">(<?php echo esc_html( (string) round( ( (int) $clicked / (int) $delivered ) * 100, 1 ) ); ?>%)</span>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				</div>
				<div style="margin-top:14px;">
					<div style="background:#f0f0f1;border-radius:4px;height:22px;overflow:hidden;position:relative;">
						<div style="background:<?php echo esc_attr( $bar_color ); ?>;height:100%;width:<?php echo esc_attr( (string) min( $pct_used, 100 ) ); ?>%;border-radius:4px;transition:width 0.3s;"></div>
						<span style="position:absolute;top:2px;left:10px;font-size:12px;font-weight:bold;color:#1d2327;"><?php echo esc_html( number_format_i18n( $sent ) ); ?> / <?php echo esc_html( number_format_i18n( $limit ) ); ?></span>
					</div>
					<p style="margin:4px 0 0;font-size:12px;color:#646970;">
						<?php
						printf(
							/* translators: 1: percentage used, 2: last update date/time */
							esc_html__( '%1$s%% of monthly quota used | Updated on %2$s', 'rest-email-api-mailer' ),
							esc_html( (string) $pct_used ),
							esc_html( $fetched_str )
						);
						?>
					</p>
				</div>
			</div>
			<?php
		}

		/**
		 * Handle "Send Test Email" form submission.
		 */
		public function handle_test_email() {
			if ( ! current_user_can( self::CAP ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'rest-email-api-mailer' ) );
			}
			check_admin_referer( 'restemap_test' );

			$to = isset( $_POST['test_to'] )
				? sanitize_email( wp_unslash( $_POST['test_to'] ) )
				: '';

			if ( '' === $to || ! is_email( $to ) ) {
				$this->redirect_with_notice(
					'error',
					__( 'Invalid recipient address.', 'rest-email-api-mailer' )
				);
			}

			$site_name = get_bloginfo( 'name' );
			/* translators: 1: site name, 2: current date/time */
			$subject = sprintf( __( '[%1$s] Email API test - %2$s', 'rest-email-api-mailer' ), $site_name, current_time( 'd/m/Y H:i:s' ) );

			$body  = '<html><body>';
			$body .= '<h1>' . esc_html__( 'Email Test', 'rest-email-api-mailer' ) . '</h1>';
			$body .= '<p>' . esc_html__( 'This email was sent through the Cyberpanel API.', 'rest-email-api-mailer' ) . '</p>';
			$body .= '<p>' . esc_html__( 'Site:', 'rest-email-api-mailer' ) . ' ' . esc_html( $site_name ) . '</p>';
			$body .= '<p>' . esc_html__( 'Date/time:', 'rest-email-api-mailer' ) . ' ' . esc_html( current_time( 'd/m/Y H:i:s' ) ) . '</p>';
			$body .= '</body></html>';

			$result = wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );

			if ( $result ) {
				/* translators: %s: recipient email */
				$msg = sprintf( __( 'Test email successfully sent to %s.', 'rest-email-api-mailer' ), $to );
				$this->redirect_with_notice( 'success', $msg );
			} else {
				$this->redirect_with_notice( 'error', __( 'Failed to send test email.', 'rest-email-api-mailer' ) );
			}
		}

		/**
		 * Handle "Check Now" form submission.
		 */
		public function handle_check_now() {
			if ( ! current_user_can( self::CAP ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'rest-email-api-mailer' ) );
			}
			check_admin_referer( 'restemap_check_now' );
			$this->check_delivery_status();
			$this->redirect_with_notice( 'success', __( 'Delivery check executed.', 'rest-email-api-mailer' ) );
		}

		/**
		 * Redirect back to the settings page carrying a notice.
		 *
		 * Notices are stored in a per-user transient and read once by the
		 * settings page; this prevents an attacker from crafting a link that
		 * makes a fake admin notice appear to another user.
		 *
		 * @param string $type    success|error.
		 * @param string $message Notice text.
		 */
		private function redirect_with_notice( $type, $message ) {
			set_transient(
				self::admin_notice_key(),
				array(
					'type'    => ( 'success' === $type ) ? 'success' : 'error',
					'message' => (string) $message,
				),
				60
			);
			wp_safe_redirect(
				add_query_arg( 'page', self::SLUG, admin_url( 'options-general.php' ) )
			);
			exit;
		}

		/**
		 * Per-user transient key used for admin notices.
		 *
		 * @return string
		 */
		private static function admin_notice_key() {
			return 'restemap_notice_' . (int) get_current_user_id();
		}

		/**
		 * Register a message id for later delivery verification.
		 *
		 * @param string $message_id Remote id.
		 * @param string $to         Recipient.
		 * @param string $subject    Subject line.
		 */
		public static function track_message( $message_id, $to, $subject ) {
			$pending = (array) get_option( self::OPT_PENDING, array() );

			$pending[ $message_id ] = array(
				'to'      => $to,
				'subject' => $subject,
				'sent_at' => time(),
				'checks'  => 0,
			);

			if ( count( $pending ) > 200 ) {
				uasort(
					$pending,
					static function ( $a, $b ) {
						return (int) $a['sent_at'] - (int) $b['sent_at'];
					}
				);
				$pending = array_slice( $pending, -200, null, true );
			}

			update_option( self::OPT_PENDING, $pending, false );

			self::schedule_check();
		}

		/**
		 * Fetch the account statistics from the API and cache them.
		 *
		 * @param string $api_key API key.
		 */
		private function fetch_account_stats( $api_key ) {
			$response = wp_remote_get(
				self::API_BASE . '/account/stats',
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( $code >= 200 && $code < 300 && ! empty( $data ) ) {
				$stats_data = isset( $data['data'] ) ? $data['data'] : $data;
				update_option(
					self::OPT_STATS,
					array(
						'data'       => $stats_data,
						'fetched_at' => time(),
					),
					false
				);
			}
		}

		/**
		 * Cron callback: check delivery status for pending messages and refresh
		 * account stats.
		 */
		public function check_delivery_status() {
			$api_key = self::get_api_key();
			if ( '' === $api_key ) {
				return;
			}

			$this->fetch_account_stats( $api_key );

			$pending = (array) get_option( self::OPT_PENDING, array() );
			if ( empty( $pending ) ) {
				return;
			}

			$api_headers = array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			);

			$updated     = false;
			$to_remove   = array();
			$has_pending = false;

			foreach ( $pending as $msg_id => $info ) {
				if ( ( time() - (int) $info['sent_at'] ) > 172800 ) {
					self::log( 'EXPIRED', $info['to'], $info['subject'], "message_id: {$msg_id} - no status after 48h" );
					$to_remove[] = $msg_id;
					$updated     = true;
					continue;
				}

				if ( (int) $info['checks'] >= 20 ) {
					self::log( 'TIMEOUT', $info['to'], $info['subject'], "message_id: {$msg_id} - 20 checks without confirmation" );
					$to_remove[] = $msg_id;
					$updated     = true;
					continue;
				}

				$response = wp_remote_get(
					self::API_BASE . '/messages/' . rawurlencode( $msg_id ),
					array(
						'timeout' => 15,
						'headers' => $api_headers,
					)
				);

				if ( is_wp_error( $response ) ) {
					$pending[ $msg_id ]['checks'] = (int) $pending[ $msg_id ]['checks'] + 1;
					$has_pending                   = true;
					$updated                       = true;
					continue;
				}

				$code = wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );

				if ( $code >= 200 && $code < 300 && ! empty( $data ) ) {
					$msg_data    = isset( $data['data'] ) ? $data['data'] : $data;
					$status      = isset( $msg_data['status'] ) ? (string) $msg_data['status'] : 'unknown';
					$delivered   = isset( $msg_data['delivered_at'] ) ? $msg_data['delivered_at'] : null;
					$opened      = ! empty( $msg_data['opened'] );
					$open_count  = isset( $msg_data['open_count'] ) ? (int) $msg_data['open_count'] : 0;
					$clicked     = ! empty( $msg_data['clicked'] );
					$click_count = isset( $msg_data['click_count'] ) ? (int) $msg_data['click_count'] : 0;

					$status_lower = strtolower( $status );

					if ( in_array( $status_lower, array( 'bounced', 'failed', 'rejected', 'complaint', 'undeliverable' ), true ) ) {
						self::log( 'BOUNCE', $info['to'], $info['subject'], "message_id: {$msg_id} - status: {$status}" );
						$to_remove[] = $msg_id;
						$updated     = true;
						continue;
					}

					if ( ! empty( $delivered ) || 'delivered' === $status_lower ) {
						$details = "message_id: {$msg_id} - DELIVERED";
						if ( ! empty( $delivered ) ) {
							$details .= " at {$delivered}";
						}
						if ( $opened ) {
							$details .= " | opened {$open_count}x";
						}
						if ( $clicked ) {
							$details .= " | clicked {$click_count}x";
						}
						self::log( 'DELIVERED', $info['to'], $info['subject'], $details );
						$to_remove[] = $msg_id;
						$updated     = true;
						continue;
					}

					$pending[ $msg_id ]['checks'] = (int) $pending[ $msg_id ]['checks'] + 1;
					$has_pending                   = true;
					$updated                       = true;
				} else {
					$pending[ $msg_id ]['checks'] = (int) $pending[ $msg_id ]['checks'] + 1;
					$has_pending                   = true;
					$updated                       = true;
				}

				usleep( 200000 );
			}

			foreach ( $to_remove as $msg_id ) {
				unset( $pending[ $msg_id ] );
			}

			if ( $updated ) {
				update_option( self::OPT_PENDING, $pending, false );
			}

			if ( $has_pending || ! empty( $pending ) ) {
				$next = wp_next_scheduled( self::CRON_HOOK );
				if ( $next ) {
					wp_unschedule_event( $next, self::CRON_HOOK );
				}
				wp_schedule_single_event( time() + 180, self::CRON_HOOK );
			}
		}

		/**
		 * Short-circuit wp_mail() through the pre_wp_mail filter (WP 5.7+).
		 *
		 * Returning null means "let WordPress's default mailer run"; returning
		 * a boolean short-circuits wp_mail() and reports success/failure.
		 * We opt in only when the plugin toggle is enabled and the message
		 * carries no attachments (the API does not accept them yet).
		 *
		 * @param null|bool $short_circuit Existing short-circuit value.
		 * @param array     $atts          Arguments passed to wp_mail().
		 * @return null|bool
		 */
		public static function filter_pre_wp_mail( $short_circuit, $atts ) {
			if ( null !== $short_circuit ) {
				return $short_circuit;
			}
			if ( ! get_option( self::OPT_ENABLED, false ) ) {
				return null;
			}
			if ( ! empty( $atts['attachments'] ) ) {
				// The API does not support attachments yet; let core handle these.
				return null;
			}

			return self::send(
				isset( $atts['to'] ) ? $atts['to'] : '',
				isset( $atts['subject'] ) ? $atts['subject'] : '',
				isset( $atts['message'] ) ? $atts['message'] : '',
				isset( $atts['headers'] ) ? $atts['headers'] : '',
				isset( $atts['attachments'] ) ? $atts['attachments'] : array()
			);
		}

		/**
		 * Send an email through the Cyberpanel API.
		 *
		 * @param mixed        $to          Recipient(s).
		 * @param string       $subject     Subject.
		 * @param string       $message     Body (HTML or text).
		 * @param string|array $headers     Headers.
		 * @param array        $attachments Attachments (unsupported by the API).
		 * @return bool
		 */
		public static function send( $to, $subject, $message, $headers = '', $attachments = array() ) {
			unset( $attachments );

			$api_key    = self::get_api_key();
			$from_email = (string) get_option( self::OPT_FROM_EMAIL, '' );
			$from_name  = (string) get_option( self::OPT_FROM_NAME, '' );

			if ( '' === $api_key || '' === $from_email ) {
				self::log( 'ERROR', $to, $subject, 'Missing API key or sender email.' );
				return false;
			}

			$cc           = '';
			$bcc          = '';
			$reply_to     = '';
			$content_type = 'text/plain';

			if ( ! is_array( $headers ) ) {
				$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
			}

			foreach ( $headers as $header ) {
				if ( empty( $header ) || false === strpos( $header, ':' ) ) {
					continue;
				}

				list( $name, $value ) = explode( ':', $header, 2 );
				$name  = strtolower( trim( $name ) );
				$value = trim( $value );

				switch ( $name ) {
					case 'from':
						if ( preg_match( '/^(.+)\s*<(.+)>$/', $value, $matches ) ) {
							$from_name  = trim( $matches[1], ' "\'' );
							$from_email = trim( $matches[2] );
						} elseif ( is_email( $value ) ) {
							$from_email = $value;
						}
						break;
					case 'cc':
						$cc = $value;
						break;
					case 'bcc':
						$bcc = $value;
						break;
					case 'reply-to':
						$reply_to = $value;
						if ( preg_match( '/<(.+)>/', $reply_to, $m ) ) {
							$reply_to = $m[1];
						}
						break;
					case 'content-type':
						if ( stripos( $value, 'text/html' ) !== false ) {
							$content_type = 'text/html';
						}
						break;
				}
			}

			unset( $from_name );

			$recipients = array();

			if ( is_array( $to ) ) {
				foreach ( $to as $addr ) {
					foreach ( explode( ',', (string) $addr ) as $a ) {
						$email = self::extract_email( trim( $a ) );
						if ( '' !== $email ) {
							$recipients[] = $email;
						}
					}
				}
			} else {
				foreach ( explode( ',', (string) $to ) as $a ) {
					$email = self::extract_email( trim( $a ) );
					if ( '' !== $email ) {
						$recipients[] = $email;
					}
				}
			}

			if ( '' !== $cc ) {
				foreach ( explode( ',', $cc ) as $a ) {
					$email = self::extract_email( trim( $a ) );
					if ( '' !== $email ) {
						$recipients[] = $email;
					}
				}
			}

			if ( '' !== $bcc ) {
				foreach ( explode( ',', $bcc ) as $a ) {
					$email = self::extract_email( trim( $a ) );
					if ( '' !== $email ) {
						$recipients[] = $email;
					}
				}
			}

			$recipients = array_values( array_unique( $recipients ) );

			if ( empty( $recipients ) ) {
				self::log( 'ERROR', $to, $subject, 'No valid recipient.' );
				return false;
			}

			$base_payload = array(
				'from'     => $from_email,
				'subject'  => $subject,
				'tags'     => array( 'wordpress', 'transactional' ),
				'metadata' => array(
					'source' => 'wp_mail',
					'site'   => home_url(),
				),
			);

			if ( 'text/html' === $content_type ) {
				$base_payload['html'] = $message;
				$base_payload['text'] = wp_strip_all_tags( $message );
			} else {
				$base_payload['text'] = $message;
			}

			if ( '' !== $reply_to ) {
				$base_payload['reply_to'] = $reply_to;
			}

			$all_ok      = true;
			$api_headers = array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			);

			foreach ( $recipients as $recipient ) {
				$payload       = $base_payload;
				$payload['to'] = $recipient;

				$response = wp_remote_post(
					self::API_BASE . '/send',
					array(
						'timeout' => 30,
						'headers' => $api_headers,
						'body'    => wp_json_encode( $payload ),
					)
				);

				if ( is_wp_error( $response ) ) {
					self::log( 'ERROR', $recipient, $subject, $response->get_error_message() );
					$all_ok = false;
					continue;
				}

				$code = wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );

				if ( $code >= 200 && $code < 300 && ! empty( $data['success'] ) ) {
					$msg_id = isset( $data['data']['message_id'] ) ? (string) $data['data']['message_id'] : 'N/A';
					self::log( 'SENT', $recipient, $subject, "message_id: {$msg_id} - awaiting delivery" );

					if ( 'N/A' !== $msg_id ) {
						self::track_message( $msg_id, $recipient, $subject );
					}
				} else {
					$error = isset( $data['message'] ) ? $data['message'] : ( isset( $data['error'] ) ? $data['error'] : $body );
					if ( is_array( $error ) ) {
						$error = wp_json_encode( $error );
					}
					self::log( 'ERROR', $recipient, $subject, "HTTP {$code}: {$error}" );
					$all_ok = false;
				}
			}

			return $all_ok;
		}

		/**
		 * Extract an email address from either a plain string or a
		 * "Name <email@domain>" expression.
		 *
		 * @param string $str Raw input.
		 * @return string
		 */
		private static function extract_email( $str ) {
			if ( preg_match( '/<([^>]+)>/', $str, $m ) ) {
				return (string) sanitize_email( $m[1] );
			}
			return (string) sanitize_email( $str );
		}

		/**
		 * Append an entry to the plugin log file.
		 *
		 * @param string $status  Event type.
		 * @param mixed  $to      Recipient(s).
		 * @param string $subject Subject.
		 * @param string $detail  Extra details.
		 */
		private static function log( $status, $to, $subject, $detail = '' ) {
			$log_file = self::get_log_file();
			if ( '' === $log_file ) {
				return;
			}
			if ( ! file_exists( $log_file ) ) {
				self::ensure_log_dir();
			}

			$line = sprintf(
				"[%s] %s | To: %s | Subject: %s | %s\n",
				current_time( 'Y-m-d H:i:s' ),
				$status,
				is_array( $to ) ? implode( ', ', $to ) : (string) $to,
				(string) $subject,
				(string) $detail
			);

			if ( file_exists( $log_file ) && filesize( $log_file ) > 512000 ) {
				$lines = @file( $log_file );
				if ( is_array( $lines ) ) {
					// Preserve the PHP exit guard on the first line; keep the last 200 real entries.
					$data_lines = array_slice( $lines, 1 );
					$data_lines = array_slice( $data_lines, -200 );
					@file_put_contents( $log_file, self::LOG_GUARD . implode( '', $data_lines ), LOCK_EX );
				}
			}

			@file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
		}

		/**
		 * Render the recent log entries.
		 */
		private function render_log() {
			$log_file = self::get_log_file();
			if ( '' === $log_file || ! file_exists( $log_file ) ) {
				return;
			}

			$lines = @file( $log_file );
			if ( ! is_array( $lines ) || count( $lines ) <= 1 ) {
				return;
			}
			// Drop the PHP exit guard that lives on the first line.
			$lines = array_slice( $lines, 1 );
			$lines = array_slice( $lines, -30 );
			$lines = array_reverse( $lines );

			echo '<hr><h2>' . esc_html__( 'Send & Delivery Log (last 30)', 'rest-email-api-mailer' ) . '</h2>';
			echo '<div style="background:#1d2327;color:#c3c4c7;padding:15px;border-radius:4px;font-family:monospace;font-size:13px;max-height:500px;overflow-y:auto;">';
			foreach ( $lines as $line ) {
				$color = '#c3c4c7';
				if ( false !== strpos( $line, 'ERROR' ) || false !== strpos( $line, 'BOUNCE' ) ) {
					$color = '#d63638';
				} elseif ( false !== strpos( $line, 'EXPIRED' ) || false !== strpos( $line, 'TIMEOUT' ) ) {
					$color = '#dba617';
				} elseif ( false !== strpos( $line, 'SENT' ) ) {
					$color = '#72aee6';
				} elseif ( false !== strpos( $line, 'DELIVERED' ) ) {
					$color = '#00a32a';
				}
				echo '<div style="color:' . esc_attr( $color ) . ';padding:3px 0;border-bottom:1px solid #2c3338;">' . esc_html( trim( $line ) ) . '</div>';
			}
			echo '</div>';
			echo '<p class="description" style="margin-top:8px;">';
			echo '<span style="color:#72aee6;">&#9632;</span> SENT &nbsp;';
			echo '<span style="color:#00a32a;">&#9632;</span> DELIVERED &nbsp;';
			echo '<span style="color:#d63638;">&#9632;</span> ERROR/BOUNCE &nbsp;';
			echo '<span style="color:#dba617;">&#9632;</span> EXPIRED/TIMEOUT';
			echo '</p>';
		}
	}
}

// Bootstrap.
Restemap_Plugin::get_instance();

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Restemap_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Restemap_Plugin', 'deactivate' ) );
