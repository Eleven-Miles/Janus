<?php

namespace ElevenMiles\WpAutomatedUpdates;

use WP_CLI;
use ElevenMiles\WpAutomatedUpdates\Utility;

/**
 * Update class.
 * 
 * 
 * @package    ElevenMiles\WpAutomatedUpdates
 * @subpackage ElevenMiles\WpAutomatedUpdates\Update
 * @since      1.0.0
 */
class Update
{
    public $ticket;
    public $date;

    /**
     *
     * Usage: wp update all --ticket=EMS-1234 --date=01-01-2021
     *
     * 
     * @return string|null
     *
     */
    public function all($args, $assoc_args)
    {
        if (!isset($assoc_args['ticket'])) WP_CLI::error('Please add "--ticket=EMS-1234" to your command');
        if (!isset($assoc_args['date'])) $assoc_args['date'] = date("d-m-Y");

        $this->ticket = $assoc_args['ticket'];
        $this->date = $assoc_args['date'];

        unset($assoc_args['ticket']);
        unset($assoc_args['date']);

        self::wordpress($args, array_merge($assoc_args, ['force' => true]));
        self::plugins($args, array_merge($assoc_args, ['force' => true]));
        // self::themes($args, array_merge($assoc_args, ['force' => true]));
        // self::translations($args, array_merge($assoc_args, ['force' => true]));
    }

    /**
     *
     * Usage: wp update wordpress --ticket=EMS-1234 --date=01-01-2021
     *
     * 
     * @return string|null
     *
     */
    public function wordpress($args, $assoc_args)
    {
        global $wp_version;
        $nextVersion = Utility::getLatestWordpressVersion();

        if (!$nextVersion) {
            WP_CLI::log('No new update for Wordpress at this time');
            return;
        }

        $force = $assoc_args['force'] ?? false;

        WP_CLI::log(sprintf('Update available, Wordpress will be update from %s to %s', $wp_version, $nextVersion));
        if (!$force) WP_CLI::confirm('Ok to continue?', $assoc_args);

        Utility::updateWordpress(array_merge($assoc_args, ['version' => $nextVersion]), $this->ticket, $this->date);
    }

    /**
     *
     * Usage: wp update plugins --ticket=EMS-1234 --date=01-01-2021
     *
     * 
     * @return string|null
     *
     */
    public function plugins($args, $assoc_args)
    {
        $pluginsToUpdate = Utility::getPluginsToUpdate();

        if (count($pluginsToUpdate) === 0) {
            WP_CLI::log('No plugins to update at this time');
            return;
        }

        $force = $assoc_args['force'] ?? false;

        WP_CLI::log(sprintf('%d plugins to update', (int)count($pluginsToUpdate)));
        if (!$force) WP_CLI::confirm('Ok to continue?', $assoc_args);

        Utility::updatePlugins($pluginsToUpdate, $this->ticket, $this->date);
    }

    /**
     *
     * Usage: wp update themes --ticket=EMS-1234 --date=01-01-2021
     *
     * 
     * @return string|null
     *
     */
    public function themes()
    {
        WP_CLI::log(sprintf('Theme updates are coming soon'));
    }

    /**
     *
     * Usage: wp update translations --ticket=EMS-1234 --date=01-01-2021
     *
     * 
     * @return string|null
     *
     */
    public function translations()
    {
        WP_CLI::log(sprintf('Translations updates are coming soon'));
    }
}
