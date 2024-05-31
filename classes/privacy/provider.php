<?php

namespace mod_sqlab\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\null_provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the SQLab plugin, implementing null_provider and user_preference_provider.
 * 
 * The SQLab plugin does not store any personal data directly. 
 * However, it interfaces with user preferences and provides utilities to manage these settings.
 */
class provider implements null_provider, user_preference_provider {

    /**
     * Returns a string explanation that this plugin does not store any personal data.
     *
     * @return string A language string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }

    /**
     * Returns metadata about the personal data stored by the SQLab plugin.
     *
     * @param collection $items A collection used to add metadata.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $items): collection {
        // SQLab stores user preferences related to user interface settings.
        $items->add_user_preference('mod_sqlab_user_setting', 'privacy:metadata:preference:description');

        return $items;
    }

    /**
     * Exports user preferences controlled by the SQLab plugin.
     *
     * @param int $userid The user ID.
     */
    public static function export_user_preferences(int $userid) {
        // Fetch user's specific preferences for SQLab.
        $preference_name = 'mod_sqlab_user_setting';
        $preference_value = get_user_preferences($preference_name, null, $userid);

        if ($preference_value !== null) {
            writer::export_user_preference(
                'mod_sqlab',
                $preference_name,
                $preference_value,
                get_string('privacy:metadata:preference:description', 'sqlab')
            );
        }
    }
}
