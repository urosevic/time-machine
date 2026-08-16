<?php
/**
 * Plugin Name: Time Machine
 * Plugin URI: https://urosevic.net/wordpress/plugins/time-machine/
 * Description: Widget to list articles published in past, relative to current date by specified time offset.
 * Version: 26.8.0
 * Author: Aleksandar Urošević
 * Author URI: https://urosevic.net/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Requires at least: 5.3
 * Text Domain: time-machine
 * Tested up to: 7.1
 * Requires PHP: 7.4
 *
 * @package TimeMachine
 */

/**
	Time Machine list articles from past on WordPress blog's
	Copyright (C) 2009-2026 Aleksandar Urošević <urke.kg@gmail.com>

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

defined( 'ABSPATH' ) || exit;

define( 'TIME_MACHINE_VERSION', '26.8.0' );
define( 'TIME_MACHINE_DB_VERSION', 1 );
define( 'TIME_MACHINE_FILE', __FILE__ );
define( 'TIME_MACHINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIME_MACHINE_URL', plugin_dir_url( __FILE__ ) );

require_once TIME_MACHINE_DIR . 'classes/class-autoloader.php';

\TechWebUX\TimeMachine\Autoloader::register();

\TechWebUX\TimeMachine\Plugin::instance();
