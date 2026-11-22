<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Specimen;
use App\Models\Validate_fields;
use App\Services\ConflictComparison;
use Illuminate\Support\Facades\Gate;

/**
 * Screen for validated records the sync flagged as a conflict (status 4):
 * Specify already held a different value. Whoever may resolve conflicts for the
 * project either approves overwriting it (status 6, then the sync runs for that
 * record straight away) or keeps what Specify has (status 5). What Specify holds
 * is always read live, never stored.
 */
class ConflictsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function record($id): Validate_fields
    {
        $record = Validate_fields::with('field.mapping')->findOrFail($id);
        $project = $record->project_id ? Project::find($record->project_id) : null;

        // a record with no project is nobody's to resolve but an admin's
        $allowed = $project ? Gate::allows('resolve-conflicts', $project) : auth()->user()->isAdmin();
        abort_unless($allowed, 403);

        return $record;
    }

    public function show($id, ConflictComparison $comparison)
    {
        $record = $this->record($id);
        $project = $record->project_id ? Project::find($record->project_id) : null;

        $specimen = Specimen::where('specimen_id', $record->specimen_id)
            ->where('project_id', $record->project_id)
            ->first();
        $images = array_values(array_filter(explode('|', $specimen->images ?? '')));

        $live = (int) $record->status === 4
            ? $comparison->compare($record, $project)
            : ['root' => null, 'rootUrl' => null, 'rows' => []];

        return view('conflicts.show', [
            'record' => $record,
            'project' => $project,
            'images' => $images,
            'rows' => $live['rows'],
            'rootUrl' => $live['rootUrl'],
            'specifyLoaded' => $live['root'] !== null,
        ]);
    }

    public function overwrite($id)
    {
        $record = $this->record($id);
        abort_unless((int) $record->status === 4, 409);

        $record->status = 6;
        $record->error = null;
        $record->save();

        // Run the existing sync for exactly this record right now (its log is discarded);
        // status 6 makes it skip the conflict check.
        ob_start();
        try {
            app(ApiController::class)->ingest_validated_nested(request(), $record->id);
        } finally {
            ob_end_clean();
        }

        $record = $record->fresh();
        if ((int) $record->status === 2) {
            return redirect('/validated?status=4')->with('success', 'Overwritten in Specify');
        }

        $message = (int) $record->status === 3
            ? 'The sync failed: ' . e($record->error)
            : 'The sync did not finish; the record is queued for the next run.';

        return redirect('/validated?status=' . ((int) $record->status === 3 ? 3 : 6))->with('error', $message);
    }

    public function keep($id)
    {
        $record = $this->record($id);
        abort_unless((int) $record->status === 4, 409);

        $record->status = 5;
        $record->error = null;
        $record->save();

        return redirect('/validated?status=4')->with('success', 'Kept the value already in Specify');
    }
}
