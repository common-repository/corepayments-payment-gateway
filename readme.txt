=== CoreGateway Payment Gateway ===
Contributors: CoreCommerce Developer
Tags: CoreGateway, WooCommerce, CoreCommerce, CorePayments 
Requires at least: 6.4.3
Tested up to: 6.4.3
Requires PHP: 7.4
Stable tag: 1.1.8
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

CoreGateway is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
CoreGateway is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details...
 
You should have received a copy of the GNU General Public License
along with CoreGateway. If not, see https://www.gnu.org/licenses/gpl-3.0.html.

== Description ==

<a href="https://www.corecommerce.com/">CoreCommerce</a> provides all the tools and services for merchants to accept payments online:  Credit Cards, Debit Cards, ACH and more.

<a href="https://woocommerce.com/">WooCommerce</a> is one of the oldest and most powerful e-commerce solutions for WordPress. This platform is widely supported in the WordPress community which makes it easy for even 
an entry level e-commerce entrepreneur to learn to use and modify.

<p style="font-size: 1.25em;">Features</p>
<ul>
<li>Easy Install: The CoreCommerce plugin installs with one click. After installing, you will have only a few fields to fill out before you are ready to accept credit cards on your store.</li>
<li>Secure Credit Card Processing: CoreCommerce’s tokenization features and customer vault allow you to send secure payment data through a PCI-DSS Level 1 Compliant gateway.</li>
<li>Refund via Dashboard: Process full or partial refunds, directly from your WordPress dashboard. No need to search order in your CoreCommerce account.</li>
<li>Authorize Now, Capture Later: Optionally choose only to authorize transactions, and capture at a later date.</li>
<li>Restrict Card Types: Optionally choose to restrict certain card types and the plugin will hide its icon and provide a proper error message on checkout.</li>
<li>Gateway Receipts: Optionally choose to send receipts from your CoreCommerce merchant account.</li>
</ul>


<p style="font-size: 1.25em;">Requirements</p>
<ul>
<li>Active <a href="https://www.corecommerce.com/">CoreCommerce</a> account.
<li><a href="https://woocommerce.com/">WooCommerce</a> version 6.0 or higher.</li>
<li>A valid SSL certificate is required to ensure your customer credit card details are safe and make your site PCI DSS compliant. This plugin does not store the customer credit card numbers or sensitive information on your website.</li>
</ul>

For custom payment gateway integration with your WordPress website, please contact us here.

== Installation ==
1. Upload "corepayments-gateway" to the "/wp-content/plugins/" directory. If you are using the WordPress plugin, this step is automatically completed for you.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Once Activated, login to your CoreGateway Merchant Account Admin.
4. Go to Settings > API Tokens.
5. Add a New Token and name it "Wordpress".
6. Once the token is created, copy it to your clipboard.
7. In WooCommerce, select CoreGateway as your payment processor and click Set Up.
8. Enter the API Token into this field as part of the setup form and save.
9. Run a test transaction to ensure your transaction is Authorized and Captured in your Merchant Account.

You are now ready to use CoreGateway with your WooCommerce store!

* Initial release.