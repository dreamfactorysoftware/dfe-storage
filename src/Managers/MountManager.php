<?php namespace DreamFactory\Enterprise\Storage\Managers;

use DreamFactory\Enterprise\Common\Contracts\StorageMounter;
use DreamFactory\Enterprise\Common\Managers\BaseManager;
use DreamFactory\Enterprise\Storage\Exceptions\MountException;
use DreamFactory\Enterprise\Storage\Providers\MountServiceProvider;
use GrahamCampbell\Flysystem\Facades\Flysystem;
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
        $options['tag'] = $_tag = str_replace('.', '-', array_get($options, 'tag', $name));

        if (null !== ($_prefix = array_get($options, 'prefix'))) {
            $options['prefix'] = $_prefix = rtrim($_prefix) . DIRECTORY_SEPARATOR;
        }

        try {
            return $this->resolve($_tag);
        } catch (\InvalidArgumentException $_ex) {
            //  Ignored
        }

        //  See if we have a pre-defined connection
        if (null === ($_config = config('flysystem.connections.' . $name))) {
            if (empty($options)) {
                throw new MountException('No configuration found or specified for mount "' . $name . '".');
            }

            //  Default to []
            $_config = [];
        }

        //  See if we actually have a config
        if (null === ($_path = array_get($_config, 'path')) && null === ($_path = array_get($_config, 'root'))) {
            throw new \InvalidArgumentException('No "path" or "root" defined for mount "' . $name . '"');
        }

        //  Our path
        $_config['path'] = $_path;

        //  Only path wanted in final config...
        if (isset($_config['root'])) {
            unset($_config['root']);
        }

        //  No driver, use default
        !isset($_config['driver']) && $_config['driver'] = config('flysystem.default');

        //  Make sure the path doesn't already have the prefix...
        if (!empty($_prefix)) {
            $_path = rtrim($_config['path'], DIRECTORY_SEPARATOR);
            $_prefix = trim($_prefix, ' ' . DIRECTORY_SEPARATOR);

            if (false === strpos($_path, $_prefix)) {
                $_config['path'] = $_path . DIRECTORY_SEPARATOR . $_prefix;
            }
        }

        //  Create a config entry for this dynamic flysystem
        config(['flysystem.connections.' . $_tag => array_merge($_config, $options)]);

        /** @noinspection PhpUndefinedMethodInspection */
        $this->manage($_tag, $_filesystem = Flysystem::connection($_tag));

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