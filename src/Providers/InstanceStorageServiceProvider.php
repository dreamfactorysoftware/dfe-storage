<?php namespace DreamFactory\Enterprise\Storage\Providers;

use DreamFactory\Enterprise\Common\Providers\BaseServiceProvider;
use DreamFactory\Enterprise\Database\Enums\GuestLocations;
use DreamFactory\Enterprise\Storage\Services\InstanceStorageService;

/**
 * Registers the instance storage service for the default guest location
 */
class InstanceStorageServiceProvider extends BaseServiceProvider
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /** @inheritdoc */
    const IOC_NAME = 'dfe.instance-storage';

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
        //  Register the manager
        $this->singleton(static::IOC_NAME,
            function ($app){
                return new InstanceStorageService($app,
                    config('provisioning.default-guest-location', GuestLocations::DFE_CLUSTER));
            });
    }
}
