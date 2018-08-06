<?php

namespace Tests;

use Azaan\LaravelScene\Contracts\Transformer;
use Azaan\LaravelScene\SceneTransformer;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\Person;


class BaseTestCase extends TestCase
{
    /**
     * Create a simple transformer
     *
     * @param null $structure
     * @param null $minStructure
     * @param null $downloadStructure
     *
     * @return SceneTransformer
     */
    protected function simpleTransformer($structure = null, $minStructure = null, $downloadStructure = null)
    {
        /** @var SceneTransformer $transformer */
        $transformer = new class($structure, $minStructure, $downloadStructure) extends SceneTransformer
        {

            /**
             * Structure transformations.
             *
             * @return array structure
             */
            protected function getStructure()
            {
                return [];
            }
        };

        if ($minStructure !== null) {
            $transformer->showMin = true;
        }

        if ($downloadStructure !== null) {
            $transformer->useDownloadStructure = true;
        }

        return $transformer;
    }

    /**
     * Assert a simple transformer
     *
     * @param      $data
     * @param      $expected
     * @param      $structure
     * @param null $minStructure
     * @param null $downloadStructure
     */
    protected function assertSimpleTransformer($data, $expected, $structure, $minStructure = null, $downloadStructure = null)
    {
        $transformer = $this->simpleTransformer($structure, $minStructure, $downloadStructure);

        $this->assertTransformation($transformer, $data, $expected);
    }

    /**
     * Assert transformed data
     *
     * @param $transformer
     * @param $data
     * @param $expected
     */
    protected function assertTransformation(Transformer $transformer, $data, $expected)
    {
        $out = $transformer->transform($data);

        $this->assertSame($expected, $out);
    }

    /**
     * Dummy persons data
     *
     * @return array
     */
    protected function personsArray()
    {
        return [
            [
                'id'    => 1,
                'name'  => 'Azaan',
                'email' => 'azaan@email.com',
            ],
            [
                'id'    => 2,
                'name'  => 'John Doe',
                'email' => 'john@email.com',
            ],
        ];
    }

    /**
     * Array of persons models
     *
     * @return array
     */
    protected function personsModels()
    {
        return array_map(function ($o) {
            return new Person($o);
        }, $this->personsArray());
    }

    /**
     * Persons models
     *
     * @return Collection
     */
    protected function personsDbCollection()
    {
        return new Collection($this->personsModels());
    }

    /**
     * Support collection persons
     *
     * @param bool $dbModels
     * @return \Illuminate\Support\Collection
     */
    protected function personsSupportCollection($dbModels = true)
    {
        return new \Illuminate\Support\Collection($dbModels ? $this->personsModels() : $this->personsArray());
    }
}
