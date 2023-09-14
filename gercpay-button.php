<?php
/**
 * Plugin Name:  GercPay Button
 * Plugin URI:   https://gercpay.com.ua/
 * Description:  This plugin allows you to create a button that lets the customers pay via GercPay.
 * Version:      1.0.0
 * Author:       MustPay
 * Author URI:   https://mustpay.tech
 * Domain Path:  /lang
 * Text Domain:  gercpay-button
 * License:      GPLv3
 * License URI:  http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package      gercpay-button
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables for translate plugin header.
$plugin_name        = esc_html__( 'GercPay Button', 'gercpay-button' );
$plugin_description = esc_html__( 'This plugin allows you to create a button that lets the customers pay via GercPay.', 'gercpay-button' );

// Plugin management methods.
register_activation_hook( __FILE__, array( 'GercPay_Button', 'gpb_activate' ) );
register_deactivation_hook( __FILE__, array( 'GercPay_Button', 'gpb_deactivate' ) );
register_uninstall_hook( __FILE__, array( 'GercPay_Button', 'gpb_uninstall' ) );
require_once 'GercPayApi.php';

add_action( 'plugins_loaded', array( 'GercPay_Button', 'gpb_init' ), 0 );

/**
 * GercPay_Button class.
 */
class GercPay_Button {

	public const GPB_PLUGIN_VERSION = '1.0.0';

	public const GPB_MODE_NONE        = 'none';
	public const GPB_MODE_PHONE       = 'phone';
	public const GPB_MODE_EMAIL       = 'email';
	public const GPB_MODE_PHONE_EMAIL = 'phone_email';

	/**
	 * Plugin instance.
	 *
	 * @var GercPay_Button
	 */
	protected static $instance;

	/**
	 * List of Plugin settings params.
	 *
	 * @var array
	 */
    protected $fillable = array(
        'merchant_id',
        'secret_key',
        'currency',
        'currency_popup',
        'language',
        'mode',
        'pay_button_text',
        'order_prefix',
        'btn_shape',
        'btn_height',
        'btn_width',
        'btn_color',
        'btn_border',
        'btn_inverse',
    );

	protected $checkout_params = array(
		self::GPB_MODE_NONE        => array(
			'_wpnonce',
			'gpb_product_name',
			'gpb_product_price',
			'gpb_product_currency',
		),
		self::GPB_MODE_PHONE       => array(
			'_wpnonce',
			'gpb_client_name',
			'gpb_phone',
			'gpb_product_name',
			'gpb_product_price',
      'gpb_product_currency',
		),
		self::GPB_MODE_EMAIL       => array(
			'_wpnonce',
			'gpb_client_name',
			'gpb_email',
			'gpb_product_name',
			'gpb_product_price',
      'gpb_product_currency',
		),
		self::GPB_MODE_PHONE_EMAIL => array(
			'_wpnonce',
			'gpb_client_name',
			'gpb_phone',
			'gpb_email',
			'gpb_product_name',
			'gpb_product_price',
      'gpb_product_currency',
		),
	);

	/**
	 * Create plugin instance.
	 *
	 * @return GercPay_Button
	 */
	public static function gpb_init() {
		is_null( self::$instance ) && self::$instance = new self();
		return self::$instance;
	}

	/**
	 * Constructor method.
	 */
	public function __construct() {
		// Load plugin translations.
		load_plugin_textdomain( 'gercpay-button', false, basename( __DIR__ ) . '/lang' );

		// Register Gutenberg block.
		add_action( 'init', array( $this, 'gpb_register_gutenberg_block' ) );

		// Translation block script file.
		add_action( 'init', array( $this, 'gpb_set_script_translations' ) );

		// Plugin front scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'gpb_link_front_scripts' ), 500 );

		// Plugin settings page styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'gpb_link_admin_styles' ), 500 );

    // Plugin settings page scripts.
    add_action( 'admin_enqueue_scripts', array( $this, 'gpb_link_admin_scripts' ), 500 );

		// Settings page menu link.
		add_action( 'admin_menu', array( $this, 'gpb_plugin_menu' ) );

		self::add_media_button_on_editor_page();

		// Settings plugin link in plugin list.
		add_filter( 'plugin_action_links', array( $this, 'gpb_plugin_settings_link' ), 10, 2 );

		// Support plugin link in plugin list.
		$plugin = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_{$plugin}", array( $this, 'gpb_plugin_support_link' ) );

		// Shortcode handler.
		add_shortcode( 'gpb', array( $this, 'gpb_make_button_from_shortcode' ) );

		// Show payment result message.
		if ( isset( $_GET['gercpay_result'] ) && ! is_admin() ) {
			add_filter( 'wp_head', array( $this, 'gpb_show_payment_result_message' ) );
		}

		// Add popup checkout form.
		add_action( 'wp_footer', array( $this, 'gpb_checkout_form' ) );

		// Ajax popup handler.
		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_popup_handler', array( $this, 'gpb_payment_handler' ) );
			add_action( 'wp_ajax_nopriv_popup_handler', array( $this, 'gpb_payment_handler' ) );
		}
	}

	/**
	 * Add media button on editor page.
	 *
	 * @return void
	 */
	public static function add_media_button_on_editor_page() {
		global $pagenow, $typenow;

		if ( 'download' !== $typenow && in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ), true ) ) {

			add_action( 'media_buttons', array( self::class, 'gpb_add_my_media_button' ), 20 );
			add_action( 'admin_footer', array( self::class, 'gpb_add_inline_popup_content' ) );
		}
	}

	/**
	 * Add media button.
	 *
	 * @return void
	 */
	public function gpb_add_my_media_button() {
		echo '<a href="#TB_inline?width=600&height=400&inlineId=gpb_popup_container" title="GercPay Button" id="insert-my-media" class="button thickbox">GercPay Button</a>';
	}

	/**
	 * Add inline popup content.
	 *
	 * @return void
	 */
	public static function gpb_add_inline_popup_content() {
	}

	/**
	 * Activate plugin method.
	 *
	 * @return void
	 */
	public static function gpb_activate() {

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		check_admin_referer( "activate-plugin_{$plugin}" );

		$gpb_settings = array(
			'merchant_id'     => '',
			'secret_key'      => '',
			'currency'        => 'UAH',
      'currency_popup'  => ['UAH' => 'on'],
			'language'        => 'ua',
			'mode'            => self::GPB_MODE_PHONE,
			'pay_button_text' => 'Pay',
			'order_prefix'    => 'GP',
		);

		add_option( 'gpb_settings', $gpb_settings );
	}

	/**
	 * Deactivate plugin method.
	 *
	 * @return void
	 */
	public static function gpb_deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$plugin = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
		check_admin_referer( "deactivate-plugin_{$plugin}" );
	}

	/**
	 * Uninstall plugin method.
	 *
	 * @return void
	 */
	public static function gpb_uninstall() {
	}

	/**
	 * Adds menu item to the settings.
	 *
	 * @return void
	 */
	public function gpb_plugin_menu() {
		$title = __( 'GercPay Button', 'gercpay-button' );
		add_options_page( $title, $title, 'manage_options', 'gpb-settings', array( $this, 'gpb_plugin_options' ) );
	}

	/**
	 * Adds GercPay plugin settings link in plugin list.
	 *
	 * @param array  $links Plugin links in menu list.
	 * @param string $file Plugin file path.
	 *
	 * @return array
	 */
	public function gpb_plugin_settings_link( $links, $file ) {
		static $this_plugin;

		if ( ! $this_plugin ) {
			$this_plugin = plugin_basename( __FILE__ );
		}

		if ( $file === $this_plugin ) {
			$settings_label = __( 'Settings', 'gercpay-button' );
			$settings_link  = "<a href='" . get_bloginfo( 'wpurl' ) . "/wp-admin/admin.php?page=gpb-settings'>{$settings_label}</a>";
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * Adds Gercpay support link in plugin list.
	 *
	 * @param array $links Plugin links in menu list.
	 *
	 * @return array
	 */
	public function gpb_plugin_support_link( $links ): array {
		unset( $links['edit'] );

		$links[] = '<a target="_blank" href="https://t.me/GercPaySupport">' . __( 'Support', 'gercpay-button' ) . '</a>';

		return $links;
	}

	/**
	 * Render plugin options page.
	 *
	 * @return void
	 */
	public function gpb_plugin_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'gercpay-button' ) );
		}

		// Settings page.
		echo '<div class="gpb-wrapper">';
		echo '<div class="gpb-header">';
		echo '<h1>' . esc_attr__( 'GercPay Button Settings', 'gercpay-button' ) . '</h1>';
		echo '</div>';
		echo '<div class="gpb-main"></div>';
		echo '</div>';

		echo '<table>';
		echo '<tr>';
		echo '<td class="gpb-td">';

		echo '<form method="POST" action="' . esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . '">';

		// Save and update options.
		if ( isset( $_POST['update'] ) ) {

			$result = $this->gpb_update_options();
      if ($result === true) {
          echo "<br /><div class='updated'>";
          echo '<p><strong>' . esc_attr__( 'Settings updated', 'gercpay-button' ) . '</strong></p>';
          echo '</div>';
      }
		}

		$settings = self::gpb_get_settings();
    // Get button image.
    $styles = $this->gpb_get_button_styles();
    $attributes = array(
        'label' => __('Donate', 'gercpay-button'),
        'label_color' => 'Orange',
        'label_fontsize' => '18px'
    );
    $styles_text = $this->gpb_get_button_styles( $attributes );

		echo '</td><td></td></tr><tr><td>';

		// Settings form.
		echo '<br />';
		?>

      <div class="gpb-section-header" id="gpb-section-header"><?php _e( 'Usage', 'gercpay-button' ); ?></div>
      <div class="gpb-section">
        <p>
          <?php esc_html_e( 'By using this you can create shortcodes which will show up as "GercPay Button" on your site.', 'gercpay-button' ); ?>
        </p>
        <p>
          <?php
          esc_html_e(
            'You can put the "GercPay Button" as many times in a page or post as you want, there is no limit. If you want to remove a "GercPay Button", just remove the shortcode text in your page or post.',
            'gercpay-button'
          );
          ?>
        </p>
      </div>

      <div class="gpb-section-header"><?php _e( 'Account settings', 'gercpay-button' ); ?></div>
      <div class="gpb-section">
        <div class="gpb-input-group">
          <label for="merchant_id" class="gpb-label"><?php _e( 'Merchant ID', 'gercpay-button' ); ?></label>
          <input type="text" name="merchant_id" id="merchant_id" class="gpb-input"
             value="<?php echo $settings['merchant_id']; ?>">
          <div class="gpb-description" id="merchant_id_description">
            <?php _e( 'Given to Merchant by GercPay', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="secret_key" class="gpb-label"><?php _e( 'Secret key', 'gercpay-button' ); ?></label>
          <input type="text" name="secret_key" id="secret_key" class="gpb-input"
             value="<?php echo $settings['secret_key']; ?>">
          <div class="gpb-description" id="merchant_id_description">
            <?php _e( 'Given to Merchant by GercPay', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="currency" class="gpb-label"><?php _e( 'Default currency', 'gercpay-button' ); ?></label>
          <select type="text" name="currency" id="currency" class="gpb-input">
          <?php echo $this->gpb_get_select_options( self::gpb_get_currencies(), $settings['currency'] ); ?>
          </select>
          <div class="gpb-description" id="merchant_id_description">
            <?php _e( 'Specify your default currency', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="currency_popup" class="gpb-label"><?php _e( 'Currencies for popup window', 'gercpay-button' ); ?></label>
            <div class="gpb-checkbox-group">
                <?php foreach (self::gpb_get_currencies() as $key => $currency) :?>
                <label for="currency_popup[<?php echo $key ?>]" class="gpb-checkbox-label">
                  <input type="checkbox" name="currency_popup[<?php echo $key ?>]"
                         id="currency_popup[<?php echo $key ?>]" class="gpb-input" <?php echo $settings['currency_popup'][$key] ? 'checked' : '' ?>>
                    <?php echo $currency['label'] ?>
                </label>
              <?php endforeach; ?>
            </div>
          <div class="gpb-description" id="currency_popup_description">
              <?php _e( 'Currencies available at the time of entering the amount', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
        <label for="language" class="gpb-label"><?php _e( 'Language', 'gercpay-button' ); ?></label>
        <select type="text" name="language" id="language" class="gpb-input">
        <?php echo $this->gpb_get_select_options( self::gpb_get_languages(), $settings['language'] ); ?>
        </select>
        <div class="gpb-description" id="language_description">
        <?php _e( 'Specify GercPay payment page language', 'gercpay-button' ); ?>
        </div>
        </div>
        <div class="gpb-input-group">
        <label for="mode" class="gpb-label"><?php _e( 'Required fields', 'gercpay-button' ); ?></label>
        <select type="text" name="mode" id="mode" class="gpb-input">
        <?php echo $this->gpb_get_select_options( self::gpb_get_required_fields(), $settings['mode'] ); ?>
        </select>
        <div class="gpb-description" id="mode_description">
        <?php _e( 'Fields required to be entered by the buyer', 'gercpay-button' ); ?>
        </div>
      </div>
        <div class="gpb-input-group">
        <label for="pay_button_text" class="gpb-label"><?php _e( 'GercPay button text', 'gercpay-button' ); ?></label>
        <input type="text" name="pay_button_text" id="pay_button_text" class="gpb-input"
           value="<?php echo $settings['pay_button_text']; ?>">
        <div class="gpb-description" id="mode_description">
          <?php _e( 'Custom GercPay button text', 'gercpay-button' ); ?>
        </div>
      </div>
        <div class="gpb-input-group">
          <label for="order_prefix" class="gpb-label"><?php _e( 'Order prefix', 'gercpay-button' ); ?></label>
          <input type="text" name="order_prefix" id="order_prefix" class="gpb-input"
             value="<?php echo $settings['order_prefix']; ?>">
          <div class="gpb-description" id="merchant_id_description">
            <?php _e( 'Prefix for order', 'gercpay-button' ); ?>
          </div>
        </div>
      </div>
      <!-- GercPay button settings -->
      <div class="gpb-section" id="gpb_section_btn_settings">
        <h3 class="hndle">
          <label for="title"><?php _e('GercPay button style', 'gercpay-button') ?></label>
        </h3>
        <div class="gpb-input-group">
          <label for="btn_shape" class="gpb-label"><?php _e( 'Button shape', 'gercpay-button' ); ?></label>
          <select type="text" name="btn_shape" id="btn_shape" class="gpb-input">
              <?php echo $this->gpb_get_select_options( self::gpb_get_btn_shape_fields(), $settings['btn_shape'] ?? 'round' ); ?>
          </select>
          <div class="gpb-description" id="btn_shape_description">
              <?php _e( 'Select button shape', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="btn_height" class="gpb-label"><?php _e( 'Button height', 'gercpay-button' ); ?></label>
          <select type="text" name="btn_height" id="btn_height" class="gpb-input">
              <?php echo $this->gpb_get_select_options( self::gpb_get_btn_height_fields(), $settings['btn_height'] ?? 'medium'); ?>
          </select>
          <div class="gpb-description" id="btn_height_description">
              <?php _e( 'Select button height', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="btn_width" class="gpb-label"><?php _e( 'Button width', 'gercpay-button' ); ?></label>
          <input type="number" placeholder="Auto" id="btn_width" class="gpb-input" name="btn_width"
                 value="<?php echo $settings['btn_width'] ?? '160'; ?>" size="10" step="1" min="160">
          <div class="gpb-description" id="btn_width_description">
              <?php _e( 'Button width in pixels. Minimum width is 160px. Leave it blank for auto width.', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="btn_color" class="gpb-label"><?php _e( 'Button color', 'gercpay-button' ); ?></label>
          <select type="text" name="btn_color" id="btn_color" class="gpb-input">
            <?php echo $this->gpb_get_select_options( self::gpb_get_btn_color_fields(), $settings['btn_color'] ?? 'white' ); ?>
          </select>
          <div class="gpb-description" id="btn_color_description">
              <?php _e( 'Select button color', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="btn_border" class="gpb-label"><?php _e( 'Button border', 'gercpay-button' ); ?></label>
          <select type="text" name="btn_border" id="btn_border" class="gpb-input">
              <?php echo $this->gpb_get_select_options( self::gpb_get_btn_border_fields(), $settings['btn_border'] ?? 'bold'); ?>
          </select>
          <div class="gpb-description" id="btn_border_description">
              <?php _e( 'Select button border', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="btn_inverse" class="gpb-label"><?php _e( 'Image type', 'gercpay-button' ); ?></label>
          <select type="text" name="btn_inverse" id="btn_inverse" class="gpb-input">
              <?php echo $this->gpb_get_select_options( self::gpb_get_btn_inverse_fields(), $settings['btn_inverse'] ?? 'normal'); ?>
          </select>
          <div class="gpb-description" id="btn_inverse_description">
              <?php _e( 'Select button image type', 'gercpay-button' ); ?>
          </div>
        </div>
        <div class="gpb-input-group">
          <label for="btn_preview" class="gpb-label"><?php _e( 'Button preview', 'gercpay-button' ); ?></label>
          <a href="" onclick="return false;" id="btn_preview" <?php echo $styles; ?>></a>
        </div>
        <div class="gpb-input-group">
          <label for="btn_preview_text" class="gpb-label"><?php _e( 'Button with additional attributes', 'gercpay-button' ); ?></label>
          <a href="" onclick="return false;" id="btn_preview_text" <?php echo $styles_text; ?>><?php echo $attributes['label']?></a>
        </div>
      </div>
      <!-- /GercPay button settings -->
		<?php submit_button( __( 'Save Changes' ), 'primary', 'Save' ); ?>
	  <input type='hidden' name='update'>
		<?php wp_nonce_field( 'gpb_form_post' ); ?>
	  </form>

	  </td>
	  </tr>
	  </table>
		<?php
		// End settings page and required permissions.
	}

	/**
	 * Generate popup checkout form.
	 *
	 * @return void
	 */
	public function gpb_checkout_form() {

		$settings = self::gpb_get_settings();
		?>
	<div id="gpb_popup" class="gpb-popup">
	  <div class="gpb-popup-body">
      <div class="gpb-popup-content">
        <a href="" class="gpb-popup-close" id="gpb-popup-close"><span>×</span></a>
        <div class="gpb-popup-title"> <?php _e( 'User info', 'gercpay-button' ); ?></div>
        <form action="" id="gpb_checkout_form" class="gpb-checkout-form">
          <?php if ( $settings['mode'] !== self::GPB_MODE_NONE ) : ?>
          <div class="gpb-popup-input-group">
            <label for="gpb_client_name" class="gpb-popup-label"><?php _e( 'Name', 'gercpay-button' ); ?></label>
            <input type="text" name="gpb_client_name" id="gpb_client_name" class="gpb-popup-input js-gpb-client-name" value="">
            <div class="gpb-popup-description" id="gpb_client_name_description">
              <?php _e( 'Enter your name', 'gercpay-button' ); ?>
            </div>
            <div class="js-gpb-error-name"></div>
          </div>
          <?php endif; ?>
          <?php if ( $settings['mode'] === self::GPB_MODE_PHONE || $settings['mode'] === self::GPB_MODE_PHONE_EMAIL ) : ?>
          <div class="gpb-popup-input-group">
            <label for="gpb_phone" class="gpb-popup-label"><?php _e( 'Phone', 'gercpay-button' ); ?></label>
            <input type="text" name="gpb_phone" id="gpb_phone" class="gpb-popup-input js-gpb-client-phone" value="">
            <div class="gpb-popup-description" id="gpb_client_first_name_description">
              <?php _e( 'Your contact phone', 'gercpay-button' ); ?>
            </div>
            <div class="js-gpb-error-phone"></div>
          </div>
          <?php endif; ?>
          <?php if ( $settings['mode'] === self::GPB_MODE_EMAIL || $settings['mode'] === self::GPB_MODE_PHONE_EMAIL ) : ?>
          <div class="gpb-popup-input-group">
            <label for="gpb_email" class="gpb-popup-label"><?php _e( 'Email', 'gercpay-button' ); ?></label>
            <input type="text" name="gpb_email" id="gpb_email" class="gpb-popup-input js-gpb-client-email" value="">
            <div class="gpb-popup-description" id="gpb_client_email_description">
              <?php _e( 'Your email', 'gercpay-button' ); ?>
            </div>
            <div class="js-gpb-error-email"></div>
          </div>
          <?php endif; ?>
          <div class="gpb-popup-input-group js-gpb-product-price-wrapper gpb-popup-field-hidden">
            <label for="gpb_product_price" class="gpb-popup-label"><?php _e( 'Amount', 'gercpay-button' ); ?></label>
            <div class="gpb-form-row">
              <input type="text" name="gpb_product_price" id="gpb_product_price" class="gpb-popup-input gpb-product-price js-gpb-product-price" value="">
              <select type="text" name="gpb_product_currency" id="gpb_product_currency" class="gpb-popup-input gpb-popup-select">
                  <?php echo $this->gpb_get_select_options_popup( self::gpb_get_currencies_popup(), $settings['currency'] ); ?>
              </select>
            </div>
            <div class="gpb-popup-description" id="gpb_product_price_description">
                <?php _e( 'Enter your prefer amount', 'gercpay-button' ); ?>
            </div>
            <div class="js-gpb-error-product-price"></div>
          </div>
          <input type="hidden" class="js-gpb-product-name" name="gpb_product_name" value="">
          <input type="hidden" name="action" value="popup_handler">
          <?php wp_nonce_field( 'popup_handler' ); ?>
          <div class="gpb-popup-footer">
            <button type="submit" class="gpb-popup-submit" id="gpb_popup_submit">
              <img src="<?php echo plugin_dir_url( __FILE__ ) . 'assets/img/logo.svg'; ?>" alt="GercPay">
              <span><?php $settings['pay_button_text'] ? esc_html_e( $settings['pay_button_text'] ) : _e( 'Pay Order', 'gercpay-button' ); ?></span>
            </button>
          </div>
        </form>
	    </div>
	  </div>
	</div>
		<?php
	}

	/**
	 * Update plugin options.
	 *
	 * @return bool
	 */
	public function gpb_update_options() {
		// Check nonce for security.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'gpb_form_post' ) ) {
			echo esc_attr__( 'Nonce verification failed', 'gercpay-button' );
			exit;
		}

		$settings = array();

		foreach ( $this->fillable as $field ) {
      if ( isset( $_POST[ $field ] ) && is_array( $_POST[ $field ] ) ) {
          $settings[ $field ] = array();
        foreach ( $_POST[ $field ] as $subkey => $subfield ) {
          $settings[ $field ][ $subkey ] = trim( sanitize_text_field( wp_unslash( $subfield ) ) );
        }
      } else if ( isset( $_POST[ $field ] ) && ! empty( trim( $_POST[ $field ] ) ) ) {
				$settings[ $field ] = trim( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

    if ( is_array( $settings['currency_popup'] ) !== true
        || count( $settings['currency_popup'] ) === 0
        || array_key_exists( $settings['currency'], $settings['currency_popup'] ) !== true
    ) {
        $message = __( 'The default currency should be available in the popup', 'gercpay-button' );
        add_action( 'admin_notices', array( $this, 'gpb_admin_notice__error' ) );
        do_action('admin_notices', $message );

        return false;
    }

		update_option( 'gpb_settings', $settings );

    return true;
	}

	/**
	 * Generates GercPay Button markup from the shortcode on the public site.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function gpb_make_button_from_shortcode( $attributes ) {

		// Get shortcode user fields.
    $attributes = shortcode_atts(
			array(
				'name'  => 'Example Name',
				'price' => '0.00',
				'size'  => '',
				'align' => '',
        'label' => '',
        'label_color' => '',
        'label_fontsize' => '',
			),
        $attributes
		);

    // Sanitize attributes.
    $atts = [];
    foreach ($attributes as $key => $attribute) {
        $atts[$key] = trim( sanitize_text_field( wp_unslash( $attribute ) ) );
    }

    $atr_name  = $atts['name'];
    $atr_price = $atts['price'];
    // Additional attributes.
    $atr_label          = $atts['label'];
    $atr_label_color    = $atts['label_color'];
    $atr_label_fontsize = $atts['label_fontsize'];

		$styles = $this->gpb_get_button_styles( [
        'label' => $atr_label,
        'label_color' => $atr_label_color,
        'label_fontsize' => $atr_label_fontsize
    ] );

		$output = '<div>';
		$output .= "<a href='' $styles data-type='gpb_submit' data-name='{$atr_name}' data-price='{$atr_price}'>";
    $output .= ($atr_label !== '') ? "{$atr_label}</a>" : "</a>";
		$output .= '</div>';

		return $output;
	}

	/**
	 * Make payment form data.
	 *
	 * @return void
	 */
	public function gpb_payment_handler() {
		global $wp;

		$validation = $this->gpb_validate_checkout_form( $_POST );
		if ( ! $validation['result'] ) {
			echo wp_json_encode( $validation['errors'] );
			die();
		}

		// Get settings page values.
		$options  = get_option( 'gpb_settings' );
		$settings = array();
		foreach ( $options as $k => $v ) {
			if ( 'align' === $k ) {
				$settings[ $k ] = strtolower( $v );
			} else {
				$settings[ $k ] = $v;
			}
		}

		$output = '';
		if ( empty( $settings['merchant_id'] ) ) {
			$output .= __( 'Please enter your GercPay Merchant ID on the settings page.', 'gercpay-button' );
		}

		if ( ! empty( $settings['order_prefix'] ) ) {
			$order_id = $settings['order_prefix'] . '_' . self::generate_random_string();
		} else {
			$order_id = 'gpb_' . self::generate_random_string();
		}

		$amount = sanitize_text_field( wp_unslash( $_POST['gpb_product_price'] ) );

    if ( isset( $_POST['gpb_product_currency'] ) ) {
      $currency = sanitize_text_field( wp_unslash( $_POST['gpb_product_currency'] ) );
    } else {
      $currency = $settings['currency'];
    }

		$product_name = sanitize_text_field( wp_unslash( $_POST['gpb_product_name'] ) );

		$gpb_base_url = home_url( $wp->request );
		$approve_url  = add_query_arg( 'gercpay_result', 'success', $gpb_base_url );
		$decline_url  = add_query_arg( 'gercpay_result', 'fail', $gpb_base_url );
		$cancel_url   = add_query_arg( 'gercpay_result', 'cancel', $gpb_base_url );
		$callback_url = '';

		list( $client_first_name, $client_last_name ) = explode(
			' ',
			sanitize_text_field( wp_unslash( trim( $_POST['gpb_client_name'] ) ) )
		);

		$phone = isset( $_POST['gpb_phone'] ) ? self::sanitize_phone( $_POST['gpb_phone'] ) : '';
		$email = isset( $_POST['gpb_email'] ) ? sanitize_email( $_POST['gpb_email'] ) : '';

		$client_site_url = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );

		$description = __( 'Payment by card on the site', 'gercpay-button' )
		  . rtrim( " $client_site_url, $client_first_name $client_last_name, $phone", ', .' );
		$gercpay  = new GercPayApi( $settings['secret_key'] );

		$request = array(
			'operation'         => 'Purchase',
			'merchant_id'       => $settings['merchant_id'],
			'amount'            => $amount,
			'order_id'          => $order_id,
			'currency_iso'      => $currency,
			'description'       => $description,
			'add_params'        => array( 'product_name' => $product_name ),
			'approve_url'       => $approve_url,
			'decline_url'       => $decline_url,
			'cancel_url'        => $cancel_url,
			'callback_url'      => $callback_url,
			'language'          => $settings['language'],
			// Statistics.
			'client_last_name'  => $client_last_name,
			'client_first_name' => $client_first_name,
			'phone'             => $phone,
			'email'             => $email,
		);
		$request['signature'] = $gercpay->getRequestSignature( $request );

		$url = GercPayApi::getApiUrl();

		$output .= "<form action={$url} method='post' id='gpb_payment_form'>";
		foreach ( $request as $key => $value ) {
			$output .= $this->print_input( $key, $value );
		}
    $settings = self::gpb_get_settings();
    $pay_button_text = $settings['pay_button_text'] ?? __( 'Pay', 'gercpay-button' );

		$output .= "<input type='submit' value='$pay_button_text' alt='Make your payments with GercPay'>";
		$output .= '</form>';

		echo $output;
		die();
	}

	/**
	 * Link admin styles.
	 */
	public function gpb_link_admin_styles() {
		wp_enqueue_style( 'gpb-admin-styles', plugin_dir_url( __FILE__ ) . 'assets/css/gercpay.css', array(), self::GPB_PLUGIN_VERSION );
	}

  /**
   * Link admin scripts.
   *
   * @return void
   */
  public function gpb_link_admin_scripts() {
      wp_enqueue_script( 'gpb-admin-scripts', plugin_dir_url( __FILE__ ) . 'assets/js/gercpay-admin.js', array(), self::GPB_PLUGIN_VERSION, true );
  }

	/**
	 * Link front scripts and styles.
	 */
	public function gpb_link_front_scripts() {
		// Register script.
		wp_register_script(
			'gpb-script',
			plugin_dir_url( __FILE__ ) . 'assets/js/gercpay.js',
			array( 'wp-i18n' ),
			self::GPB_PLUGIN_VERSION,
			true
		);
		// Share PHP variable to JS code.
		wp_localize_script(
			'gpb-script',
			'gpb_ajax',
			array(
				'url' => admin_url( 'admin-ajax.php' ),
			)
		);
		// Link script.
		wp_enqueue_script( 'gpb-script' );
		// Translate script.
		wp_set_script_translations(
			'gpb-script',
			'gercpay-button',
			plugin_dir_path( __FILE__ ) . 'lang'
		);
		wp_enqueue_style(
			'gpb-styles',
			plugin_dir_url( __FILE__ ) . 'assets/css/gercpay.css',
			array(),
			self::GPB_PLUGIN_VERSION
		);
	}

	/**
	 * Register GercPay block in Gutenberg editor.
	 *
	 * @return void
	 */
	public function gpb_register_gutenberg_block() {

		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			'gpb-block',
			plugins_url( 'assets/js/blocks/gpb.block.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n' ),
			self::GPB_PLUGIN_VERSION,
			true
		);

		wp_register_style(
			'gpb-block',
			plugins_url( 'assets/css/gercpay.css', __FILE__ ),
			array(),
			self::GPB_PLUGIN_VERSION,
			true
		);

		register_block_type(
			'gercpay-button/gpb-block',
			array(
				'editor_script' => 'gpb-block',
			)
		);
	}

	/**
	 * Displays GercPay transaction result after redirect from payment page.
	 *
	 * @return void
	 */
	public function gpb_show_payment_result_message() {
		switch ( strtolower( $_GET['gercpay_result'] ) ) {
			case 'success':
				$message = '<div class="gpb-result-success">' . esc_html__( 'Congratulations! Your payment has been approved', 'gercpay-button' ) . '</div>';
				break;
			case 'fail':
				$message = '<div class="gpb-result-fail">' . esc_html__( 'Sorry, payment failed', 'gercpay-button' ) . '</div>';
				break;
			case 'cancel':
				$message = '<div class="gpb-result-cancel">' . esc_html__( 'Payment canceled', 'gercpay-button' ) . '</div>';
				break;
			default:
				$message = '';
		}

		echo $message;
	}

	/**
	 * Generate random string for Order ID.
	 *
	 * @param  int $length Random string length.
	 * @return string
	 */
	protected static function generate_random_string( $length = 10 ) {
		$characters        = 'abcdefghijklmnopqrstuvwxyz';
		$characters_length = strlen( $characters );
		$random_string     = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[ wp_rand( 0, $characters_length - 1 ) ];
		}
		return time() . '_' . $random_string;
	}

	/**
	 * Prints inputs in form.
	 *
	 * @param string       $name Attribute name.
	 * @param array|string $val Attribute value.
	 * @return string
	 */
	protected function print_input( $name, $val ) {
		$str = '';
		if ( ! is_array( $val ) ) {
			return "<input type='hidden' name='" . $name . "' value='" . htmlspecialchars( $val ) . "'>" . PHP_EOL;
		}
		foreach ( $val as $k => $v ) {
			$str .= $this->print_input( $name . '[' . $k . ']', $v );
		}
		return $str;
	}

	/**
	 * List allowed currencies.
	 *
	 * @return array
	 */
	protected static function gpb_get_currencies() {
		return array(
			'UAH' => array(
          'label' => __( 'Ukrainian hryvnia', 'gercpay-button' ),
          'alias' => 'ГРН'
      ),
			'USD' => array(
          'label' => __( 'U.S. Dollar', 'gercpay-button' ),
          'alias' => 'USD'
      ),
			'EUR' => array(
          'label' => __( 'Euro', 'gercpay-button' ),
          'alias' => 'EUR'
      ),
		);
	}

  /**
   * Returns currencies allowed in popup window.
   *
   * @return array|array[]
   */
  protected static function gpb_get_currencies_popup() {
    $settings = self::gpb_get_settings();

    return array_intersect_key( self::gpb_get_currencies(), $settings['currency_popup'] );
  }

	/**
	 * List of allowed payment page languages.
	 *
	 * @return array
	 */
	protected static function gpb_get_languages() {
		return array(
			'ua' => __( 'UA', 'gercpay-button' ),
			'ru' => __( 'RU', 'gercpay-button' ),
			'en' => __( 'EN', 'gercpay-button' ),
		);
	}

	/**
	 * List of fields required to be entered by the buyer.
	 *
	 * @return array
	 */
	protected static function gpb_get_required_fields() {
		return array(
			self::GPB_MODE_NONE        => __( 'Do not require', 'gercpay-button' ),
			self::GPB_MODE_PHONE       => __( 'Name + Phone', 'gercpay-button' ),
			self::GPB_MODE_EMAIL       => __( 'Name + Email', 'gercpay-button' ),
			self::GPB_MODE_PHONE_EMAIL => __( 'Name + Phone + Email', 'gercpay-button' ),
		);
	}

  /**
   * GercPay Button shape values.
   *
   * @return array
   */
  protected static function gpb_get_btn_shape_fields() {
    return array(
        'rect'  => __( 'Rectangular', 'gercpay-button' ),
        'round' => __( 'Rounded', 'gercpay-button' ),
        'pill'  => __( 'Pill', 'gercpay-button' ),
    );
  }

   /**
    * GercPay Button height values.
    *
    * @return array
    */
  protected static function gpb_get_btn_height_fields() {
    return array(
      'small'  => __( 'Small', 'gercpay-button' ),
      'medium' => __( 'Medium', 'gercpay-button' ),
      'large'  => __( 'Large', 'gercpay-button' ),
      'xlarge' => __( 'Extra large', 'gercpay-button' ),
    );
  }

    /**
     * GercPay Button color values.
     *
     * @return array
     */
    protected static function gpb_get_btn_color_fields() {
        return array(
          'gold'   => array(
              'label' => __( 'Gold', 'gercpay-button' ),
              'class' => 'gpb-btn-color-gold',
              'code'  => '#FFC439'
          ),
          'blue'   => array(
              'label' => __( 'Blue', 'gercpay-button' ),
              'class' => 'gpb-btn-color-blue gpb-btn-text-color-white',
              'code'  => '#0170BA'
          ),
          'silver' => array(
              'label' => __( 'Silver', 'gercpay-button' ),
              'class' => 'gpb-btn-color-silver',
              'code'  => '#EEEEEE'
          ),
          'white'  => array(
              'label' => __( 'White', 'gercpay-button' ),
              'class' => 'gpb-btn-color-white',
              'code'  => '#FFFFFF'
          ),
          'black'  => array(
              'label' => __( 'Black', 'gercpay-button' ),
              'class' => 'gpb-btn-color-black gpb-btn-text-color-white',
              'code'  => '#2C2E2F'
          ),
        );
    }

    /**
     * GercPay Button border values.
     *
     * @return array
     */
    protected static function gpb_get_btn_border_fields() {
        return array(
          'none'    => __( 'None', 'gercpay-button' ),
          'regular' => __( 'Regular', 'gercpay-button' ),
          'bold'    => __( 'Bold', 'gercpay-button' ),
        );
    }

    /**
     * GercPay Button image type values.
     *
     * @return array
     */
    protected static function gpb_get_btn_inverse_fields() {
        return array(
            'normal' => __( 'Normal', 'gercpay-button' ),
            'inverse' => __( 'Inverse', 'gercpay-button' ),
        );
    }

	/**
	 * Returns list of select options.
	 *
	 * @param array  $data     Input options array.
	 * @param string $selected Current selected value.
	 *
	 * @return string
	 */
	protected function gpb_get_select_options( $data, $selected ) {
		$options = '';
		foreach ( $data as $key => $value ) {
      if (is_array($value)) {
          $options .= "<option class='{$value["class"]}' value='{$key}'" . ' ' . ( $key === $selected ? "selected='selected'" : '' ) . ">{$value['label']}</option>";
      } else {
          $options .= "<option value='{$key}'" . ' ' . ( $key === $selected ? "selected='selected'" : '' ) . ">{$value}</option>";
      }
		}

		return $options;
	}

    /**
     * Returns list of select options.
     *
     * @param array  $data     Input options array.
     * @param string $selected Current selected value.
     *
     * @return string
     */
    protected function gpb_get_select_options_popup( $data, $selected ) {
        $options = '';
        foreach ( $data as $key => $value ) {
            if (is_array($value)) {
                $options .= "<option class='{$value["class"]}' value='{$key}'" . ' ' . ( $key === $selected ? "selected='selected'" : '' ) . ">{$value['alias']}</option>";
            }
        }

        return $options;
    }

	/**
	 * Translate block in Gutenberg Editor.
	 *
	 * @return void
	 */
	public function gpb_set_script_translations() {
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'gpb-block', 'gercpay-button', plugin_dir_path( __FILE__ ) . 'lang' );
		}
	}

	/**
	 * Checkout form validation.
	 *
	 * @param array $post_data $_POST data.
	 *
	 * @return array
	 */
	protected function gpb_validate_checkout_form( $post_data ) {
		$result = array(
			'result' => false,
			'errors' => array(),
		);

		$checkout_params_keys = $this->gpb_get_checkout_params_keys();

		$isHasAllValues = ! array_diff_key( $checkout_params_keys, $post_data );
		if ( ! $isHasAllValues && ! isset( $post_data['is_single_field'] ) ) {
			$result['errors'][] = __( 'Error: Not enough input parameters.', 'gercpay-button' );
			return $result;
		}

		// Check nonce code.
		if ( ! wp_verify_nonce( $post_data['_wpnonce'], 'popup_handler' ) ) {
			$result['errors']['nonce'] = __( 'Error: Request failed security check', 'gercpay-button' );
		}

		// Check client_name.
		if ( isset( $checkout_params_keys['gpb_client_name'] ) && empty( trim( $post_data['gpb_client_name'] ) ) ) {
			$result['errors']['name'] = __( 'Invalid name', 'gercpay-button' );
		}

		// Check phone.
		if ( isset( $checkout_params_keys['gpb_phone'] ) ) {
			$phone = self::sanitize_phone( $post_data['gpb_phone'] );
			if ( empty( $phone ) || mb_strlen( $phone ) < 10 ) {
				$result['errors']['phone'] = __( 'Invalid phone number', 'gercpay-button' );
			}
		}

		// Check email.
		if ( isset( $checkout_params_keys['gpb_email'] ) ) {
			$email = trim( $post_data['gpb_email'] );
			if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				$result['errors']['email'] = __( 'Invalid email', 'gercpay-button' );
			}
		}

    // Check amount value.
    if ( isset( $checkout_params_keys['gpb_product_price'] )) {
        $amount = trim( $post_data['gpb_product_price'] );
        if ( !is_numeric($amount) || $amount <= 0 ) {
            $result['errors']['product-price'] = __( 'Invalid amount', 'gercpay-button' );
        }
    }

    // Check currency.
    if ( isset( $checkout_params_keys['gpb_product_currency'] )) {
        $currency = trim( $post_data['gpb_product_currency'] );
        if ( !array_key_exists( $currency, self::gpb_get_currencies_popup() ) ) {
            $result['errors']['product-price'] = __( 'Invalid currency', 'gercpay-button' );
        }
    }

		if ( empty( $result['errors'] ) ) {
			$result['result'] = true;
		}

		return $result;
	}

	/**
	 * Remove all non-numerical symbol from phone.
	 *
	 * @param string $phone Client phone.
	 *
	 * @return array|string|string[]|null
	 */
	protected static function sanitize_phone( $phone ) {
		return preg_replace( '/\D+/', '', $phone );
	}

	/**
	 * Return plugin settings.
	 *
	 * @return array
	 */
	protected static function gpb_get_settings() {
		$options  = get_option( 'gpb_settings' );
		$settings = array();
		foreach ( $options as $k => $v ) {
			$settings[ $k ] = $v;
		}

		return $settings;
	}

	/**
	 * Get required checkout fields.
	 *
	 * @return string[]
	 */
	protected function gpb_get_checkout_params_keys() {
		$settings = self::gpb_get_settings();

		$params = $settings['mode'] ? $this->checkout_params[ $settings['mode'] ] : $this->checkout_params[ self::GPB_MODE_PHONE ];

		return array_flip( $params );
	}

  /**
   * Returns GercPay Button styles and classes.
   * @param array $attributes
   *
   * @return string
   */
  protected function gpb_get_button_styles( array $attributes = [] ) {
    $styles = '';
    $settings = self::gpb_get_settings();
    $img = ($settings['btn_inverse'] === 'inverse') ?
          plugin_dir_url( __FILE__ ) . 'assets/img/gercpay-inverse.svg':
          plugin_dir_url( __FILE__ ) . 'assets/img/gercpay.svg';

    $btn_shape = $settings['btn_shape'] ? 'gpb-btn-shape-' . $settings['btn_shape'] : 'gpb-btn-shape-round';
    $btn_height = $settings['btn_height'] ? 'gpb-btn-height-' . $settings['btn_height'] : 'gpb-btn-height-medium';
    $btn_width = $settings['btn_width'] ? $settings['btn_width'] . 'px' : '160px';
    $all_btn_colors = self::gpb_get_btn_color_fields();
    $btn_color = ($settings['btn_color'] && isset($all_btn_colors[$settings['btn_color']]))
      ? $all_btn_colors[$settings['btn_color']]['code']
      : '#FFFFFF';
    $btn_border = $settings['btn_border'] ? 'gpb-btn-border-' . $settings['btn_border'] : 'gpb-btn-border-bold';

    if ( isset( $attributes['label'] ) && $attributes['label'] !== '' ) {
        $styles .= "class='gpb-button-image $btn_shape $btn_height $btn_color $btn_border'
     style='background: $btn_color no-repeat center center content-box border-box; width: $btn_width; ";
    } else {
        $styles .= "class='gpb-button-image $btn_shape $btn_height $btn_color $btn_border'
     style='background:url($img) $btn_color no-repeat 50% 50%/auto 80%; width: $btn_width";
    }

    if ( isset( $attributes['label_color'] ) && $attributes['label_color'] !== '' ) {
      $styles .= "color: " . $attributes['label_color'] . "; ";
    }

    if ( isset( $attributes['label_fontsize'] ) && $attributes['label_fontsize'] !== '' ) {
      $styles .= "font-size: " . $attributes['label_fontsize'] . ";";
    }

    $styles .= "'";

    return $styles;
  }

    /**
     * Shows admin error message.
     *
     * @param $message
     * @return void
     */
    public function gpb_admin_notice__error( $message ) {
        $class = 'notice notice-error';
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
}
