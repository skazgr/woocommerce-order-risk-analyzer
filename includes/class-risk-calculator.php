<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Risk_Calculator {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

	public function calculate_risk($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) {
			return ['score' => 0, 'positives' => [], 'negatives' => []];
		}

		$settings = get_option('wc_order_risk_settings', $this->get_default_settings());
		$weights = $settings['weights'] ?? [];

		$score = 0;
		$positives = [];
		$negatives = [];

		$customer_email = $order->get_billing_email();
		$customer_id = $order->get_customer_id();
		$payment_method = $order->get_payment_method();

		// NEGATIVE FACTORS

		// Cancelled orders (scaled)
		$cancelled_orders = $this->count_cancelled_orders_by_email($customer_email);
		if ($cancelled_orders > 0) {
			$weight = $weights['cancelled_orders'] ?? 10;
			$max_multiplier = $settings['cancelled_orders_max_multiplier'] ?? 5; // use setting here
			$multiplier = min($cancelled_orders, $max_multiplier);
			$impact = $weight * $multiplier;
			$score += $impact;
			$negatives[] = sprintf(__('Cancelled orders (%d): +%d%% (capped at %d times)', 'woocommerce-order-risk-analyzer'), $cancelled_orders, $impact, $max_multiplier);
		}

		// Guest checkout
		if ((int)$customer_id === 0) {
			$weight = $weights['guest_orders'] ?? 10;
			$score += $weight;
			$negatives[] = sprintf(__('Guest checkout: +%d%%', 'woocommerce-order-risk-analyzer'), $weight);
		}

		// High risk payment method
		$high_risk_methods = $settings['high_risk_payment_methods'] ?? ['cod', 'cheque'];
		if (in_array($payment_method, $high_risk_methods)) {
			$weight = $weights['payment_method'] ?? 15;
			$score += $weight;

			$payment_gateways = WC()->payment_gateways->payment_gateways();
			$payment_name = isset($payment_gateways[$payment_method]) ? $payment_gateways[$payment_method]->get_title() : ucfirst($payment_method);

			$negatives[] = sprintf(__('High risk payment (%s): +%d%%', 'woocommerce-order-risk-analyzer'), $payment_name, $weight);
		}

		// POSITIVE FACTORS
		 // Repeat customer risk reduction with configurable cap
		$repeat_orders = $this->count_completed_orders_by_email($customer_email);
		if ($repeat_orders >= 1) {
			$base_weight = $weights['repeat_orders'] ?? 5; // base weight per repeat order
			$max_positive = $settings['repeat_orders_max_reduction'] ?? 20; // max cap from settings

			$repeat_score = $base_weight * $repeat_orders;
			if ($repeat_score > $max_positive) {
				$repeat_score = $max_positive;
			}

			$score -= $repeat_score;
			$positives[] = sprintf(
				__('Repeat customer (%d times): -%d%% (capped at %d%%)', 'woocommerce-order-risk-analyzer'),
				$repeat_orders,
				$repeat_score,
				$max_positive
			);
		}

		// Clamp score 0–100
		$score = max(0, min(100, $score));

		return [
			'score' => $score,
			'positives' => $positives,
			'negatives' => $negatives,
		];
	}

    private function count_completed_orders_by_email($email) {
        if (empty($email)) return 0;

        $query = new WC_Order_Query([
            'limit'         => -1,
            'status'        => 'completed',
            'billing_email' => $email,
            'return'        => 'ids',
        ]);

        return count($query->get_orders());
    }

    private function count_cancelled_orders_by_email($email) {
        if (empty($email)) return 0;

        $query = new WC_Order_Query([
            'limit'         => -1,
            'status'        => 'cancelled',
            'billing_email' => $email,
            'return'        => 'ids',
        ]);

        return count($query->get_orders());
    }

    private function get_default_settings() {
    // Try to get default settings from admin class if available
		if (class_exists('WC_Order_Risk_Admin')) {
			$admin = WC_Order_Risk_Admin::init();
			if (method_exists($admin, 'get_default_settings')) {
				return $admin->get_default_settings();
			}
		}

		// Fallback if admin class/method doesn't exist
		return [
			'weights' => [
				'repeat_orders'     => 5,
				'cancelled_orders'  => 10,
				'guest_orders'      => 10,
				'payment_method'    => 15,
			],
			//'high_risk_payment_methods' => ['cod', 'cheque'],
			'thresholds' => [
				'low' => 40,
				'medium' => 70,
			],
		];
	}
}