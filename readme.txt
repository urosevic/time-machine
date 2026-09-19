=== Time Machine ===
Contributors: urkekg, techwebux
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q6Q762MQ97XJ6
Tags: archive, history, on this day, widget, block
Requires at least: 5.3
Tested up to: 7.1
Stable tag: 26.9.0
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Time Machine widget and block display a list of articles published in on this day in past, including offset in days, weeks and months.

== Description ==

Time Machine is a simple plugin that grab `N` published articles from database (posts and/or pages) published on current day and/or offset of time in past years, and list them in widget or block.
User can set widget title, number of displayed articles and message shown when there is no public articles in past.

= Features =
* provides shortcode, legacy widget, FSE widget and Gutenberg block
* list only published articles (ignore Draft's)
* it's safe and will not list password protected articles until you strictly enable this option in shortcode/widget/block settings
* option to exclude pages
* option to exclude articles published in current year
* option to hide widget/block if there is no articles in past (don't even displays `no articles` message)
* configurable widget title, number of displayed articles, message when there is no articles, and optional display comments number
* use theme based CSS Stylesheet

== Installation ==
= Manual =
1. Upload the entire `time-machine` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the `Plugins` menu in WordPress.
1. Insert new `Time Machine` widget on `Appearance` → `Widgets`.
1. Configure `Time Machine` options.

= Automatic =
1. Go to `Plugins` → `Add New` and search for `time machine`.
1. Click on `Install Now` link bellow `Time Machine` search result and aswer `Yes` on popup question.
1. If you need enter FTP parameters for your host and click on `Proceed`.
1. Activate the plugin through the `Plugins` menu in WordPress.
1. Insert new `Time Machine` widget on `Appearance` → `Widgets`.
1. Configure `Time Machine` options.

== Frequently Asked Questions ==

= Why name Time Machine? =

I like effect that produce Time Machine - traveling trough time. This plugin does exactly that, but with fixed month and day constant.

= How I can help? =

Revire and rate plugin, submit suggestions, contribute on WordPress forum.

= Does Time Machine work with full page cache plugins (WP Rocket, WP Fastest Cache, W3 Total Cache...)? =

Yes. Time Machine's list is refetched from a small REST endpoint right after the page finishes loading, so the visible content stays current even when the whole page has been cached for longer than a day. The cached markup already on the page stays visible until (and unless) that request succeeds, so nothing is ever blank while it refreshes, and the page cache itself is never touched or purged. If you prefer to disable this and rely only on the page cache's own expiry, add `define( 'TIME_MACHINE_DISABLE_REFRESH', true );` to `wp-config.php`.

= How do I turn the front end auto-refresh off? =

Add this to `wp-config.php`:

`define( 'TIME_MACHINE_DISABLE_REFRESH', true );`

No `data-time-machine-*` attributes are printed after that, the refresh script is never enqueued, and no REST request is ever made. Lists render once, on the server. On a page held in a full page cache for longer than a day, the list can then be a day or more behind.

= The browser console shows a 401 on `/wp-json/time-machine/v1/list`. What is wrong? =

A security plugin or snippet on your site closes the whole REST API to signed out visitors, through the `rest_authentication_errors` filter. That filter runs before any route can state that it is public, so Time Machine cannot exempt its own route from it, however read only that route is.

Nothing is broken for your visitors. The list is rendered on the server and stays on screen; only the refresh described above is skipped, and the failed request is logged in the console. Time Machine checks this once a day and tells you in `Tools` -> `Site Health`, which spells out the two options below.

**Option 1: switch the refresh off.** Add this to `wp-config.php`:

`define( 'TIME_MACHINE_DISABLE_REFRESH', true );`

No request is made after that, so the console stays clean. Lists render once, on the server, exactly as they did before this feature existed. On a page held in a full page cache for longer than a day, the list can then be a day or more behind.

**Option 2: let this one route through.** It is read only and returns the same published titles, excerpts and links the page already shows to the same visitor, so letting it through gives nothing away. Add this to a must-use plugin or to your theme's `functions.php`:

```
add_filter(
	'rest_authentication_errors',
	function ( $result ) {

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
			? ltrim( $GLOBALS['wp']->query_vars['rest_route'], '/' )
			: '';

		if ( 0 === strpos( $route, 'time-machine/v1/' ) ) {
			return null;
		}

		return $result;
	},
	99
);
```

It runs after the restriction and before the core cookie check, and clears the error for the `time-machine/v1` namespace only. Every other route keeps the restriction exactly as it is.

== Changelog ==

= 26.9.0 (2026-09-19) =
* Test: WordPress 7.1
* Refactor: Full plugin refactored
* Add: Support for Block Editor (FSE Widget and Gutenberg Block)
* Add: Excerpt is now generated from post content when no manual excerpt is set, same fallback WordPress core uses for `the_excerpt()`
* Add: Password protected posts with no manual excerpt show a notice instead of a generated excerpt, until the current visitor unlocks that post
* Add: Front-end auto-refresh of the post list via a REST endpoint, so pages served from a full page cache plugin still show current content
* Add: Daily check of whether a site wide REST API restriction blocks the front-end refresh route, reported in a dismissible admin notice and in a Site Health test, with the snippet needed to allow that one read only route
* Add: `TIME_MACHINE_DISABLE_REFRESH` constant to switch the front-end refresh off site wide from `wp-config.php`
* Change: Remove `hours` offset
* Change: Rename rangetype -> direction and rangenum -> offset
* Change: Remove Excerpt before/after HTML tags and wrap excerpt into span with class excerpt
* Change: Offset query results are now sorted newest year first
* Improve: Offset query now compares whole days instead of the exact time, and results are cached (object cache, or transient when no persistent object cache is active)
* Improve: Offset query loop now starts from the oldest published post's year (cached for a month) instead of a hardcoded 2002
* Improve: Cache is invalidated automatically whenever a post or page is saved, instead of only relying on the daily/monthly expiration
* Improve: Front-end script is enqueued minified, and served as readable source only while `SCRIPT_DEBUG` is on

= 0.4.1 (2014-12-20) =
* Improve: multi instance widget
* Improve: rewritten to OOP and optimized code
* Add: rel="nofollow" to post links, to prevent reindexing old posts and and affect page rank
* Add: uninstall procedure to remove widget settings
* Test on WordPress 4.1

= 0.0.6 (2011-04-02) =
* Fixed timezone bug. Now Time Machine instead of GMT use WordPres timezone for time offset calculations.

= 0.0.5.5svn (2009-08-03) =
* Added option to display excerpt, with feature to short and insert XHTML code before and after excerpt
* Added date offset (+/- N hours/days/weeks/months)
* Added offset type (before to current date, before to after current date, current date to after)

= 0.0.5.4 (2009-07-13) =
* fixed bug for feature added in 0.0.5.3 (reported by Rarst)
* Added Belorussian language (submited by Marcis Gasuns)

= 0.0.5.3 (2009-07-12) =
* Added option do hide widget if there is no posts in past (sugessted by Rarst)
* Added Simplified Chinese language (submited by Leslie Yeh)
* Updated Serbian and Italian language
* Fixed post links if blog is not in root with permalink (thanks Leslie)

= 0.0.5.2 (2009-06-19) =
* Added Italian localisation (submited by Caporale Reyes)

= 0.0.5.1 (2009-03-08) =
* Fixed broken 0.0.5 ('Number of posts' above links)

= 0.0.5 (2009-03-08) =
* compare GMT date, not local date on host
* added description to displayed number of comments

= 0.0.4 (2009-02-28) =
* Added option to display number of comments

= 0.0.3 (2009-02-14) =
* Fixed links to articles when is active non-Home pages
* Added option to exclude WordPress Pages from listing
* Added option to exclude articles published in current year from listing
* Updated Serbian translation

= 0.0.2 (2009-02-14) =
* Fixed SQL query to work as expected
* Added option to display password protected posts in list

= 0.0.1 (2009-02-13) =
* Initial release

== Screenshots ==
1. Time Machine Widget Options anr Preview
