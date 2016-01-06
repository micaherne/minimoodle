<?php

namespace Minimoodle;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class Application extends SymfonyApplication {

    public function __construct() {
        parent::__construct('minimoodle');
        $this->add(new RemoveCommand());
    }

}