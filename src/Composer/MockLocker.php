<?php

namespace Fazland\Rabbitd\Composer;

use Composer\Package\Locker;
use Composer\Repository\ArrayRepository;

class MockLocker extends Locker
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function isLocked()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getLockedRepository($withDevReqs = false)
    {
        return new ArrayRepository();
    }

    /**
     * {@inheritdoc}
     */
    public function getPlatformRequirements($withDevReqs = false)
    {
        return [];
    }

    public function getMinimumStability()
    {
        return 'stable';
    }

    public function getStabilityFlags()
    {
        return [];
    }

    public function getPreferStable()
    {
        return null;
    }

    public function getPreferLowest()
    {
        return null;
    }

    public function getPlatformOverrides()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function getLockData()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function setLockData(array $packages, $devPackages, array $platformReqs, $platformDevReqs, array $aliases, $minimumStability, array $stabilityFlags, $preferStable, $preferLowest, array $platformOverrides)
    {
        return true;
    }
}
