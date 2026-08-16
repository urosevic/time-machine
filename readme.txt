=== Time Machine ===
Contributors: urkekg, techwebux
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q6Q762MQ97XJ6
Tags: widget, posts, archive, time, past, timezone, ago, relative, date, years, months, days, hours, minutes, seconds
Requires at least: 3.9
Tested up to: 4.1
Stable tag: 0.4.1

Time Machine widget list articles published in past, relative to current date for specified offset of time, including all years of blogging (Ok, at least since 2002)

== Description ==

Time Machine is a simple plugin that grab `N` published articles from database (posts and/or pages) published on current day and/or offset of time in past years, and list them in widget.
User can set widget title, number of displayed articles and message printed when there is no public articles on current day or offset of time in past.

= Features =
* list only published articles (ignore Draft's)
* it's safe and will not list password protected articles until you strictly enable this option in widget settings
* option to exclude pages
* option to exclude articles published in current year
* option to hide widget if there is no articles in past (don't even displays `no articles` message)
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

Post suggestions, injoy in WordPress forum and donate.

== Changelog ==

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
1. Time Machine Widget Options
2. Time Machine widget in action
