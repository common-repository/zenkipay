<?php
/*
 * Plugin Name: Zenkipay
 * Plugin URI: https://github.com/zenkifi/zenkipay-woocommerce
 * Description: Your shoppers can pay with cryptos, any wallet, any coin! Transaction secured.
 * Author: Zenki
 * Author URI: https://zenki.fi/
 * Text Domain: zenkipay
 * Version: 2.3.0
 */

if (!defined('ABSPATH')) {
    exit();
}

define('ZNK_WC_PLUGIN_FILE', __FILE__);

//Languages traslation
load_plugin_textdomain('zenkipay', false, dirname(plugin_basename(__FILE__)) . '/languages/');

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_action('plugins_loaded', 'zenkipay_init_gateway_class', 0);
add_filter('woocommerce_thankyou_order_received_text', 'override_thankyou_text', 10, 2);
add_filter('the_title', 'woo_title_order_received', 10, 2);
add_action('woocommerce_order_refunded', 'zenkipay_woocommerce_order_refunded', 10, 2);
add_action('woocommerce_checkout_order_created', 'add_custom_cart_id_order_meta');
add_action('woocommerce_order_details_before_order_table', 'zenkipay_before_order_table');

function zenkipay_init_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once 'includes/class.znk_wc_payment_gateway.php';

    add_filter('woocommerce_payment_gateways', 'add_gateway_class');

    function znk_plugin_action_links($links)
    {
        $zenki_settings_url = esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=checkout&section=zenkipay'));
        array_unshift($links, "<a title='Zenkipay Settings Page' href='$zenki_settings_url'>" . __('Settings', 'zenkipay') . '</a>');

        return $links;
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'znk_plugin_action_links');

    /**
     * Add the Gateway to WooCommerce
     *
     * @return Array Gateway list with our gateway added
     */
    function add_gateway_class($gateways)
    {
        $gateways[] = 'WC_Zenki_Gateway';
        return $gateways;
    }
}

add_action(
    'save_post_shop_order',
    function (int $postId, \WP_Post $post, bool $update) {
        $logger = wc_get_logger();

        // Ignore order (post) creation
        if ($update !== true || !is_admin()) {
            return;
        }

        // Here comes your code...
        $order = new WC_Order($postId);
        $logger->info('Zenkipay - order_id => ' . $order->get_id());

        $payment_method = $order->get_payment_method();
        $logger->info('Zenkipay - payment_method => ' . $payment_method);
        // Checks if the order was pay with zenkipay
        if ($payment_method !== 'zenkipay') {
            return;
        }

        // Get the meta data in an unprotected array
        $zenkipay_order_id = $order->get_meta('_zenkipay_order_id');
        $tracking_number = $order->get_meta('zenkipay_tracking_number');
        $logger->info('Zenkipay - zenkipay_order_id => ' . $zenkipay_order_id);
        $logger->info('Zenkipay - tracking_number => ' . $tracking_number);

        // Checks if we have the required data to send the tracking number to Zenkipay
        if (empty($zenkipay_order_id) || empty($tracking_number)) {
            return;
        }

        $data = [['courierType' => 'EXTERNAL', 'trackingId' => $tracking_number]];
        $zenkipay = new WC_Zenki_Gateway();
        $zenkipay->handleTrackingNumber($zenkipay_order_id, $data);
    },
    10,
    3
);

// Order Received Thank You Text
function override_thankyou_text($thankyoutext, $order)
{
    if ($order->get_payment_method() != 'zenkipay') {
        return $thankyoutext;
    }

    $icon = plugins_url('zenkipay/assets/icons/clock.svg', __DIR__);
    $text = __('Your order was received correctly and your payment will be confirmed by email shortly.', 'zenkipay');
    $added_text = '<img src="' . $icon . '" height="40" width="40" alt="pending" style="margin-right: 7px;" /> <span>' . $text . '</span>';
    return $added_text;
}

// // Order Received Thank You Title
function woo_title_order_received($title, $id)
{
    if (function_exists('is_order_received_page') && is_order_received_page() && get_the_ID() === $id) {
        $title = 'Pending order';
    }
    return $title;
}

/**
 * Capture a dispute when a refund was made
 *
 * @param type $order_id
 * @param type $refund_id
 *
 */
function zenkipay_woocommerce_order_refunded($order_id, $refund_id)
{
    $logger = wc_get_logger();
    $logger->info('ORDER: ' . $order_id);
    $logger->info('REFUND: ' . $refund_id);

    $order = wc_get_order($order_id);
    // $refund = wc_get_order($refund_id);

    $logger->info('get_payment_method: ' . $order->get_payment_method());

    if ($order->get_payment_method() != 'zenkipay') {
        return;
    }

    $zenkipay_order_id = get_post_meta($order_id, '_zenkipay_order_id', true);
    $logger->info('_zenkipay_order_id: ' . $zenkipay_order_id);
    if (!strlen($zenkipay_order_id)) {
        return;
    }

    // $amount = floatval($refund->get_amount());
    // $reason = $refund->get_reason();
    $data = ['reason' => 'Refund request originated by WooCommerce.'];

    try {
        $zenkipay = new WC_Zenki_Gateway();
        $zenkipay->createRefund($zenkipay_order_id, $data);
        $order->add_order_note('A dispute was created in Zenkipay');
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $order->add_order_note('There was an error creating a dispute in Zenkipay: ' . $e->getMessage());
    }

    return;
}

function add_custom_cart_id_order_meta($order)
{
    if ($order->get_payment_method() != 'zenkipay') {
        return;
    }

    $zenkipay = new WC_Zenki_Gateway();
    $zenki_cart_id = $zenkipay->getZenkiCartId();

    $logger = wc_get_logger();
    $logger->info('#get_cart_hash => ' . $zenki_cart_id);
    update_post_meta($order->get_id(), 'zenkipay_cart_id', $zenki_cart_id);
    // $logger->info('#get_cart_hash => ' . $order->get_cart_hash());
    // update_post_meta($order->get_id(), 'zenkipay_cart_id', $order->get_cart_hash());
}

function zenkipay_before_order_table($order)
{
    $transactionExplorerUrl = $order->get_meta('trx_explorer_url');
    $transactionHash = $order->get_meta('trx_hash');
    ?>
        <?php if ($transactionHash && $transactionExplorerUrl): ?>
		    <h2 class="woocommerce-order-details__title"><?php echo __('Zenkipay Details', 'zenkipay'); ?></h2>
		    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
			    <tbody>								
					<tr>
						<th><?php echo __('Transaction Hash', 'zenkipay'); ?></th>
						<td><a style="color: #00ACD1 !important;" target="_blank" href="<?php echo esc_html($transactionExplorerUrl); ?>"><?php echo esc_html($transactionHash); ?></a></td>
					</tr>														
			    </tbody>
		    </table>
            <br/>
        <?php endif; ?>
	<?php
}
