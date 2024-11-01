jQuery(document).ready(function () {
    var $form = jQuery('form.checkout,form#order_review');

    // Zenkipay params
    var createOrderUrl = zenkipay_payment_args.create_order_url;
    var previousMsgType = '';

    jQuery('body').on('click', 'form.checkout button:submit', function () {
        jQuery('.woocommerce-info .woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
        // Make sure there's not an old zenki_order_id on the form
        jQuery('form.checkout').find('[name=zenki_order_id]').remove();
        jQuery('form.checkout').find('[name=zenki_status]').remove();
        jQuery('form.checkout').find('[name=trx_hash]').remove();
        jQuery('form.checkout').find('[name=trx_explorer_url]').remove();
    });

    jQuery('form#order_review').submit(function () {
        if (jQuery('input[name=payment_method]:checked').val() !== 'zenkipay') {
            return true;
        }

        // Pass if we have a token
        if ($form.find('[name=zenki_order_id]').length) {
            return true;
        }

        const email = jQuery('#billing_email').val();
        const country = jQuery('#billing_country').val();
        $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

        zenkipayOrderRequest({ email, country });
        return false; // Prevent the form from submitting with the default action
    });

    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {
        if (jQuery('input[name=payment_method]:checked').val() !== 'zenkipay') {
            return true;
        }

        // Pass if we have a token
        if ($form.find('[name=zenki_order_id]').length) {
            return true;
        }

        const email = jQuery('#billing_email').val();
        const country = jQuery('#billing_country').val();
        $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

        zenkipayOrderRequest({ email, country });
        return false; // Prevent the form from submitting with the default action
    });

    function zenkipayOrderRequest(data) {
        jQuery.post(createOrderUrl, data).success((result) => {
            var response = JSON.parse(result);

            if (!response.hasOwnProperty('error')) {
                var purchaseOptions = {
                    paymentSignature: response.payment_signature,
                    orderId: response.zenkipay_order_id,
                };

                $form.append('<input type="hidden" name="zenki_order_id" value="' + purchaseOptions.orderId + '" />');

                formHandler(purchaseOptions);
            } else {
                handleError(response.message);
            }
        });
    }

    function formHandler(purchaseOptions) {
        $form.unblock();
        zenkipay.openModal(purchaseOptions, handleZenkipayEvents);
        // $form.submit();
    }

    function handleZenkipayEvents(error, data) {
        console.log('handleZenkipayEvents', data);

        if (error) {
            jQuery('*[data-zenkipay-notice="true"]').remove();
            handleError(error);
            return;
        }

        if (data.postMsgType === 'shopper_payment_confirmation') {
            $form.append('<input type="hidden" name="zenki_status" value="on-hold" />');
            $form.submit();
            return;
        }

        if (data.postMsgType === 'done') {
            $form.append('<input type="hidden" name="zenki_status" value="on-hold" />');
            $form.submit();
            return;
        }

        if (data.postMsgType === 'processing_payment' && data.transaction) {
            $form.append('<input type="hidden" name="trx_hash" value="' + data.transaction.transactionHash + '" />');
            $form.append('<input type="hidden" name="trx_explorer_url" value="' + data.transaction.transactionExplorerUrl + '" />');
        }

        if ((previousMsgType === 'processing_payment' || previousMsgType === 'done') && data.isCompleted) {
            $form.append('<input type="hidden" name="zenki_status" value="on-hold" />');
            $form.submit();
        }

        if (data.postMsgType === 'cancel') {
            jQuery('*[data-zenkipay-notice="true"]').remove();
        }

        previousMsgType = data.postMsgType;
        return;
    }

    function handleError(error) {
        jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
        jQuery('#zenkipay-payment-container')
            .closest('div')
            .before('<ul style="background-color: #e2401c; color: #fff;" class="woocommerce_error woocommerce-error"><li> ' + error + ' </li></ul>');

        zenkipay.closeModal();
        $form.unblock();
    }
});
