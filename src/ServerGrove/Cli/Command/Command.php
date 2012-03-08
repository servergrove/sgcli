<?php

/*
 * This file is part of sgcli.
 *
 * (c) ServerGrove
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ServerGrove\Cli\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;

/**
 * Base class for sgcli commands
 *
 */
abstract class Command extends BaseCommand
{
    /**
     * @return \ServerGrove\APIClient
     */
    protected function getClient()
    {
        return $this->getApplication()->getClient();
    }

}
