<?php

namespace Minimoodle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Minimoodle\Moodle\FakeDb;
use Minimoodle\Moodle\FakeStringManager;

class PluginsRemoveCommand extends Command {

    public function configure() {
        $this->setName('plugins:remove')
            ->setDescription('Remove non-essential plugins')
            ->addArgument('moodledir', InputArgument::OPTIONAL, 'The path to your Moodle codebase');
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        global $CFG;

        $moodledir = $input->getArgument('moodledir');

        if (is_null($moodledir)) {
            // TODO: Check some default codebase location
            $output->writeln("No moodle codebase found");
            return;
        }

        // Check it is an actual codebase
        if (!file_exists("$moodledir/version.php")) {
            $output->writeln("Can't find version.php - not a Moodle codebase");
            return;
        }

        self::setUpGlobals($moodledir);

        // TODO: Make the choice of theme an input parameter
        $CFG->theme = 'clean'; // We MUST keep a default theme

        // TODO: getPluginMetadata defaults to topics format - make a parameter
        $plugins = self::getPluginMetadata();

        $fs = new Filesystem();

        foreach ($plugins as $plugin => $meta) {
            if ($meta['can_uninstall']) {
                echo "Removing $plugin\n";
                $fs->remove($CFG->dirroot . $meta['dir']);
            }
        }
    }

    public static function setUpGlobals($moodledir) {
        global $CFG, $DB;

        $CFG = new \stdClass();
        $CFG->dirroot = $moodledir;
        $CFG->dataroot = sys_get_temp_dir();
        $CFG->wwwroot = 'http://example.com';
        $CFG->debug = E_ALL;
        $CFG->debugdisplay = 1;

        define('CLI_SCRIPT', true);
        define('ABORT_AFTER_CONFIG', true); // We need just the values from config.php.
        define('CACHE_DISABLE_ALL', true); // This prevents reading of existing caches.
        define('IGNORE_COMPONENT_CACHE', true);

        require_once($CFG->dirroot . '/lib/setup.php');

        // Minimum required to use core_plugin_manager
        require_once($CFG->dirroot . '/lib/setuplib.php');
        require_once($CFG->libdir .'/dmllib.php');          // Database access
        require_once($CFG->dirroot . '/lib/moodlelib.php');
        require_once($CFG->dirroot.'/cache/lib.php');

        spl_autoload_register('core_component::classloader');

        $DB = new FakeDb();

    }

    public static function getPluginMetadata($courseformat = 'topics') {
        global $CFG;

        $pluginmanager = \core_plugin_manager::instance();

        $meta = [];
        $plugintypes = \core_component::get_plugin_types();
        foreach($plugintypes as $plugintype => $plugintypedir) {
            $plugins = \core_component::get_plugin_list($plugintype);
            foreach($plugins as $pluginname => $plugindir) {
                $component = \core_component::normalize_componentname("{$plugintype}_{$pluginname}");
                $pluginfo = $pluginmanager->get_plugin_info($component);

                // format uses get_config() which we don't want to fake
                if ($plugintype == 'format') {
                    $can_uninstall = $pluginname != $courseformat;
                } else {
                    $can_uninstall = $pluginfo->is_uninstall_allowed();
                }
                $meta[$component] = ['type' => $plugintype, 'dir' => str_replace($CFG->dirroot, '', $plugindir), 'can_uninstall' => $can_uninstall];
            }
        }

        return $meta;
    }

}