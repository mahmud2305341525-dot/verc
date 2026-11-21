<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Serverless replacement for `php artisan schedule:run`.
 *
 * Vercel has no long-running process, so an external trigger (GitHub Actions,
 * cron-job.org, or Vercel Cron on a paid plan) calls:
 *
 *   GET /internal/cron/{group}     Authorization: Bearer <CRON_SECRET>
 *
 * Groups map 1:1 to the schedule in routes/console.php.
 * Running commands by group (instead of schedule:run) means a late or skipped
 * trigger never causes a "daily at 18:00" job to be missed.
 */
class CronController extends Controller
{
    private const GROUPS = [
        'frequent' => ['safety:check-timers', 'trips:check-stale'],   // every 1-5 min
        'hourly' => ['trips:purge'],
        'daily' => ['safety:recalculate', 'events:notify-upcoming'],
    ];

    public function __invoke(Request $request, string $group): JsonResponse
    {
        $secret = (string) env('CRON_SECRET', '');
        $given = (string) $request->bearerToken();

        if ($secret === '' || ! hash_equals($secret, $given)) {
            abort(403);
        }

        $commands = self::GROUPS[$group] ?? abort(404);
        $results = [];

        foreach ($commands as $command) {
            try {
                $exit = Artisan::call($command);
                $results[$command] = ['exit' => $exit, 'output' => trim(Artisan::output())];
            } catch (\Throwable $e) {
                report($e);
                $results[$command] = ['exit' => 1, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['group' => $group, 'results' => $results]);
    }
}
