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
     * When given as a value in preload relations sub relations will be loaded from the child transformer too.
     *
     * Eg: preloading 'user' will preload whatever relations required by the trasformer associated to the 'user' key
     *     in the structure
     */
    const PRELOAD_RELATED = '__PRELOAD_RELATED__';

    /**
     * If set as a structure value the key will be ignored. Used to implement
     * structure helpers
     */
    const REMOVE_KEY = '__REMOVE_KEY__';

    /**
     * Use the download structure
     *
     * @var bool
     */
    public $useDownloadStructure = false;

    /**
     * Override structure from constructor
     *
     * @var array|null
     */
    protected $structureOverride = null;

    /**
     * Override min structure from constructor
     *
     * @var array|null
     */
    protected $minStructureOverride = null;

    /**
     * Override download structure from constructor
     *
     * @var array|null
     */
    protected $downloadStructureOverride = null;

    /**
     * Cached structure
     *
     * @var null
     */
    protected $__cached_structure = null;

    /**
     * SceneTransformer constructor.
     *
     * @param null|array $structure
     * @param null|array $minStructure
     * @param null|array $downloadStructure
     */
    public function __construct($structure = null, $minStructure = null, $downloadStructure = null)
    {
        $this->structureOverride         = $structure;
        $this->minStructureOverride      = $minStructure;
        $this->downloadStructureOverride = $downloadStructure;
    }

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
        return $this->getMinStructure();
    }

    /**
     * Hook to do any pre processing
     *
     * @param $obj
     *
     * @return mixed
     */
    protected function preProcessSingle(&$obj)
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
     * Return the object when predicate is true. Otherwise mark key for deletion
     *
     * @param $predicate
     * @param $obj
     *
     * @return string
     */
    public function when($predicate, $obj = null)
    {
        if (!$predicate) {
            return static::REMOVE_KEY;
        }

        // valid
        if ($obj instanceof \Closure) {
            return $obj();
        }

        return $obj;
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
     * Figure relations to be pre load. This takes care of recursively expanding on related relations
     */
    public function figurePreloadRelations()
    {
        $preloadRelations = $this->getPreloadRelations();
        if (empty($preloadRelations)) {
            return [];
        }

        $toLoad = [];
        foreach ($preloadRelations as $key => $value) {

            if (is_numeric($key)) {
                $toLoad[] = $value;
            } else {
                // key is not numeric. Load relation if value is truthy.
                // this allows for cases like 'createdBy' => !$this->showMin
                if (!$value) {
                    continue;
                }

                if ($value === static::PRELOAD_RELATED) {
                    $structure      = $this->figureStructure();
                    $childRelations = $this->figureChildRelations(isset($structure[$key]) ? $structure[$key] : null);

                    foreach ($childRelations as $relation) {
                        $toLoad[] = "$key.$relation";
                    }
                    continue;
                }

                // add key
                $toLoad[] = $key;
            }
        }

        return $toLoad;
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

        // transform structure
        $structure = $this->figureStructure();

        if ($data instanceof DbCollection || $data instanceof Model) {
            $preloadRelations = $this->figurePreloadRelations();
            if (!empty($preloadRelations)) {
                $data->loadMissing(array_unique($preloadRelations));
            }
        }

        if (Helpers::isSequentialArray($data) || $data instanceof Collection) {
            if (!$data instanceof Collection) {
                $data = collect($data);
            }

            // collection pre process hook
            $data = $this->preProcessCollection($data);
            if (!$data instanceof Collection) {
                throw new InvariantViolationException("Transformer pre process collection should return a collection");
            }

            $result = $data->map(function ($object) use ($structure) {
                return $this->transformOneHelper($object, $structure);
            });

            // add order by clause
            $orderBy = $this->getOrderBy();
            if ($orderBy != null) {
                if (is_array($orderBy)) {
                    $orderField = $orderBy[0];
                    $orderType  = strtolower($orderBy[1]);
                } else {
                    $orderField = $orderBy;
                    $orderType  = 'asc';
                }

                $result = ($orderType == 'desc') ? $result->sortByDesc($orderField) : $result->sortBy($orderField);
            }

            return $result->values()->toArray();

        } else {
            return $this->transformOneHelper($data, $structure);
        }
    }

    /**
     * Helper function which transforms a single object
     *
     * @param       $object
     *
     * @param array $structure
     *
     * @return array
     * @throws InvariantViolationException
     */
    private function transformOneHelper($object, $structure)
    {
        if ($object == null) {
            return $this->getNullState();
        }

        // single object pre process hook
        $original = $this->preProcessSingle($object);

        if ($original == null) {
            throw new InvariantViolationException("Transformer preProcess returned null");
        }

        // do the transformations
        $transformed = $this->structureTransformationHelper($object, $structure, $original);

        if ($original != null) {
            return $this->transformObject($transformed, $original);
        }

        return $transformed;
    }

    /**
     * Figure out structure
     *
     * @return array
     */
    protected function figureStructure()
    {
        if (!empty($this->__cached_structure)) {
            return $this->__cached_structure;
        }

        if ($this->useDownloadStructure) {
            $structure = $this->downloadStructureOverride ? $this->downloadStructureOverride : $this->getDownloadStructure();

        } else if ($this->showMin) {
            $structure = $this->minStructureOverride ? $this->minStructureOverride : $this->getMinStructure();

        } else {
            $structure = $this->structureOverride ? $this->structureOverride : $this->getStructure();
        }

        return $this->__cached_structure = $structure;
    }

    /**
     * Figure preload relations
     *
     * @param $obj
     *
     * @return array
     */
    protected function figureChildRelations($obj)
    {
        if (empty($obj)) {
            return [];
        }

        if ($obj instanceof SceneTransformer) {
            return $obj->figurePreloadRelations();
        }

        // TODO: Consider case where structure value can be [newKey, Transformer]

        return [];
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

            if ($value === static::REMOVE_KEY) {
                continue;
            }

            // a child transformer.
            if ($value instanceof SceneTransformer) {
                $subObj = $this->getValue($key, $object, $original, true);

                // call transform with the original data
                $out[$key] = $value->transform($subObj);
                continue;
            }

            // simple value transformation
            if ($value instanceof Transformer) {
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
        if (!is_string($key)) {
            throw new InvariantViolationException("Invalid key passed in to getValue. Key of type " . class_basename($key));
        }

        // 1. Call get method if it exists on transformer
        $getMethod = 'get' . \Str::studly($key);
        if (method_exists($this, $getMethod)) {
            return call_user_func_array([$this, $getMethod], [$original]);
        }

        $lookUpObj = $useOriginal ? $original : $object;

        $defaultPlaceholder = '__DEFAULT_VALUE__';

        // 2. Try a path get on object
        $result = Helpers::pathGet($lookUpObj, $key, $defaultPlaceholder);
        if ($result !== $defaultPlaceholder) {
            return $result;
        }

        // 3. Call get method on object
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
        if (!method_exists($this, 'inject')) {
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
