<?php namespace DreamFactory\Enterprise\Storage\Providers;

use DreamFactory\Enterprise\Common\Providers\BaseServiceProvider;
use DreamFactory\Enterprise\Storage\Managers\MountManager;

/**
 * Register the storage mount service as a Laravel provider
 */
class MountServiceProvider extends BaseServiceProvider
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string The name of the service in the IoC
     */
    const IOC_NAME = 'dfe.mount';

    //********************************************************************************
    //* Public Methods
    //********************************************************************************

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->singleton(static::IOC_NAME,
            function ($app){
                return new MountManager($app);
            });
    }

}
