=== SandCage ===
Contributors: evankok
Tags: sandcage, availability, scaling, scalability, user experience, exif, cache, caching, asset cache, asset caching, file cache, file caching, cdn, content delivery network, retina, upload, asset, media, media library, photo, photos, image, images, bmp, gif, animated gif, jpg, jpeg, png, compress image, processing, asset processing, image processing, bmp processing, gif processing, animated gif processing, jpg processing, jpeg processing, png processing, media processing, hosting, asset hosting, image hosting, bmp hosting, gif hosting, animated gif hosting, jpg hosting, jpeg hosting, png hosting, media hosting, delivery, asset delivery, image delivery, bmp delivery, gif delivery, animated gif delivery, jpg delivery, jpeg delivery, png delivery, media delivery, image optimizer, image resize, optimize animated gif, optimize gif, optimize jpeg, optimize png, optimization, optimize, wpo, performance, web performance, web performance optimization, performance optimization, speed, site speed, sitespeed, speed up site, compress, optimize, optimizer, plugin, google drive, full site delivery, full site acceleration, seo, page rank, pagerank, google rank, google pagerank, google page rank, google page speed, google pagespeed, gtmetrix, gtmetrix speed, gtmetrix speed test, storage, ftp, aws, s3, flash media server, amazon web services, cloud files, rackspace, akamai, max cdn, wp cache, smush
Requires at least: 3.2
Tested up to: 4.6.1
Stable tag: 0.1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SandCage Plugin to integrate with your SandCage account. Manage your Files and Speed up your Website. Process, Store and Delivery.

== Description ==

This plugins gives you a great framework to use for integrating with PayPal.
It stores both live and sandbox API credentials and allows you to switch back
and forth easily.  All NVP API calls are passed through the framework and
default values such as API version, API credentials, and even currency code are
added to the request based on settings from the admin panel.

It also has a built in IPN listener that validates messages as coming from
PayPal then throws WordPress actions based on messages received.  For example it
throws "paypal-recurring_payment_profile_cancel" when someone cancels a
recurring payment that they had set up with you.  It passes along all the info
that PayPal sent to the action, so it's simple to create other plugins that use
this one.

Requires PHP5.

You may also be interested in WordPress tips and tricks at <a href="http://wpinformer.com">WordPress Informer</a> or gerneral <a href="http://webdevnews.net">Web Developer News</a>

== Installation ==

1. Use automatic installer.

== Frequently Asked Questions ==

= How do I send a request to PayPal? =

To send a request to PayPal, simply build the request as an associative array and pass it to the hashCall helper function like this:
<code>
$ppParams = array(
	'METHOD'         => 'doDirectPayment',
	'PAYMENTACTION'  => 'Sale',
	'IPADDRESS'      => '123.123.123.123',
	'AMT'            => '222.22',
	'DESC'           => 'some product',
	'CREDITCARDTYPE' => 'VISA',
	'ACCT'           => '4111111111111111',
	'EXPDATE'        => '112011',
	'CVV2'           => '123',
	'FIRSTNAME'      => 'Aaron',
	'LASTNAME'       => 'Campbell',
	'EMAIL'          => 'pptest@xavisys.com',
	'STREET'         => '123 some pl',
	'STREET2'        => '',
	'CITY'           => 'San Diego',
	'STATE'          => 'CA',
	'ZIP'            => '92101',
	'COUNTRYCODE'    => 'US',
	'INVNUM'         => '12345',
);

$response = hashCall($ppParams);
</code>

= How do I use the Listener to process PayPal messages? =

First you have to tell PayPal to send message to the correct URL.  Go to the
PayPal Framework settings page and click the "PayPal IPN Listener URL" link to
see instructions on how to use the URL that's listed there.  Once your PayPal
account has been set up the listener will automatically process all IPN messages
and turn them into WordPress actions that you can hook into.  You can use the
'paypal-ipn' action to look at every message you ever get, or hook directly into
a 'paypal-{transaction-type}' hook to process a specific type of message:
<code>
add_action('paypal-ipn', 'my_process_ipn');
function my_process_ipn( $data ) {
	echo 'Processing IPN Message:<pre>';
	var_dump( $data );
	echo '</pre>';
}

add_action('paypal-recurring_payment_failed', 'my_process_ipn_recurring_payment_failed');
function my_process_ipn_recurring_payment_failed( $data ) {
	echo 'A recurring payment has failed. Here is the data I have to process this:<pre>';
	var_dump( $data );
	echo '</pre>';
}
</code>

= Why do you set sslverify to false? =

Many servers have out of date certificate lists, so this is necessary to be as
portable as possible.  However, if your server is set up right you can force
sslverify like this:
<code>
add_filter( 'paypal_framework_sslverify', '__return_true' );
</code>

== Changelog ==

= 0.1.0.0 =
* Original version released to the wordpress.org repository
