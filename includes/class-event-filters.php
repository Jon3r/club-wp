<?php
/**
 * ClubWorx event filtering helpers (free-trial / exclusions).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clubworx_Event_Filters {

    /**
     * Default class-name substrings excluded from public booking lists.
     *
     * @return string[]
     */
    public static function default_excluded_class_names() {
        return array('Private Training');
    }

    /**
     * Normalize excluded class name patterns from location settings.
     *
     * @param array<string,mixed>|null $loc
     * @return string[]
     */
    public static function excluded_class_names_from_location($loc) {
        $raw = '';
        if (is_array($loc) && isset($loc['excluded_class_names'])) {
            $raw = $loc['excluded_class_names'];
        }

        if (is_array($raw)) {
            $lines = $raw;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
        }

        if (!is_array($lines)) {
            $lines = array();
        }

        $out = array();
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        // Empty setting means use defaults; explicit blank line-only means none.
        if ($out === array() && (!is_array($loc) || !array_key_exists('excluded_class_names', $loc) || $loc['excluded_class_names'] === null)) {
            return self::default_excluded_class_names();
        }

        return $out;
    }

    /**
     * Whether an event name matches any exclusion pattern (case-insensitive substring).
     *
     * @param string   $class_name
     * @param string[] $patterns
     * @return bool
     */
    public static function class_name_is_excluded($class_name, $patterns) {
        $name = strtolower(trim((string) $class_name));
        if ($name === '' || !is_array($patterns) || empty($patterns)) {
            return false;
        }
        foreach ($patterns as $pattern) {
            $needle = strtolower(trim((string) $pattern));
            if ($needle !== '' && strpos($name, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Field keys that indicate prospect / free-trial capacity on a ClubWorx event.
     *
     * @return array{available:string[],max:string[],booked:string[]}
     */
    public static function prospect_field_map() {
        return array(
            'available' => array(
                'available_prospect_spaces',
                'prospect_spaces_available',
                'remaining_prospect_spaces',
                'prospects_available',
                'available_prospects',
                'free_prospect_spaces',
                'prospect_spaces_remaining',
            ),
            'max' => array(
                'max_prospects',
                'event_max_prospects',
                'prospect_spaces',
                'prospect_space',
                'max_prospect_spaces',
                'prospects_max',
            ),
            'booked' => array(
                'prospects_booked',
                'booked_prospects',
                'prospect_bookings',
                'prospects_count',
                'prospect_attendees',
            ),
        );
    }

    /**
     * Whether the event payload includes any prospect-capacity fields.
     *
     * @param array<string,mixed> $event
     * @return bool
     */
    public static function event_has_prospect_capacity_fields($event) {
        if (!is_array($event)) {
            return false;
        }

        $map = self::prospect_field_map();
        foreach (array_merge($map['available'], $map['max'], $map['booked']) as $key) {
            if (array_key_exists($key, $event)) {
                return true;
            }
        }

        if (isset($event['prospects']) && is_array($event['prospects'])) {
            return true;
        }

        foreach (array('allows_prospects', 'allow_prospects', 'free_trial', 'is_free_trial') as $flag) {
            if (array_key_exists($flag, $event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any event in the list exposes prospect-capacity fields.
     *
     * @param array<int,mixed> $events
     * @return bool
     */
    public static function events_expose_prospect_fields($events) {
        if (!is_array($events)) {
            return false;
        }
        foreach ($events as $event) {
            if (is_array($event) && self::event_has_prospect_capacity_fields($event)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the event currently has free-trial (prospect) space.
     *
     * @param array<string,mixed> $event
     * @return bool
     */
    public static function event_has_free_trial_space($event) {
        if (!is_array($event)) {
            return false;
        }

        $map = self::prospect_field_map();

        foreach ($map['available'] as $key) {
            if (isset($event[$key]) && is_numeric($event[$key])) {
                return (int) $event[$key] > 0;
            }
        }

        if (isset($event['prospects']) && is_array($event['prospects'])) {
            $p = $event['prospects'];
            if (isset($p['available']) && is_numeric($p['available'])) {
                return (int) $p['available'] > 0;
            }
            if (isset($p['remaining']) && is_numeric($p['remaining'])) {
                return (int) $p['remaining'] > 0;
            }
            if (isset($p['max']) && is_numeric($p['max'])) {
                $booked = isset($p['booked']) && is_numeric($p['booked']) ? (int) $p['booked'] : 0;
                return ((int) $p['max'] - $booked) > 0;
            }
        }

        $max = null;
        foreach ($map['max'] as $key) {
            if (isset($event[$key]) && is_numeric($event[$key])) {
                $max = (int) $event[$key];
                break;
            }
        }

        if ($max !== null) {
            $booked = 0;
            foreach ($map['booked'] as $key) {
                if (isset($event[$key]) && is_numeric($event[$key])) {
                    $booked = (int) $event[$key];
                    break;
                }
            }
            return ($max - $booked) > 0;
        }

        foreach (array('allows_prospects', 'allow_prospects', 'free_trial', 'is_free_trial') as $flag) {
            if (array_key_exists($flag, $event)) {
                return (bool) filter_var($event[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return false;
    }

    /**
     * Decide if an event should appear on the booking form class list.
     *
     * @param array<string,mixed> $event
     * @param string[]            $exclude_patterns
     * @param bool                $free_trial_only
     * @param bool                $prospect_fields_present Whether the API response included prospect fields on any event.
     * @return bool
     */
    public static function event_allowed_for_booking($event, $exclude_patterns, $free_trial_only, $prospect_fields_present) {
        if (!is_array($event)) {
            return false;
        }

        $name = '';
        if (isset($event['event_name'])) {
            $name = (string) $event['event_name'];
        } elseif (isset($event['name'])) {
            $name = (string) $event['name'];
        } elseif (isset($event['class_name'])) {
            $name = (string) $event['class_name'];
        } elseif (isset($event['title'])) {
            $name = (string) $event['title'];
        }

        if (self::class_name_is_excluded($name, $exclude_patterns)) {
            return false;
        }

        if ($free_trial_only && $prospect_fields_present && !self::event_has_free_trial_space($event)) {
            return false;
        }

        return true;
    }
}
