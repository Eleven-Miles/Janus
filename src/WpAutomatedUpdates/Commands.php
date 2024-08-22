<?php
namespace ElevenMiles\WpAutomatedUpdates;

use WP_CLI;

/**
 * Commands class.
 * 
 * 
 * @package    ElevenMiles\WpAutomatedUpdates
 * @subpackage ElevenMiles\WpAutomatedUpdates\Commands
 * @since      1.0.0
 */
class Commands {
    function __construct() {
        $this->registerCustomCommands();
    }

    function registerCustomCommands () {
        WP_CLI::add_command( 'update',  'ElevenMiles\WpAutomatedUpdates\Update');
    }
}