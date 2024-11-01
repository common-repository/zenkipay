<?php

/**
 * Main Zenki Gateway Class
 *
 * @package Zenkipay/Gateways
 * @author Zenki
 *
 */

if (!defined('ABSPATH')) {
    exit();
}

require_once dirname(__DIR__) . '/lib/svix/init.php';
require_once dirname(__DIR__) . '/lib/zenkipay/init.php';

class WC_Zenki_Gateway extends WC_Payment_Gateway
{
    protected $GATEWAY_NAME = 'Zenkipay';
    protected $test_mode = true;
    protected $sync_code;
    protected $api_key;
    protected $secret_key;
    protected $whsec;
    protected $plugin_version = '2.3.0';
    protected $purchase_data_version = 'v1.0.0';
    protected $js_url = 'https://resources.zenki.fi';
    protected $api_url = 'https://api.zenki.fi';

    public function __construct()
    {
        $this->id = 'zenkipay'; // payment gateway plugin ID
        $this->has_fields = false;
        $this->order_button_text = __('Continue with Zenkipay', 'zenkipay');
        $this->method_title = __('Zenkipay', 'zenkipay');
        $this->method_description = __('Your shoppers can pay with cryptos, any wallet, any coin! Transaction secured.', 'zenkipay');

        // Gateways can support subscriptions, refunds, saved payment methods,
        // but in this case we begin with simple payments
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();

        $this->title = $this->getCheckoutTitle($this->settings['title']);
        $this->description =
            __('Pay with cryptos, any wallet, any coin!. Transaction 100%', 'zenkipay') .
            ' <a href="' .
            esc_url('https://zenkipay.io/para-compradores/') .
            '" target="_blanck">' .
            __('secured', 'zenkipay') .
            '</a>.';

        $this->enabled = $this->settings['enabled'];
        $this->test_mode = strcmp($this->settings['test_mode'], 'yes') == 0;
        $this->sync_code = $this->settings['sync_code'];
        $this->api_key = $this->test_mode ? $this->settings['api_key_test'] : $this->settings['api_key_live'];
        $this->secret_key = $this->test_mode ? $this->settings['secret_key_test'] : $this->settings['secret_key_live'];
        $this->whsec = $this->test_mode ? $this->settings['whsec_test'] : $this->settings['whsec_live'];

        add_action('wp_enqueue_scripts', [$this, 'load_scripts']);
        add_action('woocommerce_api_zenkipay_verify_payment', [$this, 'zenkipayVerifyPayment']);
        add_action('woocommerce_api_zenkipay_create_order', [$this, 'createZenkipayOrder']);

        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('woocommerce_api_' . strtolower(get_class($this)), [$this, 'webhookHandler']);

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        wp_enqueue_style('zenkipay_style', plugins_url('assets/css/styles.css', ZNK_WC_PLUGIN_FILE), [], $this->plugin_version);
    }

    public function webhookHandler()
    {
        $order_id = '';
        $payload = file_get_contents('php://input');
        $this->logger->info('Zenkipay - Webhook => ' . $payload);

        $headers = apache_request_headers();
        $svix_headers = [];
        foreach ($headers as $key => $value) {
            $header = strtolower($key);
            $svix_headers[$header] = $value;
        }

        try {
            $secret = $this->whsec;
            $wh = new \Svix\Webhook($secret);
            $json = $wh->verify($payload, $svix_headers);
            $payment = json_decode($json->flatData);

            if ($payment->paymentInfo->cryptoPayment->transactionStatus != 'COMPLETED') {
                header('HTTP/1.1 400 Bad Request');
                header('Content-type: application/json');
                echo json_encode(['error' => true, 'message' => 'Transaction status is not completed.']);
                exit();
            }

            $args = [
                // 'status' => 'pending', // Accepts a string: one of 'pending', 'processing', 'on-hold', 'completed', 'refunded, 'failed', 'cancelled', or a custom order status.
                'meta_key' => 'zenkipay_cart_id', // Postmeta key field
                'meta_value' => $payment->cartId, // Postmeta value field
                'meta_compare' => '=', // Possible values are ‘=’, ‘!=’, ‘>’, ‘>=’, ‘<‘, ‘<=’, ‘LIKE’, ‘NOT LIKE’, ‘IN’, ‘NOT IN’, ‘BETWEEN’, ‘NOT BETWEEN’, ‘EXISTS’ (only in WP >= 3.5), and ‘NOT EXISTS’ (also only in WP >= 3.5). Values ‘REGEXP’, ‘NOT REGEXP’ and ‘RLIKE’ were added in WordPress 3.7. Default value is ‘=’.
                'return' => 'ids', // Accepts a string: 'ids' or 'objects'. Default: 'objects'.
            ];
            $order_ids = wc_get_orders($args);
            $this->logger->info('Zenkipay - Webhook - args => ' . json_encode($args));
            $this->logger->info('Zenkipay - Webhook - order_ids => ' . json_encode($order_ids));

            // Se valida que se haya encontrado la orden por el metadato del cartId
            if (!count($order_ids)) {
                header('HTTP/1.1 400 Bad Request');
                header('Content-type: application/json');
                echo json_encode(['error' => true, 'message' => 'Order with cartId ' . $payment->cartId . ' was not found.']);
                exit();
            }

            $order_id = $order_ids[0];
            // $order_id = $payment->orderId;
            $order = new WC_Order($order_id);
            $order->payment_complete();
            $order->add_order_note(sprintf("%s payment completed with Order Id of '%s'", $this->GATEWAY_NAME, $payment->zenkiOrderId));

            update_post_meta($order->get_id(), '_zenkipay_order_id', $payment->zenkiOrderId);
            update_post_meta($order->get_id(), 'zenkipay_tracking_number', '');

            wc_reduce_stock_levels($order_id);

            // Actualiza el orderID de la compra
            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $zenkipay->orders()->update($payment->zenkiOrderId, ['orderId' => $order_id]);

            $this->wc_order_add_discount($order, 'Zenkipay discount', $payment->paymentInfo->cryptoLove->discountAmount);
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - webhookHandler: ' . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-type: application/json');
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
            exit();
        }

        header('HTTP/1.1 200 OK');
        header('Content-type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }

    public function process_admin_options()
    {
        // parent::process_admin_options();
        $settings = new WC_Admin_Settings();

        $post_data = $this->get_post_data();
        $sync_code = $post_data['woocommerce_' . $this->id . '_sync_code'];
        $this->settings['title'] = $post_data['woocommerce_' . $this->id . '_title'];
        $this->settings['test_mode'] = $post_data['woocommerce_' . $this->id . '_test_mode'] == '1' ? 'yes' : 'no';
        $this->settings['enabled'] = $post_data['woocommerce_' . $this->id . '_enabled'] == '1' ? 'yes' : 'no';
        $this->test_mode = $post_data['woocommerce_' . $this->id . '_test_mode'] == '1';

        if (!$this->validateZenkipayKey($sync_code)) {
            $this->settings['enabled'] = 'no';
            $this->settings['sync_code'] = '';

            // TEST
            $this->settings['api_key_test'] = '';
            $this->settings['secret_key_test'] = '';
            $this->settings['whsec_test'] = '';

            // PROD
            $this->settings['api_key_live'] = '';
            $this->settings['secret_key_live'] = '';
            $this->settings['whsec_live'] = '';

            $settings->add_error(__('An error occurred while syncing the account', 'zenkipay'));
        } else {
            $this->settings['sync_code'] = $sync_code;
            $settings->add_message(__('Synchronization completed successfully', 'zenkipay'));
        }

        $this->logger->info('Zenkipay - $this->settings[] => ' . json_encode($this->settings));

        return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
    }

    /**
     * Plugin options
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable Zenkipay', 'zenkipay'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ],
            'test_mode' => [
                'title' => __('Enable test mode', 'zenkipay'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'zenkipay'),
                'type' => 'select',
                'description' => __('This controls the title for the payment method the customer sees during checkout.', 'zenkipay'),
                'default' => __('Zenkipay', 'zenkipay'),
                // 'desc_tip' => true,
                'options' => [
                    'ZENKIPAY' => 'Zenkipay',
                    'PAY_WITH_ZENKIPAY' => 'Pay with Zenkipay',
                    'PAY_WITH_CRYPTO' => 'Pay with Crypto (Zenkipay)',
                ],
            ],
            'sync_code' => [
                'title' => __('Enter sync code', 'zenkipay'),
                'type' => 'text',
                'default' => '',
            ],
        ];
    }

    function admin_options()
    {
        wp_enqueue_style('font_montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap', ZNK_WC_PLUGIN_FILE, [], $this->plugin_version);
        wp_enqueue_style('zenkipay_admin_style', plugins_url('assets/css/admin-style.css', ZNK_WC_PLUGIN_FILE), [], $this->plugin_version);
        wp_enqueue_script('imask_js', 'https://unpkg.com/imask', [], $this->plugin_version);
        wp_enqueue_script('zenkipay_js_input', plugins_url('assets/js/zenkipay-input-controller.js', ZNK_WC_PLUGIN_FILE), [], $this->plugin_version);

        include_once dirname(__DIR__) . '/templates/admin.php';
    }

    public function payment_fields()
    {
        $lang = substr(get_bloginfo('language'), 0, 2);
        $this->crypto_btn = $lang == 'es' ? plugins_url('assets/icons/crypto-btn-es.png', __DIR__) : plugins_url('assets/icons/crypto-btn.png', __DIR__);
        include_once dirname(__DIR__) . '/templates/payment.php';
    }

    /**
     * Process payment at checkout
     *
     * @return int $order_id
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = wc_get_order($order_id);

        if (isset($_POST['zenki_status']) && $_POST['zenki_status'] == 'on-hold') {
            // Mark as on-hold (we're awaiting the webhook's confirmation)
            $order->set_status('on-hold');
            $order->save();

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Registra el transaction hash y el transaction url explorer url en la orden
            $this->addTrxHash($order_id);

            // Remueve el valor de la sesión de zenki_cart_id
            WC()->session->set('zenki_cart_id', null);

            // Return thankyou redirect
            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        } else {
            wc_add_notice(__('Zenkipay is waiting for your payment.'), 'notice', ['zenkipay-notice' => 'true']);
        }
    }

    /**
     * Handles admin notices
     *
     * @return void
     */
    public function admin_notices()
    {
        if ('no' == $this->enabled) {
            return;
        }

        /**
         * Check if WC is installed and activated
         */
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // WooCommerce is NOT enabled!
            echo wp_kses_post('<div class="error"><p>');
            echo __('Zenkipay needs WooCommerce plugin is installed and activated to work.', 'zenkipay');
            echo wp_kses_post('</p></div>');
            return;
        }
    }

    /**
     * Checks if the Zenkipay key is valid
     *
     * @return boolean
     */
    protected function validateZenkipayKey($sync_code)
    {
        if (!$this->setCredentials($sync_code)) {
            return false;
        }

        $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
        $result = $zenkipay->getAccessToken();

        // Se valida que la respuesta sea un JWT
        $regex = preg_match('/^([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_\-\+\/=]*)/', $result);
        if ($regex != 1) {
            return false;
        }

        return true;
    }

    public function handleTrackingNumber($zenkipay_order_id, $data)
    {
        try {
            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $result = $zenkipay->trackingNumbers()->create($zenkipay_order_id, $data);

            $this->logger->info('Zenkipay - handleTrackingNumber => ' . json_encode($result));

            return true;
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - handleTrackingNumber: ' . $e->getMessage());
            return false;
        }
    }

    public function createRefund($zenkipay_order_id, $data)
    {
        try {
            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $result = $zenkipay->refunds()->create($zenkipay_order_id, $data);

            $this->logger->info('Zenkipay - createRefund => ' . json_encode($result));

            return true;
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - createRefund: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Loads (enqueue) static files (js & css) for the checkout page
     *
     * @return void
     */
    public function load_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        $create_order_url = esc_url(WC()->api_request_url('zenkipay_create_order'));
        $payment_args = [
            'create_order_url' => $create_order_url,
        ];

        wp_enqueue_script('zenkipay_js_resource', $this->js_url . '/zenkipay/script/v2/zenkipay.js', [], $this->plugin_version, true);
        wp_enqueue_script('zenkipay_js_woo', plugins_url('assets/js/znk-modal.js', ZNK_WC_PLUGIN_FILE), ['jquery', 'zenkipay_js_resource'], $this->plugin_version, true);
        wp_enqueue_style('zenkipay_checkout_style', plugins_url('assets/css/checkout-style.css', ZNK_WC_PLUGIN_FILE), [], $this->plugin_version, 'all');
        wp_localize_script('zenkipay_js_woo', 'zenkipay_payment_args', $payment_args);
    }

    /**
     * Generates the input data for Zenkipay's modal
     *
     * @return Array
     */
    protected function getZenkipayPurchaseOptions($customer_email, $country)
    {
        $cart = WC()->cart;
        $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
        $totalItemsAmount = 0;
        $items = [];
        $items_types = [];

        foreach ($cart->get_cart() as $item) {
            // Get an instance of corresponding the WC_Product object
            $product = wc_get_product($item['product_id']);
            $thumbnailUrl = wp_get_attachment_image_url($product->get_image_id());
            $product_type = $product->get_type();
            $name = trim(preg_replace('/[[:^print:]]/', '', strip_tags($product->get_title())));
            $desc = trim(preg_replace('/[[:^print:]]/', '', strip_tags($product->get_short_description())));
            $qty = (int) $item['quantity'];
            $product_price = wc_get_price_excluding_tax($product); // without taxes

            // If product has variations, image is taken from here
            if ($product_type == 'variable') {
                $variable_product = new WC_Product_Variation($item['variation_id']);
                $thumbnailUrl = wp_get_attachment_image_url($variable_product->get_image_id());
                $product_price = wc_get_price_excluding_tax($variable_product); // without taxes
            }

            $item_type = $product->is_virtual() || $product->is_downloadable() ? 'WITHOUT_CARRIER' : 'WITH_CARRIER';
            array_push($items_types, $item_type);

            $items[] = (object) [
                'externalId' => $product->get_id(),
                'name' => $name,
                'description' => $desc,
                'quantity' => $qty,
                'thumbnailUrl' => $thumbnailUrl ? esc_url($thumbnailUrl) : '',
                'unitPrice' => round($product_price, 2), // without taxes,
                'type' => $item_type,
            ];

            $totalItemsAmount += $product_price * $qty;
        }

        $shippingAmount = $cart->get_shipping_total();
        $subtotalAmount = $totalItemsAmount + $shippingAmount;
        $discountAmount = $cart->get_discount_total();
        $grandTotalAmount = $cart->total;

        $purchase_data = [
            'version' => $this->purchase_data_version,
            'type' => $this->getOrderType($items_types),
            'cartId' => $this->getZenkiCartId(),
            'shopper' => [
                'email' => !empty($customer_email) ? $customer_email : $cart->get_customer()->get_billing_email(),
            ],
            'items' => $items,
            'countryCodeIso2' => !empty($cart->get_customer()->get_billing_country()) ? $cart->get_customer()->get_billing_country() : $country,
            'breakdown' => [
                'currencyCodeIso3' => get_woocommerce_currency(),
                'totalItemsAmount' => round($totalItemsAmount, 2), // without taxes
                'shipmentAmount' => round($shippingAmount, 2), // without taxes
                'subtotalAmount' => round($subtotalAmount, 2), // without taxes
                'taxesAmount' => round($cart->get_taxes_total(), 2),
                'localTaxesAmount' => 0,
                'importCosts' => 0,
                'discountAmount' => round($discountAmount, 2),
                'grandTotalAmount' => round($grandTotalAmount, 2),
            ],
        ];

        $this->logger->info('Zenkipay - order_payload => ' . json_encode($purchase_data));

        $zenkipay_order = $zenkipay->orders()->create($purchase_data);

        $this->logger->info('Zenkipay - zenkipay_order => ' . json_encode($zenkipay_order));

        // Ocurrió un error y la orden no fue generada
        if ($zenkipay_order->errorCode) {
            return (array) $zenkipay_order;
        }

        return [
            'zenkipay_order_id' => $zenkipay_order->zenkiOrderId,
            'payment_signature' => $zenkipay_order->paymentSignature,
        ];
    }

    /**
     * Verify payment made on the checkout page
     *
     * @return string
     */
    public function zenkipayVerifyPayment()
    {
        header('HTTP/1.1 200 OK');

        if (isset($_POST['order_id']) && isset($_POST['complete'])) {
            $complete = $_POST['complete'];
            if ($complete != '0') {
                return;
            }

            $order = wc_get_order($_POST['order_id']);
            $order->update_status('failed', 'Payment not successful.');
            //$redirect_url = esc_url($this->get_return_url($order));
            $redirect_url = $order->get_view_order_url();

            echo json_encode(['redirect_url' => $redirect_url]);
        }

        die();
    }

    /**
     * Get Merchan Info
     *
     * @return object
     */
    public function getMerchantInfo()
    {
        try {
            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $merchant = $zenkipay->merchants()->me();
            return $merchant;
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - getMerchantInfo: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get order type
     *
     * @param array $items_types
     *
     * @return string
     */
    protected function getOrderType($items_types)
    {
        $needles = ['WITH_CARRIER', 'WITHOUT_CARRIER'];
        if (empty(array_diff($needles, $items_types))) {
            return 'MIXED';
        } elseif (in_array('WITH_CARRIER', $items_types)) {
            return 'WITH_CARRIER';
        } else {
            return 'WITHOUT_CARRIER';
        }
    }

    public function createZenkipayOrder()
    {
        header('HTTP/1.1 200 OK');

        try {
            $customer_email = isset($_POST['email']) ? $_POST['email'] : null;
            $country = isset($_POST['country']) ? $_POST['country'] : null;
            $zenki_order = $this->getZenkipayPurchaseOptions($customer_email, $country);
            echo json_encode($zenki_order);
        } catch (Exception $e) {
            echo json_encode(['error' => true, 'message' => __('An unexpected error has occurred.', 'zenkipay')]);
        }

        die();
    }

    private function setCredentials($sync_code)
    {
        try {
            $this->logger->info('Zenkipay - setCredentials settings_sync_code => ' . $this->settings['sync_code']);
            $this->logger->info('Zenkipay - setCredentials sync_code => ' . $sync_code);
            $this->logger->info('Zenkipay - setCredentials bool => ' . json_encode($this->settings['sync_code'] != $sync_code));

            if (!$this->hasSyncedAccount() || $this->settings['sync_code'] != $sync_code) {
                $credentials = $this->sync($sync_code);
                $this->logger->info('Zenkipay - setCredentials => ' . json_encode($credentials));

                if (isset($credentials['errorCode'])) {
                    throw new Exception($credentials['humanMessage']);
                }

                // Se valida que la sincronización haya sido exitosa
                if (isset($credentials['status']) && $credentials['status'] != 'SYNCHRONIZED') {
                    throw new Exception('Sync status ' . $credentials['status'] . ' is different from SYNCHRONIZED.');
                }

                // TEST
                $this->settings['api_key_test'] = $credentials['synchronizationAccessData']['sandboxApiAccessData']['apiAccessData']['apiKey'];
                $this->settings['secret_key_test'] = $credentials['synchronizationAccessData']['sandboxApiAccessData']['apiAccessData']['secretKey'];
                $this->settings['whsec_test'] = $credentials['synchronizationAccessData']['sandboxApiAccessData']['webhookAccessData']['signingSecret'];

                // PROD
                $this->settings['api_key_live'] = $credentials['synchronizationAccessData']['liveApiAccessData']['apiAccessData']['apiKey'];
                $this->settings['secret_key_live'] = $credentials['synchronizationAccessData']['liveApiAccessData']['apiAccessData']['secretKey'];
                $this->settings['whsec_live'] = $credentials['synchronizationAccessData']['liveApiAccessData']['webhookAccessData']['signingSecret'];

                $this->test_mode = $credentials['testMode'] ? 'yes' : 'no';
                $this->settings['test_mode'] = $this->test_mode;
            }

            $this->api_key = $this->test_mode ? $this->settings['api_key_test'] : $this->settings['api_key_live'];
            $this->secret_key = $this->test_mode ? $this->settings['secret_key_test'] : $this->settings['secret_key_live'];
            $this->whsec = $this->test_mode ? $this->settings['whsec_test'] : $this->settings['whsec_live'];

            return true;
        } catch (Exception $e) {
            $this->logger->error('Zenkipay - setCredentials: ' . $e->getMessage());
            return false;
        }
    }

    private function sync($sync_code)
    {
        $code = trim(str_replace('-', '', $sync_code));
        $url = $this->api_url . '/public/v1/pay/plugins/synchronize';
        $method = 'POST';
        $urlStore = site_url();
        $data = ['pluginUrl' => $urlStore, 'pluginVersion' => 'v1.0.0', 'synchronizationCode' => $code];
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $agent = 'Zenkipay-PHP/1.0';

        $this->logger->info('Zenkipay - sync => ' . json_encode($data));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, // return the transfer as a string of the return value
            CURLOPT_TIMEOUT => 30, // The maximum number of seconds to allow cURL functions to execute.
            CURLOPT_USERAGENT => $agent,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($data), // Data that will send
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            throw new Exception('Error with the ' . $method . ' request ' . $url);
        }

        curl_close($ch);
        return json_decode($result, true);
    }

    private function hasSyncedAccount()
    {
        $api_key = $this->test_mode ? $this->settings['api_key_test'] : $this->settings['api_key_live'];
        $secret_key = $this->test_mode ? $this->settings['secret_key_test'] : $this->settings['secret_key_live'];
        $whsec = $this->test_mode ? $this->settings['whsec_test'] : $this->settings['whsec_live'];

        $this->logger->info('Zenkipay - hasSyncedAccount => ' . json_encode(empty($api_key) || empty($secret_key) || empty($whsec)));

        if (empty($api_key) || empty($secret_key) || empty($whsec)) {
            return false;
        }

        return true;
    }

    private function addTrxHash($order_id)
    {
        $zenki_order_id = isset($_POST['zenki_order_id']) ? $_POST['zenki_order_id'] : null;
        $trx_hash = isset($_POST['trx_hash']) ? $_POST['trx_hash'] : null;
        $trx_explorer_url = isset($_POST['trx_explorer_url']) ? $_POST['trx_explorer_url'] : null;

        $this->logger->info('addTrxHash - zenki_order_id => ' . $zenki_order_id);
        $this->logger->info('addTrxHash - trx_hash => ' . $trx_hash);
        $this->logger->info('addTrxHash - trx_explorer_url => ' . $trx_explorer_url);

        if ($trx_hash && $trx_explorer_url) {
            update_post_meta($order_id, 'trx_hash', $trx_hash);
            update_post_meta($order_id, 'trx_explorer_url', $trx_explorer_url);
            return;
        }

        if ($zenki_order_id) {
            $zenkipay = new \Zenkipay\Sdk($this->api_key, $this->secret_key);
            $zenkipay_order = $zenkipay->orders()->find($zenki_order_id);

            $this->logger->info('Zenkipay - find => ' . json_encode($zenkipay_order));

            $trx_hash = $zenkipay_order->paymentInfo->cryptoPayment->transactionHash;
            $trx_explorer_url = $zenkipay_order->paymentInfo->cryptoPayment->networkScanUrl;

            $this->logger->info('addTrxHash - trx_hash API => ' . $trx_hash);
            $this->logger->info('addTrxHash - trx_explorer_url API => ' . $trx_explorer_url);

            if ($trx_hash && $trx_explorer_url) {
                update_post_meta($order_id, 'trx_hash', $trx_hash);
                update_post_meta($order_id, 'trx_explorer_url', $trx_explorer_url);
            }
        }

        return;
    }

    public function getZenkiCartId()
    {
        $zenki_cart_id = WC()->session->get('zenki_cart_id');

        if (is_null($zenki_cart_id)) {
            $bytes = random_bytes(16);
            $zenki_cart_id = bin2hex($bytes);
            WC()->session->set('zenki_cart_id', $zenki_cart_id);
        }

        $this->logger->info('Zenkipay - $zenki_cart_id => ' . $zenki_cart_id);

        return $zenki_cart_id;
    }

    private function getCheckoutTitle($option)
    {
        $label = '';
        switch ($option) {
            case 'PAY_WITH_ZENKIPAY':
                $label = __('Pay with Zenkipay', 'zenkipay');
                break;

            case 'PAY_WITH_CRYPTO':
                $label = __('Pay with Crypto (Zenkipay)', 'zenkipay');
                break;

            default:
                $label = __('Zenkipay', 'zenkipay');
        }

        return $label;
    }

    /**
     * Add a discount to an Orders programmatically
     * (Using the FEE API - A negative fee)
     *
     * @since  3.2.0
     * @param  int     $order_id  The order ID. Required.
     * @param  string  $title  The label name for the discount. Required.
     * @param  mixed   $amount  Fixed amount (float) or percentage based on the subtotal. Required.
     * @param  string  $tax_class  The tax Class. '' by default. Optional.
     */
    private function wc_order_add_discount($order, $title, $amount, $tax_class = '')
    {
        $subtotal = $order->get_subtotal();
        $item = new WC_Order_Item_Fee();

        if (strpos($amount, '%') !== false) {
            $percentage = (float) str_replace(['%', ' '], ['', ''], $amount);
            $percentage = $percentage > 100 ? -100 : -$percentage;
            $discount = ($percentage * $subtotal) / 100;
        } else {
            $discount = (float) str_replace(' ', '', $amount);
            $discount = $discount > $subtotal ? -$subtotal : -$discount;
        }

        $item->set_tax_class($tax_class);
        $item->set_name($title);
        $item->set_amount($discount);
        $item->set_total($discount);

        // if ('0' !== $item->get_tax_class() && 'taxable' === $item->get_tax_status() && wc_tax_enabled()) {
        //     $tax_for = [
        //         'country' => $order->get_shipping_country(),
        //         'state' => $order->get_shipping_state(),
        //         'postcode' => $order->get_shipping_postcode(),
        //         'city' => $order->get_shipping_city(),
        //         'tax_class' => $item->get_tax_class(),
        //     ];
        //     $tax_rates = WC_Tax::find_rates($tax_for);
        //     $taxes = WC_Tax::calc_tax($item->get_total(), $tax_rates, true);

        //     if (method_exists($item, 'get_subtotal')) {
        //         $subtotal_taxes = WC_Tax::calc_tax($item->get_subtotal(), $tax_rates, true);
        //         $item->set_taxes(['total' => $taxes, 'subtotal' => $subtotal_taxes]);
        //         $item->set_total_tax(array_sum($taxes));
        //     } else {
        //         $item->set_taxes(['total' => $taxes]);
        //         $item->set_total_tax(array_sum($taxes));
        //     }
        //     $has_taxes = true;
        // } else {
        //     $item->set_taxes(false);
        //     $has_taxes = false;
        // }
        $item->set_taxes(false);
        $has_taxes = false;
        $item->save();

        $order->add_item($item);
        $order->calculate_totals($has_taxes);
        $order->save();
    }
}
?>
