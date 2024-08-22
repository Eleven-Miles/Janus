<?php

namespace ElevenMiles\WpAutomatedUpdates;

use WP_CLI;

/**
 * PremiumPlugins class.
 * 
 * 
 * @package    ElevenMiles\WpAutomatedUpdates
 * @subpackage ElevenMiles\WpAutomatedUpdates\PremiumPlugins
 * @since      1.0.0
 */
class PremiumPlugins
{
    /**
     *
     * Update Advanced Custom Fields Pro
     *
     * 
     * @return string|null
     *
     */
    public static function AdvancedCustomFieldsPro($currentVersion, $ticket, $date)
    {
        $key = getenv('ACFPRO_KEY');

        if (!$key) {
            WP_CLI::error('Missing Advanced Custom Fields Pro license key.');
            return;
        }

        Utility::runCommand("plugin install https://connect.advancedcustomfields.com/index.php?p=pro&a=download&k={$key}&#8221 --force");
        return Utility::afterUpdatePlugin('advanced-custom-fields-pro', $currentVersion, $ticket, $date);
    }

    /**
     *
     * Update Gravity Forms
     *
     * 
     * @return string|null
     *
     */
    public static function GravityForms($name, $currentVersion, $ticket, $date)
    {
        if (!Utility::getPluginVersion('gravityformscli')) {
            WP_CLI::error('Gravity Forms CLI is not installed.');
            return;
        }

        $key = getenv('GF_KEY');

        if (!$key) {
            WP_CLI::error('Missing Gravity Forms license key.');
            return;
        }

        $command = "gf update {$name} --key={$key}";

        if ($name === 'gravityforms') $command = "gf update --key={$key}";

        Utility::runCommand($command);
        return Utility::afterUpdatePlugin('gravityforms', $currentVersion, $ticket, $date);
    }
}
