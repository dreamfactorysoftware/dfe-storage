<?php namespace DreamFactory\Enterprise\Storage\Managers;

use DreamFactory\Enterprise\Common\Contracts\StorageMounter;
use DreamFactory\Enterprise\Common\Managers\BaseManager;
use DreamFactory\Enterprise\Storage\Exceptions\MountException;
use DreamFactory\Enterprise\Storage\Providers\MountServiceProvider;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Library\Utility\Json;
use League\Flysystem\Filesystem;

class MountManager extends BaseManager implements StorageMounter
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /** @inheritdoc */
    public function boot()
    {
        if (empty($this->lumberjackPrefix)) {
            $this->setLumberjackPrefix(MountServiceProvider::IOC_NAME);
        }

        parent::boot();
    }

    /**
     * Mount the filesystem "$name" as defined in "config/flysystem.php"
     *
     * @param string $name
     * @param array  $options
     *
     * @return Filesystem
     * @throws \DreamFactory\Enterprise\Storage\Exceptions\MountException
     */
    public function mount($name, $options = [])
    {
        $_tag = str_replace('.', '-', array_get($options, 'tag', $name));

        if (null !== ($_prefix = array_get($options, 'prefix'))) {
            $_prefix = rtrim($_prefix) . DIRECTORY_SEPARATOR;
        }

        try {
            return $this->resolve($_tag);
        } catch (\InvalidArgumentException $_ex) {
        }

        //  See if we have a pre-defined connection
        if (null === ($_config = config('flysystem.connections.' . $name))) {
            if (empty($options)) {
                throw new MountException('No configuration found or specified for mount "' . $name . '".');
            }

            $_config = [];
        }

        //$this->debug('flysystem tag "' . $name . '" pulled with config: ' . Json::encode($_config));

        //  Check for "path" or "root" in config...
        if (null === ($_path = array_get($_config, 'path')) && null === ($_path = array_get($_config, 'root'))) {
            \Log::debug('config is: ' . print_r($_config, true));
            throw new \InvalidArgumentException('No "path" or "root" defined for mount "' . $name . '"');
        }

        if (isset($_config['root'])) {
            unset($_config['root']);
        }

        $_config['path'] = $_path;

        !isset($_config['driver']) && $_config['driver'] = 'local';

        if (!empty($_prefix)) {
            $_path = rtrim($_config['path'], DIRECTORY_SEPARATOR);
            $_prefix = trim($_prefix, ' ' . DIRECTORY_SEPARATOR);

            if (false === strpos($_path, $_prefix)) {
                $_config['path'] = $_path . DIRECTORY_SEPARATOR . $_prefix;
            }
        }

        config(['flysystem.connections.' . $_tag => array_merge($_config, $options)]);

        /** @noinspection PhpUndefinedMethodInspection */
        $this->manage($_tag, $_filesystem = \Flysystem::connection($_tag));

        return $_filesystem;
    }

    /**
     * Unmount the filesystem "$name" as defined in "config/flysystem.php"
     *
     * @param string $name
     * @param array  $options
     *
     * @return StorageMounter
     */
    public function unmount($name, $options = [])
    {
        return $this->unmanage($name);
    }

}