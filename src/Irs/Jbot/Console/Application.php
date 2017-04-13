<?php

namespace Irs\Jbot\Console;

use Irs\Jbot\Console\Command;

class Application extends \Symfony\Component\Console\Application
{
    public function __construct()
    {
        parent::__construct("Julia's Robot");

        $this->add(new Command\Split);
    }
}
