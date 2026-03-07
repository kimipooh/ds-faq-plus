=== WP DS FAQ Plus ===
Contributors: kimipooh
Tags: faq, answer, question, documentation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPL v2  or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WP DS FAQ Plus is the plugin which was improved based on WP DS FAQ 1.3.3. 
 
== Description ==

 WP DS FAQ Plus is the plugin which was improved based on WP DS FAQ 1.3.3.  This plugin includes the fixed some issues (Quotation and Security, such as SQL Injection and CSRF. ) , Japanese translation, improvement of interface, and SSL Admin setting.

== Installation ==

Download: <a href="http://kitaney.jp/~kitani/tools/wordpress/wp-ds-faq-plus_en.html">WP DS FAQ Plus</a>

1. Extract the source compress file and upload "wp-ds-faq-plus" folder to the plugin folder (wp-content/plugins/) in Wordpress.
2. If you use "WP DS FAQ" plugin, please disactivate the plugin.
3. Enable "WP DS FAQ Plus" plugin.

= Usage =

* Please ignore the error message if the 413 characters output error appears when you install it. The error is due to the Japanese comments in PHP code. 
* The data format is the same as "WP DS FAQ" plugin. Therefore, you can switch between "WP DS FAQ" plugin and "WP DS FAQ Plus" plugin.
* Please see the change log in the following web site about the compatibility of old version and "WP DS FAQ". 

About the detail information, please see the following site.

- English: http://kitaney.jp/~kitani/tools/wordpress/wp-ds-faq-plus_en.html
- Japanese: http://kitaney.jp/~kitani/tools/wordpress/wp-ds-faq-plus_ja.html

== Frequently Asked Questions ==

= Note on Plugin Name =

This plugin uses the prefix "WP" to indicate compatibility with WordPress.
It is an independent project and is not affiliated with, endorsed by, or
officially associated with the WordPress project or the WordPress Foundation.

The Plugin Check tool may display a "trademarked_term" warning because the
plugin slug contains the term "wp". This usage follows the common convention
used by many plugins and does not imply official endorsement.


= Is it compatible between "WP DS FAQ Plus" and "WP DS FAQ"? =

 Yes, this plugin supports the compatibility with "WP DS FAQ" plugin.
When this plugin is deactivated and "WP DS FAQ" is activated, the special function of this plugin will be automatically ignored. And when this plugin is activated ("WP DS FAQ" is deactivated), the function will automatically work.

== Screenshots ==

1. View (with WP-PostRating plugin)
2. Editing Menu in Admin menu.
3. Setting Menu 1 (Permissions)
4. Setting Menu 2 (Safety Guard for a editor and Linkage from other plugins)
5. Edit Menu.

== Changelog ==
== 2.0.0 ==

Major refactoring and modernization of the plugin.

Security Improvements
* SQL queries rewritten using $wpdb->prepare(), $wpdb->insert(), $wpdb->update()
* Nonce verification added for admin and AJAX actions
* Sanitization added for user input
* Removed direct SQL execution patterns where possible

WordPress Compatibility
* Replaced deprecated date() usage with wp_date()
* Implemented WordPress timezone handling
* Improved compatibility with both timezone_string and gmt_offset settings

Database Handling
* Introduced dbDelta() for automatic table creation and schema updates
* Improved installation reliability and compatibility with future WordPress updates

Architecture Improvements
* ajax.php removed and functionality integrated into main plugin
* Plugin bootstrap separated from core implementation
* wp-ds-faq.php now acts as minimal bootstrap loader
* Main implementation moved to wp-ds-faq-plus.php

Plugin Check
* Plugin Check validation completed
* All errors resolved
* Only "trademarked_term" warning remains (see below)

Backward Compatibility
* Existing database structure preserved
* Existing timestamps preserved
* Existing installations continue to work without migration

= 1.5.2 =
* Fixed the problem of not applying to SVN properly.

= 1.5.1 =
* Changed from deprecated "get_settings" function to "get_option" function.
* Tested up WordPress 6.7.2 with PHP8.3.19 and PHP 8.4.5

= 1.5.0 =
* Tested up WordPress 6.1 with PHP8.2
* Tested up WordPress 6.5.2 with PHP8.3.6

= 1.4.9 =
* Fixed to use DateTime function instead of date function.
* Tested up WordPress 6.0

= 1.4.8 =
* Fixed the warning regarding an undefined variable.
* Tested up WordPress 5.8 and PHP 8.0.0

= 1.4.7 =
* Fixed the warning regarding an undefined index.
* Tested up WordPress 5.6.2 and PHP 8.0.0

= 1.4.6 =
* Improved the response of CSRF (Cross-Site Request Forgery) vulnerability for this plugin's settings.
* Tested up WordPress 5.3.2 and php 7.4.2.
* Tested up WordPress 5.6 and php 7.4.2.

= 1.4.5 =
- Improved the compatibility for PHP7 regartding __construct.
 
= 1.4.4 =
- Fixed the bug  regarding strip_tags function.

= 1.4.3 =
- Fixed the category issue.

= 1.4.2 =
- Improved the security programs. All tags are removed in title of a category and a faq. A answer can only use tags for "Post" allows by WordPress. 
- Tested up to WordPress 5.3.2 and PHP 7.3

= 1.4.1 =
- Tested up to WordPress 5.2.2 and PHP 7.3

= 1.4.0 =
- Tested up to WordPress 5.0 and PHP 7.2.

= 1.3.0 =
- Tested up to WordPress 4.9.
- Fixed the background color in the admin menu.

= 1.2.10 =
- Tested up to WordPress 4.8 and PHP 7.1

= 1.2.9 =
- Tested up to WordPress 4.7

= 1.2.8 =
- Tested up to WordPress 4.6

= 1.2.7 =
- Fixed Ajax error.
- Supported PHP7.
- Change mysql_real_escape_string function function to esc_sql function.
- Improved error message.

= 1.2.6 =
- Preparing to migrate the translation function to GlotPress.

= 1.2.5 =
- Tested up to WordPress 4.5
- Preparing to migrate the translation function to GlotPress.

= 1.2.4 =
- Tested up to WordPress 4.3

= 1.2.3 =
- Tested up to WordPress 4.1.1

= 1.2.2 =
- Tested up to WordPress 4.0

= 1.2.1 =
- Tested up to WordPress 3.9.1

= 1.2.0 =
- Tested up to WordPress 3.9

= 1.1.0 =
- Tested up to WordPress 3.8

= 1.0.18 =
- Tested up to WordPress 3.7.1.

= 1.0.17 =
- (Fixed) In case of using PHP version 5.2, "Call to undefined method DateTime::setTimestamp()" error was appeared.

= 1.0.16 =
- (Fixed) "Header and CSS Settings" in the"Admin Settings" cannot be saved or restored.

= 1.0.15 =
- (Fixed) Copyright Information

= 1.0.14 =
- (Fixed) In case of editing a FAQ data from front page, "Ajax error" was displayed when "Cancel" or "Save" button was pushed". Even if "Ajax error" was displayed, the data processing could be doing.  
- (Improvement) In case of using the table format in latest list function, the title of each item is displayed.

= 1.0.13 =
- (Fixed) Some Security vulnerabilities, but you don't need to worry because this fix is 
          for multi protection.
- (Fixed) Design of FAQ category title
- (Fixed) Improvement of TimeZone processing. (Establishment of "convert_timezone_data" function. By this processing, WordPress 3.x does not support the manual offsets (ex. UTF+9), but the plugin was supported in not only PHP5.2 or above but also PHP5.1 or earlier version.
- (Added) New Function of latest FAQ List.

= 1.0.12-1 =
- (Fixed) Cannot add new category.

= 1.0.12 =
- (Fixed) Some Security vulnerabilities
- (Fixed) Support of Full compatibility of WP DS FAQ plugin.

= 1.0.11-1 =
- (Fixed) When "Display Title" option in the admin menu check on, the setting could not be applied.

= 1.0.11 =
- Movement of the settings for header and CSS.

Establishment of admin menu for FAQ in the Settings.

- General Settings (Display Title)
- Permission (Administrative and Editing Permissions)
- Safety Precaution (Disable Delete button and so on)
- Linkage from other plugins (WP-PostRating)

= 1.0.10 =
- For new version of WP DS FAQ 1.3.3. 
- (Don't need to fix) Fix of escape processing.
- (Fixed) Fix and improvement of the check function for numeric characters.
- (Fixed) Removed "Delete" button in the editing area after push edit button and cancel button.
- By version [1.0.3] policy, you can only delete a FAQ article in the admin control page.
- (Fixed) Change the sort value in the FAQ sub menu of admin control page to "FAQ name".

= 1.0.9 =
- Added "Sort" function ("Sort Key" and "Order by") in each FAQ category.
- (BUG) Now, if you continually select 2 or more checkboxes, I'm sorry that only last selected checkbox will be changed.
- (Unsolved) If you select "Descending" in "Order By", the "Custom" selection won't  normally work because the ajax of "WP DS FAQ" plugin only works in only case of "Ascending". I'd like to fix it, but this issue is not solved in this version.

= 1.0.8 =
- "Under Construction" message changed to &lt;div class="dsfaq_plus_under_construction"&gt;Under Construction&lt;/div&gt;

= 1.0.7 =
- If there is not data in the category, "Under Construction" will be displayed.

= 1.0.6 =
- Added the submenu in main item. In the submenu, the FAQ categories are displayed.

= 1.0.5 =
- Fixed the ajax problem for Admin SSL setting.
- (Issue) The setting for Admin SSL setting was forcibly enabled even if FORCE_SSL_ADMIN is false setting. 

= 1.0.4 =
- Added CSRF (Cross Site Request Forgeries) security protection.

= 1.0.3 =
- Removed "Delete" button in the editing area.
- Added the main item in the side menu (Multi-language).

= 1.0.2 =
- Fixed the ajax problem for Admin SSL setting (FORCE_SSL_ADMIN).

= 1.0.1 =
- Moved [Delete question] button to the right edge.
- Added [Last modified Date] in the right of [Edit] button.
- Added the sort the list of questions by last modified date.
- If there is the "()" in the head of question title, the view moves to the category item.
- Adjusted the size in [td] tag.

= 1.0.0 =
- Fixed SQL Injection problem.
- Fixed Escape function for quotation mark
- View of Source code with [<a href="http://wordpress.org/extend/plugins/wp-syntax/">WP Syntax</a>] plugin. 
- Japanese Translation
- Fixed [Error] message from Russian to English.

== Upgrade Notice ==

= 1.0.12 =

This version cannot add the category. If you use this version, please upgrade to 1.0.12-1 or later


== 2.0.0 ==
Major refactoring and modernization of the plugin.

Security Improvements
* SQL queries rewritten using $wpdb->prepare(), $wpdb->insert(), $wpdb->update()
* Nonce verification added for admin and AJAX actions
* Sanitization added for user input

WordPress Compatibility
* Replaced deprecated date() usage with wp_date()
* Implemented WordPress timezone handling

Database Handling
* Introduced dbDelta() for automatic table creation and schema updates

Architecture Improvements
* ajax.php removed
* Bootstrap loader separated from main implementation

Plugin Check
* Plugin Check validation completed
* All errors resolved except "trademarked_term"


Note on Plugin Name

WP DS FAQ Plus is an independent plugin for WordPress and is not affiliated with or endorsed by the WordPress project.
