<?php

namespace App\Services;

use App\Exceptions\SyncAborted;
use App\Http\Controllers\ApiController;
use App\Models\Project;
use App\Models\SyncRun;
use App\Models\User;
use App\Models\Validate_fields;
use App\Notifications\SyncRunFailed;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Runs the sync of a project without anybody looking at a page (the scheduled sync, or `platform:sync-due --project`),
 * and keeps the history of every run, manual ones included. What the sync prints for a person to read on the manual
 * page is captured as the log of the run.
 *
 * Only what is waiting is sent (status 1, or 6 = an administrator approved overwriting). A record that fails is marked
 * as an error and is not tried again by itself, and a conflict waits for a person, exactly as on the manual page.
 */
class SyncRunner
{
    public function __construct(private ApiController $sync)
    {
    }

    /** What is waiting to be sent for a project. */
    public function pending(Project $project)
    {
        return Validate_fields::where('project_id', $project->id)->whereIn('status', [1, 6])->whereHas('field.mapping');
    }

    public function run(Project $project, string $trigger = 'auto', ?int $userId = null): SyncRun
    {
        $run = SyncRun::create([
            'project_id' => $project->id,
            'user_id' => $userId,
            'trigger' => $trigger,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $seen = [];
        $failure = null;
        $deadline = time() + (int) config('sync.auto_time_budget');

        ob_start();
        try {
            if (! config('services.specify.url')) {
                throw new SyncAborted('The connection to the collection database is not configured (API_URL).');
            }

            while (time() < $deadline) {
                $records = $this->pending($project)->whereNotIn('id', $seen)->orderBy('id')->limit((int) config('sync.auto_chunk'))->get();
                if ($records->isEmpty()) {
                    break;
                }
                $seen = array_merge($seen, $records->pluck('id')->all());

                echo '<h3>' . count($seen) . ' records so far</h3>';
                $this->sync->syncRecords($records, null, false, false);
            }
        } catch (SyncAborted $e) {
            $failure = $e->getMessage();
        } catch (\Throwable $e) {
            Log::error('Automatic sync failed', ['project' => $project->id, 'exception' => $e]);
            $failure = get_class($e) . ': ' . $e->getMessage();
        }
        $output = (string) ob_get_clean();

        $this->forgetCookieJar();

        return $this->finish($run, $project, $seen, $output, $failure);
    }

    /** Records a run made from the manual page: it processed $records, and printed $output (HTML). */
    public function recordManual(Collection $records, string $output, ?int $userId, ?string $failure = null): ?SyncRun
    {
        $first = $records->first();
        if (! $first) {
            return null;
        }

        $run = SyncRun::create([
            'project_id' => $first->project_id,
            'user_id' => $userId,
            'trigger' => 'manual',
            'status' => 'running',
            'started_at' => now(),
        ]);

        return $this->finish($run, Project::find($first->project_id), $records->pluck('id')->all(), $output, $failure, notify: false);
    }

    private function finish(SyncRun $run, ?Project $project, array $ids, string $output, ?string $failure, bool $notify = true): SyncRun
    {
        $status = fn (int $s) => Validate_fields::whereIn('id', $ids)->where('status', $s)->count();

        $run->sent = $status(2);
        $run->errors = $status(3);
        $run->conflicts = $status(4);
        $run->pending = $project ? $this->pending($project)->count() : 0;
        $run->status = $failure ? 'failed' : 'ok';
        $run->message = $failure ?: $this->summary($run);
        $run->log = $this->text($output, $failure);
        $run->finished_at = now();
        $run->save();

        if ($notify && $failure && $project) {
            $this->tellTheAdministrators($run, $project);
        }

        return $run;
    }

    private function summary(SyncRun $run): string
    {
        $parts = [$run->sent . ' sent', $run->conflicts . ' in conflict', $run->errors . ' with an error'];
        if ($run->pending) {
            $parts[] = $run->pending . ' still waiting';
        }

        return implode(', ', $parts);
    }

    /** The page's HTML as plain text, cut to the size the log may have. */
    private function text(string $html, ?string $failure): string
    {
        $text = preg_replace('/<(br|\/p|\/h[1-6]|\/tr|hr)[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));
        if ($failure) {
            $text = trim($text . "\n\nThe run stopped: " . $failure);
        }

        $limit = (int) config('sync.log_limit');

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . "\n[log cut]" : $text;
    }

    /** One message when a project's automatic sync starts to fail, not one for every run that keeps failing. */
    private function tellTheAdministrators(SyncRun $run, Project $project): void
    {
        $previous = SyncRun::where('project_id', $project->id)->where('trigger', $run->trigger)->where('id', '<', $run->id)->orderByDesc('id')->first();
        if ($previous && $previous->failed()) {
            return;
        }

        try {
            $people = User::whereIn('role', ['root', 'admin'])->whereNotNull('email')->get();
            Notification::send($people, new SyncRunFailed($run, $project));
        } catch (\Throwable $e) {
            Log::warning('The administrators could not be told that a sync failed', ['exception' => $e->getMessage()]);
        }
    }

    /** The sync keeps its login to the collection database in a file named after the session; a run has no use for it afterwards. */
    private function forgetCookieJar(): void
    {
        $file = specify_cookie_file();
        if (File::exists($file)) {
            File::delete($file);
        }
    }
}
