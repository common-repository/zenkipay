 <div class="znk-admin-container">
            <div class="znk-header">
                <img class="znk-img" alt="Zenkipay" src="<?php echo plugins_url('./../assets/icons/logo-white.png', __FILE__); ?>"/>
                <div class="znk-copy">                
                <p><?php echo __('Your shoppers can pay with cryptos, any wallet, any coin!', 'zenkipay'); ?></p>
                </div>
            </div>
            <div class="znk-form-container">
                <p class="instructions"><?php echo __(
                    'To set up quickly and easily, enter the synchronization code from your Zenkipay portal. If you don\'t have an account, open one for free ',
                    'zenkipay'
                ); ?><a href="https://portal.zenki.fi/pay/#/auth/register" target="_blank"><?php echo __('here', 'zenkipay'); ?></a>.</p>
                <p class="instructions"><?php echo __(
                    'For more information about plugin configuration ',
                    'zenkipay'
                ); ?><a href="https://support.zenki.fi/developer-center/docs/plugins-woocomerce" target="_blank"><?php echo __('click here', 'zenkipay'); ?>.</a></p>
                <hr>
                <table class="form-table">
                <?php $this->generate_settings_html(); ?>
                <tr><th></th><td style="padding: 0px 10px 0 10px"><p class="current-test-mode"><?php echo $this->test_mode ? __('Test mode', 'zenkipay') : __('Live mode', 'zenkipay'); ?></p></td></tr>
                </table> 
            </div>
        </div>