<?php namespace DreamFactory\Enterprise\Storage\Facades;

use DreamFactory\Enterprise\Storage\Managers\MountManager;
use DreamFactory\Enterprise\Storage\Providers\MountServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Filesystem mount( string $name, $options = [] )
 * @method static MountManager unmount( string $name, $options = [] )
 */
class Mounter extends Facade
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return MountServiceProvider::IOC_NAME;
    }
}