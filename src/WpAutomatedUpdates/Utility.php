<?php

namespace ElevenMiles\WpAutomatedUpdates;

use WP_CLI;
use WP_CLI\Utils;
use Composer\Semver\Comparator;
use ElevenMiles\WpAutomatedUpdates\PremiumPlugins;

/**
 * Utility class.
 * 
 * 
 * @package    ElevenMiles\WpAutomatedUpdates
 * @subpackage ElevenMiles\WpAutomatedUpdates\Utility
 * @since      1.0.0
 */
class Utility
{
    /**
     *
     * Get the latest version of WordPress available.
     *
     * 
     * @return string|false The version number, or false if the check failed or is still in progress.
     *
     */

    public static function getLatestWordpressVersion()
    {
        \wp_version_check();

        $from_api = \get_site_transient('update_core');

        if (!$from_api) {
            return [];
        }

        $compare_version = str_replace('-src', '', $GLOBALS['wp_version']);

        $updates = [
            'major' => false,
            'minor' => false,
        ];

        foreach ($from_api->updates as $offer) {

            $update_type = Utils\get_named_sem_ver($offer->version, $compare_version);
            if (!$update_type) continue;

            // WordPress follow its own versioning which is roughly equivalent to semver
            if ('minor' === $update_type) {
                $update_type = 'major';
            } elseif ('patch' === $update_type) {
                $update_type = 'minor';
            }

            if (!empty($updates[$update_type]) && !Comparator::greaterThan($offer->version, $updates[$update_type]['version'])) continue;

            $updates[$update_type] = ['version' => $offer->version];
        }

        foreach ($updates as $type => $value) if (empty($value)) unset($updates[$type]);

        $updates = array_reverse(array_values($updates));

        return isset($updates[0]['version']) ? $updates[0]['version'] : false;
    }

    /**
     *
     * Get a list of plugins.
     *
     * 
     * @return array|false The list of plugins, or false if the check failed or is still in progress.
     *
     */
    public static function getPlugins()
    {
        $options = [
            'return'     => true,
            'parse'      => 'json',
            'launch'     => false,
            'exit_error' => true,
        ];

        return WP_CLI::runcommand('plugin list --format=json', $options);
    }

    /**
     *
     * Get a list of plugins that need to be updated.
     *
     * 
     * @return array|false The list of plugins, or false if the check failed or is still in progress.
     *
     */
    public static function getPluginsToUpdate()
    {
        return [...array_filter(self::getPlugins(), fn ($plugin) => $plugin['update'] == 'available')];
    }

    /**
     *
     * Get the version of a plugin.
     *
     * 
     * @return string|false The version number, or false if the check failed or is still in progress.
     *
     */
    public static function getPluginVersion($name)
    {
        $plugins = array_filter(self::getPlugins(), fn ($plugin) => $plugin['name'] === $name);

        if (count($plugins) === 1) return $plugins[array_key_first($plugins)]['version'];

        return false;
    }

    /**
     *
     * Get the number of things that need to be updated.
     *
     * 
     * @return int The number of things that need to be updated.
     *
     */
    public static function getCountOfThingsToUpdate()
    {
        global $wp_version;
        $wordpressNeedsUpdate = $wp_version !== self::getLatestWordpressVersion() ? 1 : 0;
        $pluginsToUpdate = count(self::getPluginsToUpdate());

        return $wordpressNeedsUpdate + $pluginsToUpdate;
    }

    /**
     *
     * Update WordPress.
     *
     * 
     * @return void
     *
     */
    public static function updateWordpress($assoc_args = [], $ticket, $date)
    {
        $args = join(' ', array_reduce(array_keys($assoc_args), function ($output, $key) use ($assoc_args) {
            array_push($output, "--{$key}={$assoc_args[$key]}");

            return $output;
        }, []));

        WP_CLI::runcommand(sprintf('core update %s', $args));

        $wp_details = self::getWordpressDetails();

        self::commitToGit('Wordpress', $assoc_args['version'], $wp_details['wp_version'], $ticket, $date);
    }

    /**
     *
     * Update plugins.
     *
     * 
     * @return void
     *
     */
    public static function updatePlugins($updateablePlugins, $ticket, $date)
    {
        $premiumPlugins = [
            'advanced-custom-fields-pro',
            'gravityforms',
        ];

        $premium = array_filter($updateablePlugins, fn ($plugin) => (in_array($plugin['name'], $premiumPlugins) || str_contains($plugin['name'], 'gravityforms')));
        $free = array_filter($updateablePlugins, fn ($plugin) => !(in_array($plugin['name'], $premiumPlugins) || str_contains($plugin['name'], 'gravityforms')));

        $premiumUpdated = self::updatePremiumPlugins($premium, $ticket, $date);
        $freeUpdated = self::updateFreePlugins($free, $ticket, $date);

        Utility::logTableOfPluginsUpdated(array_merge($freeUpdated, $premiumUpdated));
    }

    /**
     *
     * Update Free plugins.
     *
     * 
     * @return void
     *
     */
    public static function updateFreePlugins($plugins, $ticket, $date)
    {
        $updated = [];

        foreach ($plugins as $plugin) {
            $updated[] = Utility::updatePlugin($plugin['name'], $plugin['version'], $ticket, $date);
        }
        
        return $updated;
    }

    /**
     *
     * Update Premium plugins.
     *
     * 
     * @return void
     *
     */
    public static function updatePremiumPlugins($plugins, $ticket, $date)
    {
        $updated = [];
        
        foreach ($plugins as $plugin) {
            switch ($plugin['name']) {
                case 'advanced-custom-fields-pro':
                    $updated[] = PremiumPlugins::AdvancedCustomFieldsPro($plugin['version'], $ticket, $date);
                    break;
                case 'gravityforms':
                    $updated[] = PremiumPlugins::GravityForms($plugin['name'], $plugin['version'], $ticket, $date);
                    break;
            }
        }

        return $updated;
    }

    /**
     *
     * Update an individual plugin.
     *
     * 
     * @return void
     *
     */
    public static function updatePlugin($name, $currentVersion, $ticket, $date)
    {
        Utility::runCommand("plugin update {$name}");
        return Utility::afterUpdatePlugin($name, $currentVersion, $ticket, $date);
    }

    /**
     *
     * Log to terminal after updating a plugin and commit changes to git..
     *
     * 
     * @return void
     *
     */
    public static function afterUpdatePlugin($name, $currentVersion, $ticket, $date)
    {
        $newVersion = Utility::getPluginVersion($name);

        if ($currentVersion === $newVersion) {
            WP_CLI::warning("{$name} not updated. This is probably due to the plugin files being private. At this time you will have to manually update this plugin.");
        } else {
            WP_CLI::success("{$name} updated ({$currentVersion} -> {$newVersion}).");
            Utility::commitToGit($name, $currentVersion, $newVersion, $ticket, $date);
        }

        return [
            'name' => $name,
            'old' => $currentVersion,
            'new' => $newVersion,
        ];
    }

    /**
     *
     * Update themes.
     *
     * 
     * @return void
     *
     */
    public static function updateThemes()
    {
    }

    /**
     *
     * Update translations.
     *
     * 
     * @return void
     *
     */
    public static function updateTranslations($ticket, $date)
    {
        Utility::runCommand('language core update');
        Utility::commitToGit('Translations', 0, 1, $ticket, $date);
    }

    /**
     *
     * Commit changes to git.
     *
     * 
     * @return void
     *
     */
    public static function commitToGit($name, $version, $newVersion, $ticket, $date)
    {
        $emoji = false;

        switch (true) {
            case $version === null:
                $emoji = "heavy_plus_sign";
                break;
            case $newVersion === null:
                $emoji = "heavy_minus_sign";
                break;
            case $version === $newVersion:
                $emoji = "arrow_up";
                break;
            case $version > $newVersion:
                $emoji = "arrow_up";
                break;
            case $version < $newVersion:
                $emoji = "arrow_down";
                break;
        }

        $emoji = $emoji ?  ':' . $emoji . ': ' : '';
        $versionText = $version == 0 && $newVersion == 1 ? "{$version} -> {$newVersion} " : "";

        shell_exec("git commit -am '{$ticket}: :package: {$emoji}{$name} {$versionText}({$date})'");
        WP_CLI::success("{$ticket}: :package: {$emoji}{$name} {$versionText}({$date})");
    }

    /**
     *
     * Get WordPress details.
     *
     * 
     * @return array
     *
     */
    public static function getWordpressDetails($abspath = ABSPATH)
    {
        $versions_path = $abspath . 'wp-includes/version.php';

        if (!is_readable($versions_path)) {
            WP_CLI::error(
                "This does not seem to be a WordPress installation.\n" .
                    'Pass --path=`path/to/wordpress` or run `wp core download`.'
            );
        }

        $version_content = file_get_contents($versions_path, null, null, 6, 2048);

        $vars   = ['wp_version', 'wp_db_version', 'tinymce_version', 'wp_local_package'];
        $result = [];

        foreach ($vars as $var_name) {
            $result[$var_name] = self::findVar($var_name, $version_content);
        }

        return $result;
    }

    /**
     *
     * Find a variable in a PHP code block.
     *
     * 
     * @return string|null
     *
     */
    public static function findVar($var_name, $code)
    {
        $start = strpos($code, '$' . $var_name . ' = ');

        if (!$start) {
            return null;
        }

        $start = $start + strlen($var_name) + 3;
        $end   = strpos($code, ';', $start);

        $value = substr($code, $start, $end - $start);

        return trim($value, " '");
    }

    /**
     *
     * Run a WP CLI command.
     *
     * 
     * @return string|null
     *
     */
    public static function runCommand($command)
    {
        $options = [
            'return'     => true,
            'parse'      => 'json',
            'launch'     => false,
            'exit_error' => false
        ];

        return WP_CLI::runcommand($command, $options);
    }

    public static function logTableOfPluginsUpdated($plugins = [])
    {
        $updatedPlugins = array_filter($plugins, fn ($plugin) => $plugin['old'] !== $plugin['new']);
        $updatedPluginNames = array_map(fn ($plugin) => $plugin['name'], $updatedPlugins);

        $allPlugins = self::getPlugins();

        $notUpdatedPlugins = array_filter($allPlugins, fn ($plugin) => !in_array($plugin['name'], $updatedPluginNames));
        $notUpdatedPluginsNames = array_map(fn ($plugin) => $plugin['name'], $notUpdatedPlugins);

        WP_CLI\Utils\format_items('table', $updatedPlugins, ['name', 'old', 'new']);
        WP_CLI\Utils\format_items('table', $notUpdatedPluginsNames, ['name']);
    }
}
