<?php
namespace openpsa\installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io) : void
    {
        $installer = new installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }

    public function deactivate(Composer $composer, IOInterface $io) : void
    {
        $installer = new installer($io, $composer);
        $composer->getInstallationManager()->removeInstaller($installer);
    }

    public function uninstall(Composer $composer, IOInterface $io) : void
    {
        // maybe remove symlinks here?
    }
}
