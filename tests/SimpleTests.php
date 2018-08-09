<?php

namespace Tests;

use Azaan\LaravelScene\Contracts\Transformer;
use Azaan\LaravelScene\SceneTransformer;
use Illuminate\Support\Collection;
use Tests\Helpers\Person;

class SimpleTests extends BaseTestCase
{
    public function testSimpleTransformation()
    {
        $this->assertSimpleTransformer(
            $this->personsArray(),
            [
                [
                    'id'   => 1,
                    'name' => 'Azaan',
                ],
                [
                    'id'   => 2,
                    'name' => 'John Doe',
                ],
            ],
            [
                'id',
                'name',
            ]
        );
    }

    public function testModelTransformation()
    {
        $this->assertSimpleTransformer(
            $this->personsDbCollection(),
            [
                [
                    'id'   => 1,
                    'name' => 'Azaan',
                ],
                [
                    'id'   => 2,
                    'name' => 'John Doe',
                ],
            ],
            [
                'id',
                'name',
            ]
        );
    }

    public function testCollectionTransformation()
    {
        $this->assertSimpleTransformer(
            $this->personsSupportCollection(),
            [
                [
                    'id'   => 1,
                    'name' => 'Azaan',
                ],
                [
                    'id'   => 2,
                    'name' => 'John Doe',
                ],
            ],
            [
                'id',
                'name',
            ]
        );
    }

    public function testSimpleMinimumStructure()
    {
        $this->assertSimpleTransformer(
            $this->personsArray(),
            [
                [
                    'id' => 1,
                ],
                [
                    'id' => 2,
                ],
            ],
            null,
            [
                'id',
            ]
        );
    }

    public function testSimpleDownloadStructure()
    {
        $this->assertSimpleTransformer(
            $this->personsArray(),
            [
                [
                    'id' => 1,
                ],
                [
                    'id' => 2,
                ],
            ],
            null,
            null,
            [
                'id',
            ]
        );
    }

    public function testEmptyArray()
    {
        $this->assertSimpleTransformer(
            [],
            [],
            ['id', 'name']
        );
    }

    public function testNullState()
    {
        $transformer = new class extends SceneTransformer
        {

            protected function getNullState()
            {
                return [
                    'id' => null,
                ];
            }

            protected function getStructure()
            {
                return ['id'];
            }
        };

        $this->assertTransformation(
            $transformer,
            [null],
            [
                [
                    'id' => null,
                ],
            ]
        );
    }

    public function testNullStateReturnsNull()
    {
        $transformer = new class extends SceneTransformer
        {
            protected function getStructure()
            {
                return ['id'];
            }
        };

        $this->assertTransformation(
            $transformer,
            [null],
            [
                null
            ]
        );
    }

    public function testPreProcessSingle()
    {
        $transformer = new class extends SceneTransformer
        {
            protected function preProcessSingle(&$obj)
            {
                $obj['key'] = 'test';

                return $obj;
            }

            protected function getStructure()
            {
                return ['id', 'key'];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsArray(),
            [
                [
                    'id'  => 1,
                    'key' => 'test',
                ],
                [
                    'id'  => 2,
                    'key' => 'test',
                ],
            ]
        );
    }

    public function testPreProcessCollection()
    {
        $transformer = new class extends SceneTransformer
        {
            protected function preProcessCollection(Collection $collection)
            {
                return $collection->map(function ($o) {
                    $o['key'] = 'test';

                    return $o;
                });
            }

            protected function getStructure()
            {
                return ['id', 'key'];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsArray(),
            [
                [
                    'id'  => 1,
                    'key' => 'test',
                ],
                [
                    'id'  => 2,
                    'key' => 'test',
                ],
            ]
        );
    }

    public function testOrdering()
    {
        $transformer = new class extends SceneTransformer
        {
            protected function getOrderBy()
            {
                return ['id', 'desc'];
            }

            protected function getStructure()
            {
                return ['id'];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsArray(),
            [
                [
                    'id' => 2,
                ],
                [
                    'id' => 1,
                ],
            ]
        );
    }

    public function testLookupWithDifferentKey()
    {
        $transformer = new class extends SceneTransformer
        {
            protected function getStructure()
            {
                return [
                    'key' => 'id',
                ];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsArray(),
            [
                [
                    'key' => 1,
                ],
                [
                    'key' => 2,
                ],
            ]
        );
    }

    public function testTransformerAsChildKey()
    {
        $childTransformer = new class implements Transformer
        {

            public function transform($value)
            {
                return 'child' . $value;
            }
        };

        $transformer = new class(['id' => $childTransformer]) extends SceneTransformer
        {
            protected function getStructure()
            {
                return [];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsArray(),
            [
                [
                    'id' => 'child1',
                ],
                [
                    'id' => 'child2',
                ],
            ]
        );
    }

    public function testTransformerChildWithDifferentKey()
    {
        $childTransformer = new class implements Transformer
        {

            public function transform($value)
            {
                return 'child' . $value;
            }
        };

        $structure = [
            'key' => ['id', $childTransformer],
        ];

        $transformer = new class($structure) extends SceneTransformer
        {
            protected function getStructure()
            {
                return [];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsArray(),
            [
                [
                    'key' => 'child1',
                ],
                [
                    'key' => 'child2',
                ],
            ]
        );
    }

    public function testTransformerLookup()
    {
        $structure = [
            'id',
            'status',
            'number',
        ];

        $transformer = new class($structure) extends SceneTransformer
        {
            protected function getStructure()
            {
                return [];
            }

            protected function getId($obj)
            {
                return 'child' . $obj['id'];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsDbCollection(),
            [
                [
                    // getter on transformer
                    'id'     => 'child1',

                    // same name on model
                    'status' => 'status',

                    // getter on model
                    'number' => 'number',
                ],
                [
                    'id'     => 'child2',
                    'status' => 'status',
                    'number' => 'number',
                ],
            ]
        );
    }

    public function testStructureRemoveHelper()
    {
        $transformer = new class extends SceneTransformer
        {
            protected function getStructure()
            {
                return [
                    'id',
                    'email'  => $this->when(false, 'email'),
                    'valid'  => $this->when(true, 'name'),
                    'valid2' => $this->when(true, function () {
                        return 'name';
                    }),
                ];
            }
        };

        $this->assertTransformation(
            $transformer,
            $this->personsDbCollection(),
            [
                [
                    'id'     => 1,
                    'valid'  => 'Azaan',
                    'valid2' => 'Azaan',
                ],
                [
                    'id'     => 2,
                    'valid'  => 'John Doe',
                    'valid2' => 'John Doe',
                ],
            ]
        );
    }

    public function testToArrayNotCalledWhenDoingModelTransformation()
    {
        $arr = head($this->personsArray());
        $mockObject = $this->getMockBuilder(Person::class)
            ->setConstructorArgs([$arr])
            ->setMethods(['toArray'])
            ->getMock();

        $mockObject
            ->expects($this->never())
            ->method('toArray');

        $this->assertSimpleTransformer(
            new \Illuminate\Database\Eloquent\Collection([$mockObject]),
            [
                [
                    'id'   => 1,
                    'name' => 'Azaan',
                ],
            ],
            [
                'id',
                'name',
            ]
        );
    }
}
