# Laravel Scene
Laravel library to help transform your Models and to API responses.

# Installation

Install using composer.

```
composer require azaan/laravel-scene
```

# Usage

Create a transformer class to transform your model. You can use the same transform method to transform an array/collection of objects or a single object.

Example.
```php
class PersonTransformer extends SceneTransformer {
    /**
     * Structure transformations.
     *
     * @return array structure
     */
    protected function getStructure()
    {
        return [
            'id',
            'name',
            'email',
            'fullname',
            'created_at' => new DateFormatTransform('Y-m-d'),
        ];
    }

    protected function getFullname(Person $person)
    {
        return $person->first_name . ' ' . $person->last_name;
    }
}
```

Now in your controller method.

```php
    public function all()
    {
        $people = $this->peopleService->getAllPeople();

        $transformer = new PeopleTransformer();
        return SceneResponse::respond($leaves, $transformer);
    }
```

You can use the `SceneRespond::respond` method with either a collection of data or a single object. The transformer will handle it appropriately. It can also handle a `LengthAwarePaginator`.

In this example it is assumed your model (`Person`) has the attributes id, name, email and created_at. For the field `fullname` the method `getFullname` is used to resolve the value.

Keys are resolved using the following steps:
1. If a getter method exists in the transformer it is called
2. Check if the key exists on the object
3. Check if a getter method for the key exists on the object
4. Returns `null`

### Nesting Transformers

You can nest transformers within another transformer. For example if you had a `Company` model with a relation called owner which resolves to a `Person` object your `Company` transformer can look like this.
```php
class CompanyTransformer extends SceneTransformer {
    /**
     * Structure transformations.
     *
     * @return array structure
     */
    protected function getStructure()
    {
        return [
            'id',
            'name',
            'owner' => new PersonTransformer()
        ];
    }
}
```

This way your transform logic for every model is compartmentalised and can easily be reused. 

## Hooks

Several hook methods are defined which you can override to get desired behaviour.

### Injecting from Laravel Container
If you need to inject classes from the laravel container you can override the method `inject`. All parameters of the `inject` method will be resolved from the Laravel Container.  

### Minimum Structure
When you're responding with a collection of objects (Eg: for a listing) you might want to respond with different fields. You can use this by overriding the method `getMinStructure()`. In this case when you're instantiating the transformer in the controller use the method `::createMinTransformer()`

### Preload Eloquent relations
When responding with a collection of eloquent objects and accessing relations which haven't been loaded it will lead to the N+1 query problem. You can override the method `getPreloadRelations` and return an array of relations to be preloaded by the transformer. The transformer will take care of only loading the relations which haven't been loaded and will load them whenever appropriate. You can also use dot notation to preload nested relations. (Eg: `person.address`)

### Pre process
Override the methods `preProcessSingle` and `preProcessCollection` to run any pre process logic before the transformer starts transforming the objects.

### Null State
Override the method `getNullState` to return the default state if the object is null. Default implementation returns `null`

### Ordering
Usually it is better to do your ordering before passing into the transformer. However in cases where you require to order by a field which is only present after the transformation you can override `getOrderBy` to provide ordering. You should return the field name as a string to order by. If you require direction return an array. (Eg: `['field_name', 'DESC']`)

### Post process hook.
To do any changes __after__ all transformations you can override the method `transformObject`. The transformed output array will be passed in as the first argument and original object as the second.

# Licence
MIT
