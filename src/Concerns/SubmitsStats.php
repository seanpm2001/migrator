<?php

namespace Statamic\Migrator\Concerns;

use Exception;
use Illuminate\Support\Facades\Http;

trait SubmitsStats
{
    /**
     * Attempt submitting anonymous stats.
     *
     * @param  array  $stats
     */
    protected function attemptSubmitStats($stats)
    {
        if (env('DISABLE_MIGRATOR_STATS')) {
            return;
        }

        try {
            $stats['command'] = str_replace('statamic:', '', $stats['command']);

            Http::timeout(3)->post('https://outpost.statamic.com/v3/migrator-stats', array_merge([
                'app' => md5(base_path()),
            ], $stats));
        } catch (Exception $exception) {
            //
        }
    }
}
