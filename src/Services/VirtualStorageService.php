<?php namespace DreamFactory\Enterprise\Storage\Services;

use DreamFactory\Enterprise\Common\Utility\Disk;
use DreamFactory\Enterprise\Storage\Enums\ManagedDefaults;
use DreamFactory\Enterprise\Storage\Utility\Managed;

/**
 * Core instance storage services
 */
class VirtualStorageService extends BaseStorageService
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type string
     */
    protected $privatePathName;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Init
     */
    public function boot()
    {
        $this->privatePathName =
            Disk::segment(config('dreamfactory.private-path-name', ManagedDefaults::DEFAULT_PRIVATE_PATH_NAME));
    }

    /**
     * @return string
     */
    public function getRootStoragePath()
    {
        return Managed::getConfig('storage-root');
    }

    /**
     * @param string|null $append Optional path to append
     *
     * @return string
     */
    public function getStoragePath($append = null)
    {
        return Managed::getStoragePath($append);
    }

    /**
     * We want the private path of the instance to point to the user's area. Instances have no "private path" per se.
     *
     * @param string|null $append Optional path to append
     *
     * @return mixed
     */
    public function getPrivatePath($append = null)
    {
        return Managed::getPrivatePath($append);
    }

    /**
     * We want the private path of the instance to point to the user's area. Instances have no "private path" per se.
     *
     * @param string|null $append Optional path to append
     *
     * @return mixed
     */
    public function getOwnerPrivatePath($append = null)
    {
        return Managed::getOwnerPrivatePath($append);
    }

    /**
     * @return string
     */
    public function getSnapshotPath()
    {
        return $this->getOwnerPrivatePath(config('provisioning.snapshot-path-name',
            ManagedDefaults::SNAPSHOT_PATH_NAME));
    }

    /**
     * @return string
     */
    public function getPrivatePathName()
    {
        return $this->privatePathName;
    }
}