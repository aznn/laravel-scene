<?php

namespace Azaan\LaravelScene\TransformHelpers;


use Azaan\LaravelScene\Contracts\ValueTransformation;

/**
 * Class ArrayMapTransform
 *
 * Given a map with [key => value] map a key to the value or default
 *
 * @package Azaan\LaravelScene\TransformHelpers
 */
class ArrayMapTransform implements ValueTransformation
{
    /**
     * @var array
     */
    private $map;

    /**
     * @var mixed|null
     */
    private $default;

    /**
     * ArrayMapTransformer constructor.
     *
     * @param array $map
     * @param mixed $default default value to return if look up value not in map
     */
    public function __construct($map, $default = null)
    {
        $this->map     = $map;
        $this->default = $default;
    }

    /**
     * Given the value return the transformed value
     *
     * @param $value
     * @return mixed
     */
    public function transform($value)
    {
        if (isset($this->map[$value])) {
            return $this->map[$value];
        }

        return $this->default;
    }
}
