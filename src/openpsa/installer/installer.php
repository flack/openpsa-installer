<?php
namespace openpsa\installer;
use Composer\Installer as base_installer;

class installer extends base_installer
{
    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return $packageType === 'midcom-extras';
    }
}