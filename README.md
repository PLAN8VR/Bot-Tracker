=== Good Bot / Bad Bot - Keep track of what bots crawl your wordpress site ===
Contributors: PLAN8
Tags: bots, security, firewall
Requires at least: 5.7
Tested up to: 6.4.3
Stable tag: 0.0.2
Requires PHP: 7.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

A simple Wordpress addon that stores an sql database list of visitors to the site that identify as "Bot", "spider" or crawler etc in order to help keep an eye out for "Bad bots" scraping your site. 

The addon creates a basic admin page (accessed on the sidepanel in wp-admin) that lists all the visitors considered bots for the last 30 days. 

The addon automatically clears out any database entries older than 30 days.

To Install, Download "as zip", and install as usual with wordpress.

Once installed, the Bot Tracker option appears on the left side along with all the other wordpress options. In the tab you will find a main list page called "Bot Tracker", as well as a settings option.

In the settings, you will find a text entry box where you can enter in any bots that you consider safe "Googlebot" for example. Once saved, any entries in yur good bot list will not be highlighted in the overall list.
