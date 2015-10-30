<?php namespace DreamFactory\Enterprise\Storage\Adapters;

use DreamFactory\Enterprise\Common\Traits\Archivist;
use League\Flysystem\ZipArchive\ZipArchiveAdapter as BaseZipArchiveAdapter;

class ZipArchiveAdapter extends BaseZipArchiveAdapter
{
    //******************************************************************************
    //* Traits
    //******************************************************************************

    use Archivist;
}
