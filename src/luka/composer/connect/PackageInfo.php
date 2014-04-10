<?php
/**
 * @author    Axel Helmert <ah@luka.de>
 * @copyright Copyright (c) 2014 LUKA netconsult GmbH (www.luka.de)
 * @license   http://www.gnu.org/licenses/gpl-3.0.en.html
 */

namespace luka\composer\connect;

/**
 * Package information
 */
class PackageInfo
{
    /**
     * @var ChannelReader
     */
    protected $reader = null;

    /**
     * @var ReleaseInfo[]
     */
    protected $releases = null;

    /**
     * @var string
     */
    protected $name = null;

    /**
     * @param ChannelReader $reader
     */
    public function __construct($name, ChannelReader $reader)
    {
        $this->name = $name;
        $this->reader = $reader;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return ReleaseInfo[]
     */
    public function getReleases()
    {
        if ($this->releases === null) {
            $this->releases = $this->reader->loadReleases($this);
        }

        return $this->releases;
    }
}
