<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     mod_sqlab
 * @category    upgrade
 * @copyright   2024 José Fernández Carmona <Jose.Fdez@alu.uclm.es>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once (__DIR__ . '/upgradelib.php');

/**
 * Execute mod_sqlab upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_sqlab_upgrade($oldversion)
{
    global $DB;

    $dbman = $DB->get_manager();

    // Not implemented, not currently used.

    return true;
}
