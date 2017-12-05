<?php

namespace Azaan\LaravelScene;


use Azaan\LaravelScene\Exceptions\InvariantViolationException;
use Illuminate\Database\Eloquent\Collection as DbCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Helpers
{
    /**
     * Check if relation is loaded on a loadable object like a DB collection or Model.
     *
     * Default model implementation does not work with nested relations. This does
     *
     * @param                            $relation
     * @param Model|DbCollection|Model[] $obj
     *
     * @return bool
     * @throws InvariantViolationException
     */
    public static function isRelationLoaded($relation, $obj)
    {
        // relation is 'loaded' if the object is non existent. No point trying to load anyway
        if ($obj == null || empty($obj)) {
            return true;
        }

        // ensure type is correct
        if (!($obj instanceof Model || $obj instanceof DbCollection)) {
            throw new InvariantViolationException("Object of type " . get_class($obj) . " does not support relation loading");
        }

        $parts = explode('.', $relation);
        if (count($parts) !== 1) {
            $first = $parts[0];

            // get without first part
            array_shift($parts);
            $rest = implode(".", $parts);
        } else {
            $first = $parts[0];
            $rest  = null;
        }

        if ($obj instanceof Model) {

            // ensure relation is loaded on object
            if (!$obj->relationLoaded($first)) {
                return false;
            }

        } else {

            // ensure relation is loaded on all objects
            foreach ($obj as $o) {
                if (!$o->relationLoaded($first)) {
                    return false;
                }
            }

        }

        // we have sub relations. Check them
        if ($rest != null) {
            if ($obj instanceof Model) {

                // ensure relation is loaded in sub relation
                return static::isRelationLoaded($rest, $obj->$first);
            } else {

                // ensure relation is loaded in sub relations of all objects
                foreach ($obj as $o) {
                    if (!static::isRelationLoaded($rest, $o->$first)) {
                        return false;
                    }
                }
            }
        }

        // relation is loaded
        return true;
    }

    /**
     * Checks if the object given is a sequential array
     *
     * @param mixed $obj possible array object
     *
     * @return bool is it a sequential array
     */
    public static function isSequentialArray($obj)
    {
        if ($obj instanceof Collection) {
            return true;
        }

        if (is_array($obj)) {
            $i = 0;
            foreach ($obj as $key => $value) {
                if ($key !== $i) {
                    return false;
                }

                $i++;
            }

            // all indexes are integers starting from 0
            return true;
        }

        return false;
    }

    /**
     * Given a path string traverse an array object and return the value
     *
     * Supports syntax:
     * - a.b.c
     * - [0]
     * - a.b[0]
     * - a.b[2].c
     *
     * @param array  $obj     object
     * @param string $path    path
     * @param mixed  $default default value
     *
     * @return mixed
     */
    public static function pathGet($obj, $path, $default = null)
    {
        $paths = explode('.', $path);

        $object = $obj;
        foreach ($paths as $section) {
            if (empty($object)) {
                return $default;
            }

            if (strpos($section, '[') !== false) {
                // array access
                $index = (int)explode(']', explode('[', $section)[1])[0];
                $key   = explode('[', $section)[0];

                if (is_array($object)) {
                    $object = isset($object[$key]) ? $object[$key] : null;
                } else if (is_object($object)) {
                    $object = isset($object->$key) ? $object->$key : null;
                } else {
                    return null;
                }

                if (isset($object[$index])) {
                    $object = $object[$index];
                } else {
                    $object = null;
                }

            } else {
                if (is_array($object)) {
                    $object = isset($object[$section]) ? $object[$section] : null;
                } else if (is_object($object)) {
                    $object = isset($object->$section) ? $object->$section : null;
                } else {
                    return null;
                }
            }
        }

        if ($object !== null) {
            return $object;
        }

        return $default;
    }
}
