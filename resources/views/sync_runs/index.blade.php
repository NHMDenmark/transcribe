@extends('layouts.app')

@section('content')
<div class="container-fluid px-lg-4">
    <h1>{!! get_dialogs('Sync history', $active_language) !!}</h1>
    <p class="text-muted">{!! get_dialogs('Every run of the sync with the collection database, automatic or started by a person. Open a run to read what it did.', $active_language) !!}</p>

    <form method="GET" action="{{ url('sync-runs') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="project" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ get_dialogs_simple('All projects', $active_language) }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" {{ (int) request('project') === $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">{{ get_dialogs_simple('Any result', $active_language) }}</option>
                <option value="ok" {{ request('status') === 'ok' ? 'selected' : '' }}>{{ get_dialogs_simple('Finished', $active_language) }}</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ get_dialogs_simple('Failed', $active_language) }}</option>
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle" style="font-size: .85rem">
            <thead>
                <tr>
                    <th>{!! get_dialogs('Started', $active_language) !!}</th>
                    <th>{!! get_dialogs('Project', $active_language) !!}</th>
                    <th>{!! get_dialogs('By', $active_language) !!}</th>
                    <th>{!! get_dialogs('Result', $active_language) !!}</th>
                    <th class="text-end">{!! get_dialogs('Sent', $active_language) !!}</th>
                    <th class="text-end">{!! get_dialogs('Conflicts', $active_language) !!}</th>
                    <th class="text-end">{!! get_dialogs('Errors', $active_language) !!}</th>
                    <th class="text-end">{!! get_dialogs('Still waiting', $active_language) !!}</th>
                    <th class="text-end">{!! get_dialogs('Seconds', $active_language) !!}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td><a href="{{ url('sync-runs/' . $run->id) }}">{{ $run->started_at->format('Y-m-d H:i:s') }}</a></td>
                        <td>{{ $run->project->name ?? '—' }}</td>
                        <td>
                            @if ($run->trigger === 'auto') {{ get_dialogs_simple('Automatic', $active_language) }}
                            @elseif ($run->trigger === 'command') {{ get_dialogs_simple('Command line', $active_language) }}
                            @else {{ $run->user->name ?? get_dialogs_simple('A person', $active_language) }}
                            @endif
                        </td>
                        <td>
                            @if ($run->status === 'failed') <span class="badge bg-danger">{{ get_dialogs_simple('Failed', $active_language) }}</span>
                            @elseif ($run->status === 'running') <span class="badge bg-secondary">{{ get_dialogs_simple('Running', $active_language) }}</span>
                            @else <span class="badge bg-success">{{ get_dialogs_simple('Finished', $active_language) }}</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $run->sent }}</td>
                        <td class="text-end">{{ $run->conflicts }}</td>
                        <td class="text-end">{{ $run->errors }}</td>
                        <td class="text-end">{{ $run->pending }}</td>
                        <td class="text-end">{{ $run->seconds() ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">{!! get_dialogs('Nothing has been sent yet.', $active_language) !!}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $runs->links() }}
</div>
@endsection
