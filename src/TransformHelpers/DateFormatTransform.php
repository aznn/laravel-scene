<?php

namespace Azaan\LaravelScene\TransformHelpers;


use Azaan\LaravelScene\Contracts\ValueTransformation;
use Carbon\Carbon;

/**
 * Class DateFormatTransform
 *
 * Given a format format a carbon date instance to a string
 *
 * @package Azaan\LaravelScene\TransformHelpers
 */
class DateFormatTransform implements ValueTransformation
{
    /**
     * @var
     */
    private $format;
    /**
     * @var null
     */
    private $default;

    /**
     * DateFormatTransform constructor.
     *
     * @param string $format
     * @param null   $default
     */
    public function __construct($format = null, $default = null)
    {
        $this->format  = $format;
        $this->default = $default;
    }

    /**
     * Given the value return the transformed value
     *
     * @param $date
     * @return mixed
     */
    public function transform($date)
    {
        if ($date == null) {
            return $this->default;
        }

        if (!($date instanceof Carbon)) {
            $date = new Carbon($date);
        }

        if ($this->format == null) {
            return $date->toDateTimeString();
        }

        return $date->format($this->format);
    }
}
