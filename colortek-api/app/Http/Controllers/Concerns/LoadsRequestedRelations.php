<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait LoadsRequestedRelations
{
    /**
     * @param  list<string>  $allowed
     */
    protected function loadRequestedRelations(Request $request, Model $model, array $allowed): Model
    {
        $requested = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $request->query('relations', '')),
        )));

        $toLoad = array_values(array_intersect($requested, $allowed));
        if ($toLoad !== []) {
            $model->load($toLoad);
        }

        return $model;
    }
}
