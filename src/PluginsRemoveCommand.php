<?php

namespace Minimoodle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Minimoodle\Moodle\FakeDb;
use Symfony\Component\Console\Input\InputOption;

class PluginsRemoveCommand extends Command {

    public function configure() {
        $this->setName('plugins:remove')
            ->setDescription('Remove non-essential plugins')
            ->addArgument('moodledir', InputArgument::OPTIONAL, 'The path to your Moodle codebase')
            ->addOption('testable', null, InputOption::VALUE_NONE, 'Adds the minimum plugins required to run unit tests (there is no guarantee the tests will pass!)')
            ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Which course format to use', 'weeks')
            ->addOption('theme', null, InputOption::VALUE_OPTIONAL, 'Which theme format to use', 'clean')
            ->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        global $CFG;

        $moodledir = $input->getArgument('moodledir');
        $theme = $input->getOption('theme');
        $format = $input->getOption('format');
        $dryrun = $input->getOption('dry-run');

        // Do we add default blocks etc to enable the package to be installed?
        // TODO: Make an option to switch off
        $installable = true;

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

        // Read static data about plugins
        $yaml = file_get_contents(__DIR__ . '/../resource/plugins.yml');
        $staticplugindata = \Symfony\Component\Yaml\Yaml::parse($yaml);

        self::setUpGlobals($moodledir);

        if (!file_exists("$moodledir/theme/$theme")) {
            $output->writeln("Invalid theme $theme");
            return;
        }

        if (!file_exists("$moodledir/course/format/$format")) {
            $output->writeln("Invalid format $format");
            return;
        }

        $CFG->theme = $theme; // We MUST keep a default theme

        $plugins = self::getPluginMetadata($format);

        self::preventUninstall('theme_' . $theme, $plugins);
        self::preventUninstall('format_' . $format, $plugins);

        $fs = new Filesystem();

        if ($input->getOption('testable')) {
            foreach(['required_for_phpunit_init', 'required_for_phpunit_run', 'required_for_phpunit_pass'] as $type) {
                foreach ($staticplugindata[$type] as $component) {
                    self::preventUninstall($component, $plugins);
                }
            }

        }

        if ($installable) {
            foreach ($staticplugindata['default_system_blocks'] as $blockname) {
                self::preventUninstall('block_' . $blockname, $plugins);
            }
        }

        if ($dryrun) {
            echo "Dry running removal process\n";
        }

        foreach ($plugins as $plugin => $meta) {
            if ($meta['can_uninstall']) {
                echo "Removing $plugin\n";
                if (!$dryrun) {
                    $fs->remove($CFG->dirroot . $meta['dir']);
                }
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

    public static function getPluginMetadata($courseformat = 'weeks') {
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
                $meta[$component] = ['type' => $plugintype, 'dir' => str_replace($CFG->dirroot, '', $plugindir), 'can_uninstall' => $can_uninstall,
                    'versioninfo' => (array) self::getVersionInfo($plugindir)
                ];
            }
        }

        return $meta;
    }

    public static function getVersionInfo($plugindir) {

        $plugin = new \stdClass();

        if (!is_readable($plugindir.'/version.php')) {
            return $plugin;
        }

        $plugin->version = null;
        $module = $plugin; // Prevent some notices when plugin placed in wrong directory.
        require($plugindir.'/version.php');  // defines $plugin with version etc

        return $plugin;
    }

    /**
     * Prevent uninstall, taking dependencies into account.
     *
     * @param unknown $component
     * @param unknown $pluginMetadata
     */
    public static function preventUninstall($component, &$pluginMetadata) {
        $pluginMetadata[$component]['can_uninstall'] = false;
        $versioninfo = $pluginMetadata[$component]['versioninfo'];
        if (isset($versioninfo['dependencies'])) {
            foreach ($versioninfo['dependencies'] as $dependency => $version) {
                if ($pluginMetadata[$dependency]['can_uninstall']) {
                    self::preventUninstall($dependency, $pluginMetadata);
                }
            }
        }
    }

}
