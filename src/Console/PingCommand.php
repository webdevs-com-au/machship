<?php

namespace WebdevsComAu\MachShip\Console;

use Illuminate\Console\Command;
use WebdevsComAu\MachShip\Exceptions\MachShipException;
use WebdevsComAu\MachShip\MachShip;

class PingCommand extends Command
{
    protected $signature = 'machship:ping';

    protected $description = 'Check that the configured MachShip API token is valid';

    public function handle(MachShip $machShip): int
    {
        try {
            if ($machShip->ping()) {
                $this->components->info('MachShip token is valid.');

                return self::SUCCESS;
            }
        } catch (MachShipException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->error('MachShip did not confirm the token.');

        return self::FAILURE;
    }
}
