<?php
/**
 * Leapfrog Performance > Bloat > "Password change email to the administrator".
 * WordPress only defines this function if no plugin has, so an empty one here
 * stops that email. Loaded only while the setting is on.
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('wp_password_change_notification')) {
    function wp_password_change_notification($user)
    {
    }
}
