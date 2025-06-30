<?php
if (!defined('ABSPATH')) {
	exit;
}

class WC_Order_Risk_Admin {

	private static $instance = null;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter('manage_edit-shop_order_columns', [$this, 'add_risk_column']);
		add_action('manage_shop_order_posts_custom_column', [$this, 'render_risk_column'], 10, 2);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('woocommerce_process_shop_order_meta', [$this, 'save_order_risk'], 10, 2);
	}

	public function add_risk_column($columns) {
		$new_columns = [];
		foreach ($columns as $key => $title) {
			$new_columns[$key] = $title;
			if ($key === 'order_status') {
				$new_columns['order_risk'] = __('Risk', 'woocommerce-order-risk-analyzer');
			}
		}
		return $new_columns;
	}

	public function render_risk_column($column, $post_id) {
		if ($column !== 'order_risk') {
			return;
		}

		$calc = WC_Order_Risk_Calculator::get_instance();
		$risk_data = $calc->calculate_risk($post_id);
		$score = intval($risk_data['score']);
		$positives = $risk_data['positives'] ?? [];
		$negatives = $risk_data['negatives'] ?? [];

		if ($score >= 70) {
			$color_class = 'text-danger';
			$label = __('High Risk', 'woocommerce-order-risk-analyzer');
		} elseif ($score >= 40) {
			$color_class = 'text-warning';
			$label = __('Medium Risk', 'woocommerce-order-risk-analyzer');
		} else {
			$color_class = 'text-success';
			$label = __('Low Risk', 'woocommerce-order-risk-analyzer');
		}

		$details_html = '<div class=\'risk-explanation bg-white p-2 rounded shadow-sm text-start\' style=\'min-width:250px;\'>';

		if (!empty($negatives)) {
			$details_html .= '<strong class="text-danger d-block mb-1">' . esc_html__('Negative Factors', 'woocommerce-order-risk-analyzer') . '</strong>';
			foreach ($negatives as $item) {
				$details_html .= '<div class="text-danger small">➤ ' . esc_html($item) . '</div>';
			}
		}

		if (!empty($positives)) {
			$details_html .= '<strong class="text-success d-block mt-2 mb-1">' . esc_html__('Positive Factors', 'woocommerce-order-risk-analyzer') . '</strong>';
			foreach ($positives as $item) {
				$details_html .= '<div class="text-success small">✔ ' . esc_html($item) . '</div>';
			}
		}

		if (empty($negatives) && empty($positives)) {
			$details_html .= '<div class="text-muted small">' . esc_html__('No data to evaluate risk.', 'woocommerce-order-risk-analyzer') . '</div>';
		}

		$details_html .= '</div>';

		echo '<span class="badge ' . esc_attr($color_class) . ' fw-bold" ' .
			'tabindex="0" role="button" data-bs-toggle="popover" data-bs-html="true" ' .
			'data-bs-trigger="focus" title="' . esc_attr($label . " ({$score}%)") . '" ' .
			'data-bs-content="' . esc_attr(wp_kses_post($details_html)) . '">' .
			esc_html($score) . '%</span>';
	}

	public function enqueue_admin_assets($hook) {
		// Pages where you want to enqueue your assets
		$is_orders_list = ($hook === 'edit.php' && get_post_type() === 'shop_order');
		$is_settings_page = ($hook === 'woocommerce_page_wc_order_risk_settings');

		if ($is_orders_list || $is_settings_page) {
			// Bootstrap CSS & JS
			wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', [], '5.3.0');
			wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', [], '5.3.0', true);
		}

		if ($is_settings_page) {
			// Bootstrap Icons only on settings page
			wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css', [], '1.10.5');
		}

		if ($is_orders_list || $is_settings_page) {
			// CSS JS
			wp_enqueue_style('woocommerce-order-risk-admin-style', plugin_dir_url(__FILE__) . 'css/admin-style.css', [], '1.0');
			wp_enqueue_script('woocommerce-order-risk-admin-script', plugin_dir_url(__FILE__) . 'js/admin-script.js', ['bootstrap-js'], '1.0', true);
			// Localize for translation
			wp_localize_script('woocommerce-order-risk-admin-script','wcOrderRiskData',['selectPlaceholder' => __( 'Select high-risk payment methods', 'woocommerce-order-risk-analyzer' ),]);
		}
	}

	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__('Order Risk Settings', 'woocommerce-order-risk-analyzer'),
			__('Order Risk Settings', 'woocommerce-order-risk-analyzer'),
			'manage_woocommerce',
			'wc_order_risk_settings',
			[$this, 'render_settings_page']
		);
	}

	public function register_settings() {
		register_setting('wc_order_risk_settings_group', 'wc_order_risk_settings', [
			'sanitize_callback' => [$this, 'sanitize_settings'],
			'default' => $this->get_default_settings()
		]);
	}

	public function sanitize_settings($input) {
		if (!is_array($input)) return [];

		$defaults = $this->get_default_settings();

		$output = [
			'weights' => [],
			'thresholds' => [],
			'high_risk_payment_methods' => [],
			'cancelled_orders_max_multiplier' => $defaults['cancelled_orders_max_multiplier'],
			'repeat_orders_max_reduction' => $defaults['repeat_orders_max_reduction'],
		];

		$output['cancelled_orders_max_multiplier'] = isset($input['cancelled_orders_max_multiplier']) ? intval($input['cancelled_orders_max_multiplier']) : $defaults['cancelled_orders_max_multiplier'];

		$output['repeat_orders_max_reduction'] = isset($input['repeat_orders_max_reduction']) ? intval($input['repeat_orders_max_reduction']) : $defaults['repeat_orders_max_reduction'];

		foreach (['repeat_orders', 'cancelled_orders', 'guest_orders', 'payment_method'] as $key) {
			$output['weights'][$key] = isset($input['weights'][$key]) ? intval($input['weights'][$key]) : $defaults['weights'][$key];
		}

		if (!empty($input['high_risk_payment_methods']) && is_array($input['high_risk_payment_methods'])) {
			$output['high_risk_payment_methods'] = array_map('sanitize_text_field', $input['high_risk_payment_methods']);
		} else {
			$output['high_risk_payment_methods'] = $defaults['high_risk_payment_methods'];
		}

		foreach (['low', 'medium'] as $key) {
			$output['thresholds'][$key] = isset($input['thresholds'][$key]) ? intval($input['thresholds'][$key]) : $defaults['thresholds'][$key];
		}

		return $output;
	}

	public function get_default_settings() {
		return [
			'weights' => [
				'repeat_orders' => 5,
				'cancelled_orders' => 10,
				'guest_orders' => 10,
				'payment_method' => 20,
			],
			//'high_risk_payment_methods' => ['cod', 'cheque'],
			'thresholds' => [
				'low' => 40,
				'medium' => 70,
			],
			'cancelled_orders_max_multiplier' => 5,
			'repeat_orders_max_reduction' => 20,
		];
	}

	public function render_settings_page() {
		$settings = get_option('wc_order_risk_settings', $this->get_default_settings());

		// Check if settings were updated
		if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
?>
<div class="notice notice-success is-dismissible">
	<p><?php esc_html_e('Settings saved successfully.', 'woocommerce-order-risk-analyzer'); ?></p>
</div>
<?php
																					 }
?>
<div class="wrap">
	<h1 class="mb-4"><?php esc_html_e('Order Risk Analyzer Settings', 'woocommerce-order-risk-analyzer'); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields('wc_order_risk_settings_group');
		do_settings_sections('wc_order_risk_settings_group');
		?>

		<div class="accordion mt-4" id="wcOrderRiskAccordion">

			<!-- Risk Factors -->
			<div class="accordion-item">
				<h2 class="accordion-header" id="headingRisk">
					<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRisk" aria-expanded="true" aria-controls="collapseRisk">
						<i class="bi bi-shield-exclamation text-danger me-2"></i><?php esc_html_e('Risk Factors (Add Points)', 'woocommerce-order-risk-analyzer'); ?>
					</button>
				</h2>
				<div id="collapseRisk" class="accordion-collapse collapse show" data-bs-parent="#wcOrderRiskAccordion">
					<div class="accordion-body">
						<div class="mb-3">
							<label class="form-label" for="cancelled_orders_weight">
								<?php esc_html_e('Cancelled Orders Multiplier Point', 'woocommerce-order-risk-analyzer'); ?>
							</label>
							<input type="number" id="cancelled_orders_weight" name="wc_order_risk_settings[weights][cancelled_orders]" class="form-control" value="<?php echo esc_attr($settings['weights']['cancelled_orders']); ?>" min="1" max="100">
							<small class="text-muted">
								<?php
		$cancelled_cap = $settings['cancelled_orders_max_multiplier'] ?? 5;
		printf(
			esc_html__('This value × number of cancelled orders, capped at %d times.', 'woocommerce-order-risk-analyzer'),
			$cancelled_cap
		);
								?>
							</small>
						</div>

						<div class="mb-3">
							<label class="form-label" for="cancelled_orders_max_multiplier">
								<?php esc_html_e('Cancelled Orders Max Multiplier', 'woocommerce-order-risk-analyzer'); ?>
							</label>
							<input type="number" id="cancelled_orders_max_multiplier" name="wc_order_risk_settings[cancelled_orders_max_multiplier]" class="form-control" value="<?php echo esc_attr($settings['cancelled_orders_max_multiplier'] ?? 5); ?>" min="1" max="20">
							<small class="text-muted"><?php esc_html_e('Max times cancelled orders weight is multiplied in risk calculation.', 'woocommerce-order-risk-analyzer'); ?></small>
						</div>

						<div class="mb-3">
							<label class="form-label"><?php esc_html_e('Guest Checkout Point', 'woocommerce-order-risk-analyzer'); ?></label>
							<input type="number" name="wc_order_risk_settings[weights][guest_orders]" class="form-control" value="<?php echo esc_attr($settings['weights']['guest_orders']); ?>" min="1" max="100">
						</div>
						<div class="mb-3">
							<label class="form-label" for="high_risk_payment_methods_select">
								<?php esc_html_e('High-Risk Payment Methods', 'woocommerce-order-risk-analyzer'); ?>
							</label>
							<select class="form-select" id="high_risk_payment_methods_select" name="wc_order_risk_settings[high_risk_payment_methods][]" multiple data-coreui-search="true">
								<?php
		$available_methods = WC()->payment_gateways()->payment_gateways();
		$selected_methods = $settings['high_risk_payment_methods'] ?? [];

		foreach ($available_methods as $gateway) {
			$selected = in_array($gateway->id, $selected_methods) ? 'selected' : '';
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr($gateway->id),
				$selected,
				esc_html($gateway->get_title())
			);
		}
								?>
							</select>
							<small class="text-muted">
								<?php esc_html_e('Select one or more payment methods considered high-risk. These will increase the risk score.', 'woocommerce-order-risk-analyzer'); ?>
							</small>
						</div>
					</div>
				</div>
			</div>

			<!-- Trust Factors -->
			<div class="accordion-item">
				<h2 class="accordion-header" id="headingTrust">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTrust" aria-expanded="false" aria-controls="collapseTrust">
						<i class="bi bi-hand-thumbs-up text-success me-2"></i><?php esc_html_e('Trust Factors (Subtract Points)', 'woocommerce-order-risk-analyzer'); ?>
					</button>
				</h2>
				<div id="collapseTrust" class="accordion-collapse collapse" data-bs-parent="#wcOrderRiskAccordion">
					<div class="accordion-body">
						<div class="mb-3">
							<label class="form-label" for="repeat_orders_base_point">
								<?php esc_html_e('Repeat Customer Base Point', 'woocommerce-order-risk-analyzer'); ?>
							</label>
							<input type="number" id="repeat_orders_base_point" name="wc_order_risk_settings[weights][repeat_orders]" class="form-control" value="<?php echo esc_attr($settings['weights']['repeat_orders']); ?>" min="1" max="100">
							<small class="text-muted">
								<?php
		$cap = isset($settings['repeat_orders_max_reduction']) ? intval($settings['repeat_orders_max_reduction']) : 20;
		printf(
			esc_html__('This value × number of completed orders, capped at %d times.', 'woocommerce-order-risk-analyzer'),
			$cap
		);
								?>
							</small>
						</div>

						<div class="mb-3">
							<label class="form-label" for="repeat_orders_max_reduction">
								<?php esc_html_e('Repeat Orders Max Reduction (%)', 'woocommerce-order-risk-analyzer'); ?>
							</label>
							<input type="number" id="repeat_orders_max_reduction" name="wc_order_risk_settings[repeat_orders_max_reduction]" class="form-control" value="<?php echo esc_attr($settings['repeat_orders_max_reduction'] ?? 20); ?>" min="0" max="100">
							<small class="text-muted"><?php esc_html_e('Maximum total percentage risk reduction for repeat orders (cap).', 'woocommerce-order-risk-analyzer'); ?></small>
						</div>
					</div>
				</div>
			</div>

			<!-- General Settings -->
			<div class="accordion-item">
				<h2 class="accordion-header" id="headingGeneral">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneral" aria-expanded="false" aria-controls="collapseGeneral">
						<i class="bi bi-gear text-secondary me-2"></i><?php esc_html_e('General Settings', 'woocommerce-order-risk-analyzer'); ?>
					</button>
				</h2>
				<div id="collapseGeneral" class="accordion-collapse collapse" data-bs-parent="#wcOrderRiskAccordion">
					<div class="accordion-body">
						<div class="row">
							<div class="col-md-6 mb-3">
								<label class="form-label"><?php esc_html_e('Low Risk Threshold (%)', 'woocommerce-order-risk-analyzer'); ?></label>
								<input type="number" name="wc_order_risk_settings[thresholds][low]" class="form-control" value="<?php echo esc_attr($settings['thresholds']['low']); ?>" min="0" max="100">
							</div>
							<div class="col-md-6 mb-3">
								<label class="form-label"><?php esc_html_e('Medium Risk Threshold (%)', 'woocommerce-order-risk-analyzer'); ?></label>
								<input type="number" name="wc_order_risk_settings[thresholds][medium]" class="form-control" value="<?php echo esc_attr($settings['thresholds']['medium']); ?>" min="0" max="100">
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="mt-4 text-center">
			<button type="submit" class="button button-primary button-hero px-5 py-2">
				<i class="bi bi-save me-2"></i><?php esc_html_e('Save Settings', 'woocommerce-order-risk-analyzer'); ?>
			</button>
		</div>
	</form>
</div>
<?php
	}
	public function save_order_risk($post_id, $post) {
		$calc = WC_Order_Risk_Calculator::get_instance();
		$result = $calc->calculate_risk($post_id);
		update_post_meta($post_id, '_order_risk_score', $result['score']);
		update_post_meta($post_id, '_order_risk_reasons', maybe_serialize([
			'positives' => $result['positives'] ?? [],
			'negatives' => $result['negatives'] ?? [],
		]));
	}
}