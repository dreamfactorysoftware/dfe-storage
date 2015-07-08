<?php

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter as BaseZipArchiveAdapter;

class ZipArchiveAdapter extends BaseZipArchiveAdapter
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @param string $tag      Unique identifier for temp space
     * @param bool   $pathOnly If true, only the path is returned.
     *
     * @return \League\Flysystem\Filesystem|string
     */
    protected static function getWorkPath($tag, $pathOnly = false)
    {
        $_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dfe' . DIRECTORY_SEPARATOR . $tag;

        if (!\DreamFactory\Library\Utility\FileSystem::ensurePath($_root)) {
            throw new \RuntimeException('Unable to create working directory "' . $_root . '". Aborting.');
        }

        if ($pathOnly) {
            return $_root;
        }

        //  Set our temp base
        return new Filesystem(new Local($_root));
    }
}