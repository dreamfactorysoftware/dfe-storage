<?php namespace DreamFactory\Enterprise\Storage\Services;

use Illuminate\Contracts\Foundation\Application;

/**
 * A base class for storage services
 */
class BaseStorageService
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type Application No underscore so it matches ServiceProvider class...
     */
    protected $app;

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct($app = null)
    {
        $this->app = $app;

        $this->boot();
    }

    /**
     * Perform any service initialization
     */
    public function boot()
    {
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return $this->app;
    }
}
