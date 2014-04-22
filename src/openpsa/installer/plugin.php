<?php
namespace openpsa\installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}