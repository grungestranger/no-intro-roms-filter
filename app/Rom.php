<?php

namespace App;

class Rom
{
    /** @var string */
    protected $name;

    /** @var array */
    protected $regions = [];

    /** @var array */
    protected $params = [];

    /** @var string|null */
    protected $revision;

    /** @var string|null */
    protected $version;

    /**
     * @param string $name
     * @param array $params
     */
    public function __construct(string $name, array $params)
    {
        $this->name = $name;

        if ($params) {
            $params = array_map(function ($param) {
                return trim($param);
            }, $params);

            $this->regions = preg_split('/ *, */u', array_shift($params));

            foreach ($params as $param) {
                if (preg_match('/^rev +([a-z0-9\.]+)$/iu', $param, $matches)) {
                    $this->revision = $matches[1];

                    break;
                } elseif (preg_match('/^v *([0-9\.]+)$/iu', $param, $matches)) {
                    $this->version = $matches[1];

                    break;
                }
            }

            $this->params = $params;
        }
    }

    /**
     * @return array
     */
    public function getRegions(): array
    {
        return $this->regions;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return string|null
     */
    public function getRevision(): ?string
    {
        return $this->revision;
    }

    /**
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
