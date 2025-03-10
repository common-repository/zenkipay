=== WooCommerce Zenkipay ===
Contributors: zenki
Tags: woocommerce, zenki, zenkipay, cryptocurrency, wallets, metamask, rainbow, muun, argent, payments, ecommerce, e-commerce, store, sales, sell, shop, shopping, cart, checkout
Requires at least: 5.3
Tested up to: 6.1.1
Requires PHP: 7.1
Stable tag: 2.3.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Zenkipay’s latest crypto payments processing solution. Accept any coin from any wallet. We support more than 150 wallets and the transaction is 100% secured. Want to learn [more](https://zenki.fi/)?

== Description ==

Zenkipay’s latest, most complete cryptocurrency payment processing solution. Accept any crypto coin with over 150 wallets around the world.

Built and supported by [Zenki](https://zenki.fi/).

= Give your customers a new experience to pay with any cryptos from any wallet with one single integration =

Streamline your business with one simple, powerful solution.

With the latest Zenkipay extension, your customers can pay with almost any wallet option and almost any cryptocurrency in just a few minutes on any device — all with one seamless checkout experience.

== Installation ==

= Requirements =

To install WooCommerce Zenkipay, you need:

* WordPress Version 5.3 or newer (installed).
* WooCommerce Version 3.9 or newer (installed and activated).
* PHP Version 7.1 or newer.
* Zenkipay merchat [account](https://zenki.fi/).

= Instructions =

1. Log in to WordPress admin.
2. Go to **Plugins > Add New**.
3. Search for the **Zenkipay** plugin.
4. Click on **Install Now** and wait until the plugin is installed successfully.
5. You can activate the plugin immediately by clicking on create your Zenkipay account [here](https://zenki.fi/).
6. Now on the success page. If you want to activate it later, you can do so via **Plugins > Installed Plugins**.

= Setup and Configuration =

Follow the steps below to connect the plugin to your Zenki account:

1. After you have activated the Zenkipay plugin, go to **WooCommerce  > Settings**.
2. Click the **Payments** tab.
3. The Payment methods list will include one Zenkipay options. Click on **Zenkipay** .
4. Enter your production/sadbox plugin key. If you do not have a Zenki merchant account, click **Create your Zenkipay account here**
5. After you have successfully obtained you plugin keys, click on the **Enable Zenkipay** checkbox to enable Zenkipay.
6. Click **Save changes**.

== Screenshots ==

1. Super easy to use interface.
2. Select from many cryptocurrencies.
3. Any crypto wallet to choose from.
4. Your payments are made quickly and smoothly.

== Changelog ==
= 2.3.0 =
* Support for coupon discounts
= 2.2.0 =
* Merchant will be able to define the checkout title for Zenkipay
= 2.1.6 =
* CartId value that is used by webhook was replaced
= 2.1.5 =
* Fix: pluginUrl for sync
= 2.1.4 =
* Added transaction hash to order details
= 2.1.3 =
* Added support for es_Es language
= 2.1.2 =
* Removed order creation when the modal is launched
= 2.1.1 =
* Fix: Right price for the variable product
= 2.1.0 =
* Implemented sync code to get authentication credentials
= 2.0.0 =
* Authentication process changed 
* SDK integration
= 1.7.3 =
* Fix: Apply Crypto Love discount after a failed purchase with another payment method
= 1.7.2 =
* serviceType property was added to purchaseData object
= 1.7.1 =
* Fix: Validates if the payment method is zenkipay before add Crypto Love discount
= 1.7.0 =
* Webhook payload decryption was implemented
* Webhook payload structure changed 
= 1.6.10 =
* Updated tracking endpoint
= 1.6.9 =
* Fix: Updated production URLs
= 1.6.8 =
* Fix: URL to activate plugin
= 1.6.7 =
* Added behavior to redirect customers to order detail if modal is closed
= 1.6.6 =
* Adjusted CSS styles for thankyou-page
= 1.6.5 =
* Fix: Languages traslation path
= 1.6.4 =
* Added es_MX translations
= 1.6.3 =
* Replace text for Order Received Thank You
= 1.6.2 =
* Fix: Gateway URL was fixed 
= 1.6.1 =
* Webhook was implemented to change the order status to complete
* Capture order's zenkipay_tracking_number
= 1.5.0 =
* Updated purchaseOptions object structure
= 1.4.5 =
* Fix: If a product has a variation, the variation price is sent it in the purchaseData object
= 1.4.4 =
* Some console logs were removed and some CSS styles were added
= 1.4.3 =
* Fix: Added signature property to purchaseOptions object
= 1.4.2 =
* Fix: Zenkipay plugin was showing twice in the backoffice
= 1.4.0 =
* Modal payload data is signed with RSA-SHA256 algorithm
= 1.3.2 =
* Checkout content and style was updated
= 1.3.1 =
* Fixed bug when sandbox key was updated
= 1.3.0 =
* WooCommerce OrderId is sent to Zenkipay
= 1.2.0 =
* New PurchaseItem's properties were added when modal is launched
= 1.1.2 =
* Zenkipay key validation.
= 1.0.0 =
* Initial release.
