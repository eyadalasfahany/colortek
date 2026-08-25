<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Client;
use App\Models\Project;
use App\Models\Sample;

final class SampleReferenceGenerator
{
    public function forSample(?Project $project, Client $client): string
    {
        if ($project !== null) {
            $seq = Sample::query()->where('project_id', $project->id)->count() + 1;

            return sprintf('%s-S%d', $project->reference, $seq);
        }

        $seq = Sample::query()->where('client_id', $client->id)->whereNull('project_id')->count() + 1;

        return sprintf('CL-%d-S%d', $client->id, $seq);
    }

    public function forFormula(Sample $sample, int $version): string
    {
        return sprintf('%s-F%d', $sample->reference, $version);
    }
}
