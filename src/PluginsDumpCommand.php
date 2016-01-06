<?php

namespace Minimoodle;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class PluginsDumpCommand extends Command {

    public function configure() {
        $this->setName('plugins:dump')
            ->setDescription("Dump plugin data to YAML")
            ->addArgument('moodledir', InputArgument::OPTIONAL, 'The path to your Moodle codebase')
            ->addArgument('outputfile', InputArgument::OPTIONAL, 'Output file', 'plugins.yaml');
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        global $CFG;

        // TODO: Copied from RemoveCommand - refactor
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

        RemoveCommand::setUpGlobals($moodledir);

        $outputfile = $input->getArgument('outputfile');

        $plugins = RemoveCommand::getPluginMetadata();
        file_put_contents($outputfile, Yaml::dump($plugins));

        $output->writeln("Plugin data dumped to " . realpath($outputfile));
    }

}