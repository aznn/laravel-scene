<?php

namespace Azaan\LaravelScene;

use Azaan\LaravelScene\Contracts\Transformer;
use Azaan\LaravelScene\Contracts\ValueTransformation;
use Azaan\LaravelScene\Exceptions\InvariantViolationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as DbCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;


abstract class SceneTransformer implements Transformer
{
    public $showMin = false;

    /**
     * Use the download structure
     *
     * @var bool
     */
    public $useDownloadStructure = false;

    /**
     * Create a min transformer instance
     *
     * @return static
     */
    public static function createMinTransformer()
    {
        $transformer          = new static;
        $transformer->showMin = true;

        return $transformer;
    }

    /**
     * Create a download transformer instance
     *
     * @return static
     */
    public static function createDownloadStructure()
    {
        $transformer                       = new static;
        $transformer->useDownloadStructure = true;

        return $transformer;
    }

    /**
     * Structure transformations.
     *
     * @return array structure
     */
    abstract protected function getStructure();

    /**
     * Get structure call for a min transformation
     *
     * @return array
     */
    protected function getMinStructure()
    {
        return $this->getStructure();
    }

    /**
     * Get download structure
     *
     * @return array
     */
    protected function getDownloadStructure()
    {
        return $this->getStructure();
    }

    /**
     * Hook to do any pre processing
     *
     * @param $obj
     *
     * @return mixed
     */
    protected function preProcessSingle($obj)
    {
        // default is a no-op
        return $obj;
    }

    /**
     * Pre process a collection as a whole if the transformer is used to transform
     * a collection
     *
     * @param Collection $collection
     *
     * @return Collection
     */
    protected function preProcessCollection(Collection $collection)
    {
        // default is a no-op
        return $collection;
    }

    /**
     * Get the object to return when the input is null
     *
     * @return mixed
     */
    protected function getNullState()
    {
        return null;
    }

    /**
     * Return an array of relations to preload if a database collection is passed in to transform
     *
     * @return array|string[]
     */
    public function getPreloadRelations()
    {
        return [];
    }

    /**
     * Get an order by clause. If set result set will be ordered after being transformed
     *
     * Return:
     *  'field_name',
     *  ['field_name', 'DESC']
     *
     * @return null|array|string
     */
    protected function getOrderBy()
    {
        return null;
    }

    /**
     * Transform the given data structure.
     *
     * This is the entry point for transformations
     *
     * If an iterable is given every item will be individually transformed.
     *
     * @param $data
     *
     * @return array|Collection
     * @throws Exceptions\InvariantViolationException
     */
    public function transform($data)
    {
        $this->injectDependencies();

        if ($data instanceof DbCollection || $data instanceof Model) {
            $preloadRelations = $this->getPreloadRelations();
            if (! empty($preloadRelations)) {

                $toLoad = [];
                foreach ($preloadRelations as $key => $value) {

                    if (is_numeric($key)) {
                        $relation = $value;
                    } else {
                        // key is not numeric. Load relation if value is truthy.
                        // this allows for cases like 'createdBy' => !$this->showMin
                        if (!$value) {
                            continue;
                        }

                        $relation = $key;
                    }

                    if (! Helpers::isRelationLoaded($relation, $data)) {
                        $toLoad[] = $relation;
                    }
                }

                if (! empty($toLoad)) {
                    $data->loadMissing($toLoad);
                }
            }
        }

        if (Helpers::isSequentialArray($data) || $data instanceof Collection) {
            if (! $data instanceof Collection) {
                $data = collect($data);
            }

            // collection pre process hook
            $data = $this->preProcessCollection($data);

            $result = $data->map(function ($object) {
                return $this->transformOneHelper($object);
            });

            // add order by clause
            $orderBy = $this->getOrderBy();
            if ($orderBy != null) {
                if (is_array($orderBy)) {
                    $orderField = $orderBy[0];
                    $orderType  = $orderBy[1];
                } else {
                    $orderField = $orderBy;
                    $orderType  = 'ASC';
                }

                $result = ($orderType == 'DESC') ? $result->sortByDesc($orderField) : $result->sortBy($orderField);
            }

            return $result->values()->toArray();

        } else {
            return $this->transformOneHelper($data);
        }
    }

    /**
     * Helper function which transforms a single object
     *
     * @param $object
     *
     * @return array
     * @throws InvariantViolationException
     */
    private function transformOneHelper($object)
    {
        if ($object == null) {
            return $this->getNullState();
        }

        // single object pre process hook
        $original = $this->preProcessSingle($object);

        if ($original == null) {
            throw new InvariantViolationException("Transformer preProcess returned null");
        }

        if ($object instanceof Arrayable) {
            $object = $object->toArray();
        }

        if (! is_array($object)) {
            $object = (array) $object;
        }

        // do the transformations
        if ($this->useDownloadStructure) {
            $structure = $this->getDownloadStructure();
        } else {
            $structure = $this->showMin ? $this->getMinStructure() : $this->getStructure();
        }

        $transformed = $this->structureTransformationHelper($object, $structure, $original);

        if ($original != null) {
            return $this->transformObject($transformed, $original);
        }

        return $transformed;
    }

    /**
     * Given an object does any transformations. This function is called after applying
     * structure transformations.
     *
     * @param array $object   object to apply the transformations on
     * @param mixed $original original object without any transformations
     *
     * @return array transformed object
     */
    protected function transformObject(array $object, $original)
    {
        // dummy implementation. Should be overridden
        // by child classes for extra logic
        return $object;
    }

    /**
     * Helper function which does structure transformations
     *
     * @param array $object    object to perform on
     * @param array $structure structure rules
     * @param array $original  original object
     *
     * @return array transformed object
     * @throws InvariantViolationException
     */
    private function structureTransformationHelper($object, $structure, $original)
    {
        if (empty($structure) || empty($object)) {
            return [];
        }

        $out = [];

        foreach ($structure as $key => $value) {
            if ($key === '__all' || $value === '__all') {
                return $object;
            }

            // a child transformer.
            if ($value instanceof SceneTransformer) {
                $subObj = $this->getValue($key, $object, $original, true);

                // call transform with the original data
                $out[$key] = $value->transform($subObj);
                continue;
            }

            // simple value transformation
            if ($value instanceof ValueTransformation) {
                $subObj = $this->getValue($key, $object, $original);

                if (Helpers::isSequentialArray($subObj)) {
                    $out[$key] = collect($subObj)
                        ->transform(function ($o) use ($value) {
                            return $value->transform($o);
                        })
                        ->toArray();
                } else {
                    $out[$key] = $value->transform($subObj);
                }

                continue;
            }

            if (is_array($value)) {

                if (array_key_exists('__flat', $value)) {
                    // for flat structures all key references are made with respect to base
                    // object instead of nested
                    unset($value['__flat']);
                    $out[$key] = $this->structureTransformationHelper($object, $value, $original);

                } else if (count($value) == 2 && $value[1] instanceof Transformer) {
                    // if the value is a two item array ['key', $transformer] then we lookup by the
                    // key and transform using the transformer
                    $out[$key] = $value[1]->transform($this->getValue($value[0], $object, $original));

                } else {

                    $newObject = $this->getValue($key, $object, $original);
                    $out[$key] = $this->structureTransformationHelper($newObject, $value, $original);
                }

            } else {
                // single value transformation

                // only the name given. This is both the source and dest key
                if (is_integer($key)) {
                    $key = $value;
                }

                $out[$key] = $this->getValue($value, $object, $original);
            }
        }

        return $out;
    }

    /**
     * Given a key and the original object get the value of the key in the object.
     * If the value is not set, check if the method getFieldName is defined in the transformer.
     * If so use that value
     *
     * @param      $key
     * @param      $object
     * @param      $original
     * @param bool $useOriginal get the value from original
     *
     * @return mixed|null
     * @throws InvariantViolationException
     */
    private function getValue($key, $object, $original, $useOriginal = false)
    {
        if (! is_string($key)) {
            throw new InvariantViolationException("Invalid key passed in to getValue. Key of type " . class_basename($key));
        }

        // if 'get' method exists call it
        $getMethod = 'get' . studly_case($key);
        if (method_exists($this, $getMethod)) {
            return call_user_func_array([$this, $getMethod], [$original]);
        }

        $lookUpObj = $useOriginal ? $original : $object;

        $defaultPlaceholder = '__DEFAULT_VALUE__';

        $result = Helpers::pathGet($lookUpObj, $key, $defaultPlaceholder);
        if ($result !== $defaultPlaceholder) {
            return $result;
        }

        // if getter exists on object call it as last resort
        if (method_exists($original, $getMethod)) {
            return call_user_func_array([$original, $getMethod], []);
        }

        return null;
    }

    /**
     * If a inject function is defined in base class inject the dependencies
     */
    private function injectDependencies()
    {
        if (! method_exists($this, 'inject')) {
            return;
        }

        $container = Container::getInstance();

        $reflector  = new \ReflectionClass(static::class);
        $method     = $reflector->getMethod('inject');
        $parameters = $method->getParameters();

        // make the required parameters
        $buildParams = [];
        foreach ($parameters as $parameter) {
            $buildParams[] = $container->make($parameter->getClass()->name);
        }

        call_user_func_array([$this, 'inject'], $buildParams);
    }
}
