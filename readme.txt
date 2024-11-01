=== Woocommerce UPS Israel Domestic Print Orders Plugin ===
Contributors: O.P.S.I. Israel
Donate link: https://pickuppoint.co.il/Print/Woo
Tags: pickups, ups, woocommerce, pickups for wordpress, ups for wordpress, pickups for woocommerce, ups for woocommerce, woocommerce shipping
Requires at least: 4.4
Tested up to: 4.9.10
Stable tag: 4.9.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html


== Description ==

Woocommerce UPS Israel Domestic Print Orders Plugin:
A simple solution designed for all WordPress WooCommerce site owners, who are clients of UPS Israel.
Plugin is designed to easily print UPS labels for the selected orders.

== Installation ==

Manual installation:
Upload `woocommerce-ups-ship-print-orders.zip` to the `/wp-content/plugins/` directory and extract all files.
In your WordPress Dashboard, go to the "Plugins" screen and activate the plugin.
Note: you can always download PickUPS from our web site at: https://pick-ups.co.il/woocommerce-ups-ship-print-orders.zip
To work correctly you need to install the application UPS SmartShip™ Printing Tool: https://pickuppoint.co.il/Print

== Frequently Asked Questions ==

Q: How to use it?
A: After installing the plug-in on the page of orders two functions are added.
The first, a button for quickly printing of only one order in the corresponding column of the "Actions".
The second in "Bulk Actions","Print WB Labels", for printing both one and several orders marked with a checkbox.

== Screenshots ==

1. Location plugin in dashboard and buttons to send to print.

2. Set order status "printed".

3. Set order status "print failed".




== Changelog ==
= 1.3.1 =
* Add ship files time stamp with seconds

= 1.3.0 =
* Deleted header('Content-Length: ' . filesize($name));

= 1.2.9 =
* Added more monitoring in javascript

= 1.2.8 =
* Added json data to the service response in order to monitor the download process

= 1.2.7 =
* Fixed the bug of creating orderes.ship file
* Fixed the bug of updating the order's status

= 1.2.6 =
* Fixed pickup service printing bug

= 1.2.3 =
* File download sized added

= 1.2.4 =
* Change: In WB column tracking number is now a link to https://wwwapps.ups.com to check the status of the delivery.
* Add: Tip to button in order list.

= 1.2.3 =
* Fix : Fix multiple print bug.

= 1.2.2 =
* Core: Set 'NumberOfPackages' = 1.
* Image field: Add screenshots and  their description, replace banner.
*

= 1.2.1 =
* Release