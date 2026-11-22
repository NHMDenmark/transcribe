<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SyncRun;
use Illuminate\Http\Request;

/** The history of the sync with the collection database: every run, automatic or manual, and what it did (route middleware run-sync). */
class SyncRunsController extends Controller
{
    public function index(Request $request)
    {
        $runs = SyncRun::with(['project', 'user'])
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', (int) $request->query('project')))
            ->when(in_array($request->query('status'), ['ok', 'failed'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('sync_runs.index', ['runs' => $runs, 'projects' => Project::orderBy('name')->get(['id', 'name'])]);
    }

    public function show(SyncRun $run)
    {
        return view('sync_runs.show', ['run' => $run->load(['project', 'user'])]);
    }
}
