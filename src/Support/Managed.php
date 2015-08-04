<?php namespace DreamFactory\Enterprise\Storage\Utility;

use DreamFactory\Enterprise\Storage\Enums\ManagedDefaults;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Enums\EnterpriseDefaults;
use DreamFactory\Library\Utility\IfSet;
use DreamFactory\Library\Utility\Json;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Methods for interfacing with DreamFactory Enterprise (DFE)
 *
 * This class discovers if this instance is a DFE cluster participant. When the DFE
 * console provisions an instance, the cluster configuration file is used to determine
 * the necessary information to operate in a managed environment.
 */
final class Managed
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string Prepended to the cache keys of this object
     */
    const CACHE_KEY_PREFIX = 'dfe.managed.config.';
    /**
     * @type int The number of minutes to keep managed instance data cached
     */
    const CACHE_TTL = ManagedDefaults::CONFIG_CACHE_TTL;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type bool Enable/disable debug logging
     */
    protected static $debug = true;
    /**
     * @type string
     */
    protected static $_cacheKey = null;
    /**
     * @type bool
     */
    protected static $_dfeInstance = false;
    /**
     * @type array
     */
    protected static $_config = false;
    /**
     * @type string Our API access token
     */
    protected static $_token = null;
    /**
     * @type array The storage paths
     */
    protected static $_paths = [];
    /**
     * @type string The root storage directory
     */
    protected static $_storageRoot;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Initialization for hosted DSPs
     *
     * @return array
     * @throws \RuntimeException
     */
    public static function initialize()
    {
        static::getCacheKey();

        if (config('app.debug') || !static::loadCachedValues()) {
            //  Discover where I am
            if (!static::getClusterConfiguration()) {
                logger('Unmanaged instance, ignoring.');

                return false;
            }
        }

        //  It's all good!
        static::$_dfeInstance = true;

        //  Generate a signature for signing payloads...
        static::$_token = static::generateSignature();

        if (!static::interrogateCluster()) {
            logger('cluster unreachable or in disarray.');

            throw new \RuntimeException('Unmanaged instance detected.', Response::HTTP_NOT_FOUND);
        }

        logger('managed instance bootstrap complete.');

        return true;
    }

    /**
     * Retrieves an instance's status and caches the shaped result
     *
     * @return array|bool
     */
    protected static function interrogateCluster()
    {
        //  Get my config from console
        $_status = static::callConsole('status', ['id' => $_id = static::getInstanceName()]);

        logger('ops/status response: ' . (Json::encode($_status) ?: print_r($_status, true)));

        if (!($_status instanceof \stdClass) || !data_get($_status, 'response.metadata')) {
            throw new \RuntimeException('Corrupt response during status query for "' . $_id . '".',
                Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$_status->success) {
            throw new \RuntimeException('Unmanaged instance detected.', Response::HTTP_NOT_FOUND);
        }

        if (data_get($_status, 'response.archived', false) || data_get($_status, 'response.deleted', false)) {
            throw new \RuntimeException('Instance "' . $_id . '" has been archived and/or deleted.',
                Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        //  Stuff all the unadulterated data into the config
        static::setConfig([
            //  Storage root is the top-most directory under which all instance storage lives
            'storage-root'  => static::$_storageRoot = static::getConfig('storage-root', storage_path()),
            //  The storage map defines where exactly under $storageRoot the instance's storage resides
            'storage-map'   => (array)data_get($_status, 'response.metadata.storage-map', []),
            'home-links'    => (array)data_get($_status, 'response.home-links'),
            'managed-links' => (array)data_get($_status, 'response.managed-links'),
            'env'           => (array)data_get($_status, 'response.metadata.env', []),
            'audit'         => (array)data_get($_status, 'response.metadata.audit', []),
            'paths'         => $_paths = (array)data_get($_status, 'response.metadata.paths', []),
        ]);

        //  Clean up the paths accordingly
        $_paths['storage-root'] = static::$_storageRoot;
        $_paths['log-path'] =
            Disk::segment([array_get($_paths, 'private-path', storage_path()), ManagedDefaults::PRIVATE_LOG_PATH_NAME],
                false);

        //  prepend real base directory to all collected paths and cache statically
        foreach (array_except($_paths, ['storage-root']) as $_key => $_path) {
            $_paths[$_key] = Disk::path([static::$_storageRoot, $_path], true, 02775, true);
        }

        //  Now place our sanitized data back into the config
        static::setConfig('paths', (array)$_paths);

        //  Get the database config plucking the first entry if one.
        static::setConfig('db', (array)head(data_get($_status, 'response.metadata.db', [])));

        if (false === empty($_status->response->metadata->limits)) {
            static::$_config['limits'] = (array)$_status->response->metadata->limits;
        }

        static::freshenCache();

        return true;
    }

    /**
     * @return array
     */
    protected static function validateClusterEnvironment()
    {
        try {
            //  Start out false
            static::$_dfeInstance = false;

            //  And API url
            if (!isset(static::$_config['console-api-url'], static::$_config['console-api-key'])) {
                logger('Invalid configuration: No "console-api-url" or "console-api-key" in cluster manifest.');

                return false;
            }

            //  Make it ready for action...
            static::setConfig('console-api-url', rtrim(static::getConfig('console-api-url'), '/') . '/');

            //  And default domain
            $_host = static::getHostName();

            if (!empty($_defaultDomain = ltrim(static::getConfig('default-domain'), '. '))) {
                $_defaultDomain = '.' . $_defaultDomain;

                //	If this isn't an enterprise instance, bail
                if (false === strpos($_host, $_defaultDomain)) {
                    logger('Invalid "default-domain" for host "' . $_host . '"');

                    return false;
                }

                static::setConfig('managed.default-domain', $_defaultDomain);
            }

            if (empty($_storageRoot = static::getConfig('storage-root'))) {
                logger('No "storage-root" found.');

                return false;
            }

            static::setConfig([
                'storage-root'          => rtrim($_storageRoot, ' ' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                'managed.instance-name' => str_replace($_defaultDomain, null, $_host),
            ]);

            //  It's all good!
            return true;
        } catch (\InvalidArgumentException $_ex) {
            //  The file is bogus or not there
            return false;
        }
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array  $payload
     * @param array  $curlOptions
     *
     * @return bool|\stdClass|array
     */
    protected static function callConsole($uri, $payload = [], $curlOptions = [], $method = Request::METHOD_POST)
    {
        try {
            //  Allow full URIs or manufacture one...
            if ('http' != substr($uri, 0, 4)) {
                $uri = static::$_config['console-api-url'] . ltrim($uri, '/ ');
            }

            if (false === ($_result = Curl::request($method, $uri, static::signPayload($payload), $curlOptions))) {
                throw new \RuntimeException('Failed to contact API server.');
            }

            if (!($_result instanceof \stdClass)) {
                if (is_string($_result) && (false === json_decode($_result) || JSON_ERROR_NONE !== json_last_error())) {
                    throw new \RuntimeException('Invalid response received from DFE console.');
                }
            }

            return $_result;
        } catch (\Exception $_ex) {
            logger('api error: ' . $_ex->getMessage());

            return false;
        }
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return array|mixed
     */
    protected static function getClusterConfiguration($key = null, $default = null)
    {
        if (false === static::$_config) {
            $_configFile = static::locateClusterEnvironmentFile(EnterpriseDefaults::CLUSTER_MANIFEST_FILE);

            if (!$_configFile || !file_exists($_configFile)) {
                return false;
            }

            logger('cluster config found: ' . $_configFile);

            try {
                logger('cluster config read: ' . ($_json = file_get_contents($_configFile)));
                static::$_config = Json::decode($_json);

                if (!static::validateClusterEnvironment()) {
                    return false;
                }
            } catch (\Exception $_ex) {
                logger('Cluster configuration file is not in a recognizable format.');
                static::$_config = false;

                throw new \RuntimeException('This instance is not configured properly for your system environment.');
            }
        }

        return null === $key ? static::$_config : static::getConfig($key, $default);
    }

    /**
     * @param array $payload
     *
     * @return array
     */
    protected static function signPayload(array $payload)
    {
        return array_merge([
            'client-id'    => static::$_config['client-id'],
            'access-token' => static::$_token,
        ],
            $payload ?: []);
    }

    /**
     * @return string
     */
    protected static function generateSignature()
    {
        return hash_hmac(static::$_config['signature-method'],
            static::$_config['client-id'],
            static::$_config['client-secret']);
    }

    /**
     * @return boolean
     */
    public static function isManagedInstance()
    {
        return static::$_dfeInstance;
    }

    /**
     * @return string
     */
    public static function getInstanceName()
    {
        return static::getConfig('instance-name');
    }

    /**
     * @param string|null $append Optional path to append
     *
     * @return string
     */
    public static function getStoragePath($append = null)
    {
        return Disk::segment([array_get(static::$_paths, 'storage-path'), $append]);
    }

    /**
     * @param string|null $append Optional path to append
     *
     * @return string
     */
    public static function getPrivatePath($append = null)
    {
        return Disk::segment([array_get(static::$_paths, 'private-path'), $append]);
    }

    /**
     * @return string Absolute /path/to/logs
     */
    public static function getLogPath()
    {
        return Disk::path([static::getPrivatePath(), ManagedDefaults::PRIVATE_LOG_PATH_NAME], true, 2775);
    }

    /**
     * @param string|null $name
     *
     * @return string The absolute /path/to/log/file
     */
    public static function getLogFile($name = null)
    {
        return Disk::path([static::getLogPath(), ($name ?: static::getInstanceName() . '.log')]);
    }

    /**
     * @param string|null $append Optional path to append
     *
     * @return string
     */
    public static function getOwnerPrivatePath($append = null)
    {
        return Disk::segment([array_get(static::$_paths, 'owner-private-path'), $append]);
    }

    /**
     * Retrieve a config value or the entire array
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return array|mixed
     */
    public static function getConfig($key = null, $default = null)
    {
        if (null === $key) {
            return static::$_config;
        }

        $_value = array_get(static::$_config, $key, $default);

        //  Add value to array if defaulted
        $_value === $default && static::setConfig($key, $_value);

        return $_value;
    }

    /**
     * Retrieve a config value or the entire array
     *
     * @param string|array $key A single key to set or an array of KV pairs to set at once
     * @param mixed        $value
     *
     * @return array|mixed
     */
    protected static function setConfig($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $_key => $_value) {
                array_set(static::$_config, $_key, $_value);
            }

            return static::$_config;
        }

        return array_set(static::$_config, $key, $value);
    }

    /**
     * Refreshes the cache with fresh values
     */
    protected static function freshenCache()
    {
        \Cache::put(static::getCacheKey(),
            ['paths' => static::$_paths, 'config' => static::$_config],
            static::CACHE_TTL);
    }

    /**
     * Reload the cache
     */
    protected static function loadCachedValues()
    {
        if (is_array($_cache = \Cache::get(static::$_cacheKey))) {
            static::$_paths = array_get($_cache, 'paths');
            static::$_config = array_get($_cache, 'config');
        }

        return !empty(static::$_paths) && !empty(static::$_config);
    }

    /**
     * Locate the configuration file for DFE, if any
     *
     * @param string $file
     *
     * @return bool|string
     */
    protected static function locateClusterEnvironmentFile($file)
    {
        $_path = isset($_SERVER, $_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : getcwd();

        while (true) {
            if (file_exists($_path . DIRECTORY_SEPARATOR . $file)) {
                return $_path . DIRECTORY_SEPARATOR . $file;
            }

            $_parentPath = dirname($_path);

            if ($_parentPath == $_path || empty($_parentPath) || $_parentPath == DIRECTORY_SEPARATOR) {
                return false;
            }

            $_path = $_parentPath;
        }

        return false;
    }

    /**
     * Gets my host name
     *
     * @return string
     */
    protected static function getHostName()
    {
        return static::getConfig('managed.host-name', app('request')->server->get('HTTP_HOST', gethostname()));
    }

    /**
     * Returns a key prefixed for use in \Cache
     *
     * @return string
     */
    protected static function getCacheKey()
    {
        return static::$_cacheKey = static::$_cacheKey ?: static::CACHE_KEY_PREFIX . static::getHostName();
    }

    /**
     * Return a database configuration as specified by the console if managed, or config() otherwise.
     *
     * @return array
     */
    public static function getDatabaseConfig()
    {
        return static::isManagedInstance() ? static::getConfig('db')
            : config('database.connections.' . config('database.default'), []);
    }

    /**
     * Return the limits for this instance or an empty array if none.
     *
     * @param string|null $limitKey A key within the limits to retrieve. If omitted, all limits are returned
     * @param array       $default  The default value to return if $limitKey was not found
     *
     * @return array|mixed
     */
    public static function getLimits($limitKey = null, $default = [])
    {
        return null === $limitKey
            ? static::getConfig('limits', [])
            : array_get(static::getConfig('limits', []),
                $limitKey,
                $default);
    }

    /**
     * Return the Console API Key hash or null
     *
     * @return string|null
     */
    public static function getConsoleKey()
    {
        return static::isManagedInstance() ? hash(ManagedDefaults::DEFAULT_SIGNATURE_METHOD,
            IfSet::getDeep(static::$_config, 'env', 'cluster-id') . IfSet::getDeep(static::$_config,
                'env',
                'instance-id')) : null;
    }
}
