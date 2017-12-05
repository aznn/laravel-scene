<?php

namespace Azaan\LaravelScene\Contracts;


interface Transformer
{
    /**
     * Given the value return the transformed value
     *
     * @param $value
     * @return mixed
     */
    public function transform($value);
}
