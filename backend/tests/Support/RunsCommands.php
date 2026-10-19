<?php

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** Exécution des commandes console dans le noyau de test, sans interaction. */
trait RunsCommands
{
    /** @param array<string, mixed> $input arguments et options, ex. ['phone' => '+216…', '--create' => true] */
    protected function runCommand(string $name, array $input = []): CommandTester
    {
        return $this->runCommandObject((new Application(static::$kernel ?? static::bootKernel()))->find($name), $input);
    }

    /** @param array<string, mixed> $input */
    protected function runCommandObject(Command $command, array $input = []): CommandTester
    {
        $tester = new CommandTester($command);
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }
}
