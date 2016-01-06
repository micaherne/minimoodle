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

    // Plugins needed for initialising unit tests
    protected static $requiredforphpunitinit = ['tool_phpunit', 'block_admin_bookmarks', 'block_private_files',
        'block_online_users', 'block_badges', 'block_calendar_month', 'block_calendar_upcoming', 'block_course_overview',
        'block_site_main_menu', 'block_course_summary'
    ];

    // Plugins needed for unit test includes
    protected static $requiredforphpunitrun = [
        'mod_quiz', // lib\tests\questionlib_test.php
        'gradereport_grader', // grade\tests\report_graderlib_test.php,
        'gradereport_user', // grade\tests\reportuserlib_test.php
        'enrol_imsenterprise', // course\tests\courselib_test.php,
        'mod_wiki', // tag\tests\events_test.php,
        'qbehaviour_deferredfeedback', // question\type\missingtype\tests\missingtype_test.php
        'mod_assign', // course\tests\courselib_test.php
        'mod_assignment', // mod\assign\tests\upgradelib_test.php
        'profilefield_datetime', // user\profile\index_field_form.php
    ];

    // Plugins needed to make unit tests pass
    protected static $requiredforphpunitpass = [
    	'block_search_forums', // course format default
    	'block_news_items', // course format default
    	'block_calendar_upcoming', // course format default
		'block_recent_activity', // course format default
    ];

    public function configure() {
        $this->setName('plugins:remove')
            ->setDescription('Remove non-essential plugins')
            ->addArgument('moodledir', InputArgument::OPTIONAL, 'The path to your Moodle codebase')
            ->addOption('testable', null, InputOption::VALUE_NONE, 'Adds the minimum plugins required to run unit tests (there is no guarantee the tests will pass!)');
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

        if ($input->getOption('testable')) {
            foreach (array_merge(self::$requiredforphpunitinit, self::$requiredforphpunitrun, self::$requiredforphpunitpass) as $component) {
                $plugins[$component]['can_uninstall'] = false;
            }
        }

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