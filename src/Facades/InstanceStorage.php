<?php namespace DreamFactory\Enterprise\Storage\Facades;

use DreamFactory\Enterprise\Database\Models\Instance;
use DreamFactory\Enterprise\Storage\Providers\InstanceStorageServiceProvider;
use DreamFactory\Enterprise\Storage\Services\InstanceStorageService;
use Illuminate\Support\Facades\Facade;
use League\Flysystem\Filesystem;

/**
 * @see \DreamFactory\Enterprise\Storage\Services\InstanceStorageService
 *
 * @method static string|bool buildStorageMap($hashBase = null)
 * @method static InstanceStorageService setInstance(Instance $instance)
 * @method static string getPrivatePathName()
 * @method static string getStorageRootPath($append = null)
 * @method static string getTrashPath($append = null, $create = true)
 * @method static string getStoragePath(Instance $instance, $append = null, $create = false)
 * @method static string getPrivatePath(Instance $instance, $append = null, $create = false)
 * @method static string getPackagePath(Instance $instance, $append = null, $create = false)
 * @method static string getOwnerPrivatePath(Instance $instance, $append = null, $create = false)
 * @method static string getSnapshotPath(Instance $instance, $append = null, $create = false)
 * @method static string getWorkPath(Instance $instance, $append = null)
 * @method static string deleteWorkPath($workPath)
 * @method static Filesystem getTrashMount($append = null, $create = true)
 * @method static Filesystem getStorageRootMount(Instance $instance, $tag = 'storage-root:instance-id')
 * @method static Filesystem getStorageMount(Instance $instance, $tag = 'storage:instance-id')
 * @method static Filesystem getPrivateStorageMount(Instance $instance, $tag = 'private:instance-id')
 * @method static Filesystem getPackageStorageMount(Instance $instance, $tag = 'private:instance-id')
 * @method static Filesystem getOwnerPrivateStorageMount(Instance $instance, $tag = 'owner-private:instance-id')
 * @method static Filesystem getSnapshotMount(Instance $instance, $tag = 'snapshot:instance-id')
 */
class InstanceStorage extends Facade
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /** @inheritdoc */
    /** @noinspection PhpMissingParentCallCommonInspection */
    protected static function getFacadeAccessor()
    {
        return InstanceStorageServiceProvider::IOC_NAME;
    }
}
