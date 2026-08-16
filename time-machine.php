<?php
defined( 'ABSPATH' ) OR exit;
/*
Plugin Name: Time Machine
Version: 0.4.1
Plugin URI: http://blog.urosevic.net/wordpress/time-machine/
Author: Aleksandar Urošević
Author URI: http://urosevic.net/
Description: Widget to list articles published in past, relative to current date by specified time offset.
*/
/*

    Time Machine list articles from past on WordPress blog's
    Copyright (C) 2009-2015 Aleksandar Urošević <urke.kg@gmail.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

 */

if ( ! class_exists('TimeMachine') ) {

class TimeMachine {

	private $defaults;

	function __construct() {

		$this->defaults = self::defaults();

		require_once('inc/widget.php');

	} // eom __construct

	public static function defaults() {

		$defaults = array(
			"title"              => __('Time Machine', 'tm'),
			"message"            => __('No articles published on same day in past', 'tm'),
			"posts"              => 10,
			"showifno"           => false,
			"private"            => false,
			"exclude_pages"      => false,
			"exclude_current"    => false,
			"display_commentnum" => false,
			"range"              => "none",
			"rangenum"           => "1",
			"rangetype"          => "both",
			"excerpt"            => false,
			"excerpt_cut"        => false,
			"excerpt_length"     => 150,
			"excerpt_before"     => "<br /><small><em>",
			"excerpt_after"      => "</em></small>"
		);

		$options = wp_parse_args(get_option('time_machine'), $defaults);
		return $options;

	} // eom defaults()

} // eo class TimeMachine

} // eo class check

new TimeMachine;
