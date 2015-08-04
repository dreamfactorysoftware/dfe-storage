<?php namespace DreamFactory\Enterprise\Storage\Providers;

use DreamFactory\Enterprise\Storage\Services\VirtualStorageService;
use DreamFactory\Enterprise\Storage\Utility\Managed;
use Illuminate\Support\ServiceProvider;

/**
 * Register the storage mount service as a Laravel provider
 */
class VirtualStorageServiceProvider extends ServiceProvider
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string The name of the service in the IoC
     */
    const IOC_NAME = 'managed.storage';

    //********************************************************************************
    //* Public Methods
    //********************************************************************************

    /**
     * Start up the dependency
     */
    public function boot()
    {
        Managed::initialize();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(static::IOC_NAME,
            function ($app) {
                return new VirtualStorageService($app);
            });
    }
}
