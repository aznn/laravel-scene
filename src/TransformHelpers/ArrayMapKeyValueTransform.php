<?php

namespace Azaan\LaravelScene\TransformHelpers;


use Azaan\LaravelScene\Contracts\ValueTransformation;

/**
 * Class ArrayMapKeyValueTransform
 *
 * Given a map with [key => value] mappings transform a given key to the format
 * [
 *  'key' => $key,
 *  'value' => $value || $default
 * ]
 *
 * @package Azaan\LaravelScene\TransformHelpers
 */
class ArrayMapKeyValueTransform implements ValueTransformation
{
    /**
     * @var
     */
    private $map;
    /**
     * @var null
     */
    private $default;

    /**
     * ArrayMapKeyValueTransform constructor.
     *
     * @param      $map
     * @param null $default
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
            return [
                'key'   => $value,
                'value' => $this->map[$value],
            ];
        }

        return [
            'key'   => $value,
            'value' => $this->default,
        ];
    }
}
