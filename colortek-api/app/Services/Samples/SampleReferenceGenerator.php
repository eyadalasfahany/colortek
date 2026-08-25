<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Client;
use App\Models\Formula;
use App\Models\Project;
use App\Models\Sample;

final class SampleReferenceGenerator
{
    public function forSample(?Project $project, Client $client): string
    {
        if ($project !== null) {
            $prefix = $project->reference;
            $count = Sample::query()->where('project_id', $project->id)->count() + 1;

            return sprintf('%s-S%d', $prefix, $count);
        }

        $count = Sample::query()->where('client_id', $client->id)->whereNull('project_id')->count() + 1;

        return sprintf('CL-%d-S%d', $client->id, $count);
    }

    public function forFormula(Sample $sample): string
    {
        $version = Formula::query()->where('sample_id', $sample->id)->count() + 1;

        return sprintf('%s-F%d', $sample->reference, $version);
    }

    public function nextSampleReference(Sample $parent): string
    {
        $project = $parent->project;
        if ($project !== null) {
            $count = Sample::query()->where('project_id', $project->id)->count() + 1;

            return sprintf('%s-S%d', $project->reference, $count);
        }

        $count = Sample::query()
            ->where('client_id', $parent->client_id)
            ->whereNull('project_id')
            ->count() + 1;

        return sprintf('CL-%d-S%d', $parent->client_id, $count);
    }
}
