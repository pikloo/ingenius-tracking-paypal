=== Plugin Name ===
Contributors: (this should be a list of wordpress.org userid's)
Donate link: http://example.com/
Tags: comments, spam
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin retrieves tracking numbers and carrier data when an order is paid by paypal and then sends this information to WooCommerce Paypal Payments.

== Description ==

This plugin allows you to automatically send a tracking to Paypal using the Paypal API and to display the tracking sent on the order administration page in the WooCommerce Paypal Payment metabox.

2 scenarios are possible:

* The contributor adds, modifies (tracking number and/or carrier name) or deletes an Aftership tracking on the order editing page in the administration. This modification is sent to the Paypal API as soon as the order is saved.
* An import of orders including the tracking number and the carrier name processes the modifications concerning the Aftership tracking and sends the additions and/or modifications to the Paypal API.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `ingenius-tracking-paypal.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==
= 2.0.1.2 =
* Add customer note

= 2.0.1.1 =
* FIX MAJ update checker

= 2.0.1 =
* MAJ update checker