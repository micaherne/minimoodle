<?php

$CFG = new stdClass();
$CFG->dirroot = 'F:\htdocs\minimoodle';
$CFG->dataroot = sys_get_temp_dir();
$CFG->wwwroot = 'http://example.com';
$CFG->debug = E_ALL;
$CFG->debugdisplay = 1;

$CFG->theme = 'clean'; // We MUST keep a default theme

define('CLI_SCRIPT', true);
define('ABORT_AFTER_CONFIG', true); // We need just the values from config.php.
define('CACHE_DISABLE_ALL', true); // This prevents reading of existing caches.
define('IGNORE_COMPONENT_CACHE', true);

require_once($CFG->dirroot . '/lib/setup.php');
require_once(__DIR__ . '/vendor/autoload.php');

spl_autoload_register('core_component::classloader');

// Minimum required to use core_plugin_manager
require_once($CFG->dirroot . '/lib/setuplib.php');
require_once($CFG->libdir .'/dmllib.php');          // Database access
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot.'/cache/lib.php');

// Fake $DB for pluginfo that do record_exists()
$DB = new fakedb();

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;

Minimoodle::removePlugins();

class Minimoodle {

    public static $yamlfile = __DIR__ . '/minimoodle.yaml';

    // We need at least one course format, but can decide which
    public static $courseformat = 'topics';

    public static function init() {
        global $CFG;

        $pluginmanager = core_plugin_manager::instance();

        $meta = [];
        $plugintypes = core_component::get_plugin_types();
        foreach($plugintypes as $plugintype => $plugintypedir) {
            $plugins = core_component::get_plugin_list($plugintype);
            foreach($plugins as $pluginname => $plugindir) {
                $component = core_component::normalize_componentname("{$plugintype}_{$pluginname}");
                echo "$component\n";
                $pluginfo = $pluginmanager->get_plugin_info($component);

                // format uses get_config() which we don't want to fake
                if ($plugintype == 'format') {
                    $can_uninstall = $pluginname != self::$courseformat;
                } else {
                    $can_uninstall = $pluginfo->is_uninstall_allowed();
                }
                $meta[$component] = ['type' => $plugintype, 'dir' => str_replace($CFG->dirroot, '', $plugindir), 'can_uninstall' => $can_uninstall];
            }
        }
        file_put_contents(self::$yamlfile, Yaml::dump($meta));
    }

    public static function removePlugins() {
        global $CFG;

        if (!file_exists(self::$yamlfile)) {
            throw new Exception("Yaml file not found");
        }

        $fs = new Filesystem();

        $yaml = file_get_contents(self::$yamlfile);
        $plugins = Yaml::parse($yaml);

        foreach ($plugins as $plugin => $meta) {
            if ($meta['can_uninstall']) {
                echo "Removing $plugin\n";
                $fs->remove($CFG->dirroot . $meta['dir']);
            }
        }
    }

}

class fakedb {

    // For auth, qtype etc
    public function record_exists() {
        return false;
    }

}
