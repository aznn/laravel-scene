<?php

namespace Azaan\LaravelScene;


use Azaan\LaravelScene\Contracts\Transformer;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Support\Facades\Response;

class SceneResponse
{
    /**
     * Respond a json response
     *
     * @param mixed       $data
     * @param Transformer $transformer               an optional transformer
     * @param array       $extraFields               extra objects to be added to response. The key values
     *                                               passed in are added to the response. If a transformer is
     *                                               required pass it in the format ['key' => [$arr, $transformer]]
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function respond($data, Transformer $transformer = null, $extraFields = null)
    {
        $out = [];

        if ($data instanceof LengthAwarePaginator) {
            $out['pagination'] = static::makePager($data);
            $data              = $data->getCollection();
        }

        // transform data if a transformer is given
        if ($transformer != null) {
            $data = $transformer->transform($data);
        }

        $out['data'] = $data;

        if ($extraFields != null) {
            foreach ($extraFields as $key => $value) {
                if (isset($value[1]) && $value[1] instanceof Transformer) {
                    $out[$key] = $value[1]->transform($value[0]);
                } else {
                    $out[$key] = $value;
                }
            }
        }

        return Response::json($out);
    }

    /**
     * Make pager data from a length aware paginator
     *
     * @param LengthAwarePaginator $paginatedData
     * @return array
     */
    private static function makePager(LengthAwarePaginator $paginatedData)
    {
        $pager = [
            'total'        => $paginatedData->total(),
            'per_page'     => $paginatedData->perPage(),
            'current_page' => $paginatedData->currentPage(),
            'last_page'    => $paginatedData->lastPage(),
        ];

        return $pager;
    }
}
