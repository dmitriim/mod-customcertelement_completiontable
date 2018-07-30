<?php


defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Max number of date ranges.
 *
 */
$settings->add(new admin_setting_configselect(
    'customcertelement_completiontable/maxranges',
    get_string('maxranges', 'customcertelement_completiontable')
));

