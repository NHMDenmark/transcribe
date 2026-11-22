@extends('layouts.app')

@section('content')
<div class="container">
    <p><a href="{{ url('sync-runs' . ($run->project_id ? '?project=' . $run->project_id : '')) }}">&larr; {!! get_dialogs('Sync history', $active_language) !!}</a></p>
    <h1>{{ $run->project->name ?? '—' }} <small class="text-muted">{{ $run->started_at->format('Y-m-d H:i:s') }}</small></h1>

    <p>
        @if ($run->status === 'failed') <span class="badge bg-danger">{{ get_dialogs_simple('Failed', $active_language) }}</span>
        @elseif ($run->status === 'running') <span class="badge bg-secondary">{{ get_dialogs_simple('Running', $active_language) }}</span>
        @else <span class="badge bg-success">{{ get_dialogs_simple('Finished', $active_language) }}</span>
        @endif
        {{ $run->message }}
    </p>
    <p class="text-muted">
        @if ($run->trigger === 'auto') {!! get_dialogs('Started by the schedule.', $active_language) !!}
        @elseif ($run->trigger === 'command') {!! get_dialogs('Started from the command line.', $active_language) !!}
        @else {!! get_dialogs('Started by', $active_language) !!} {{ $run->user->name ?? get_dialogs_simple('a person', $active_language) }}.
        @endif
        @if ($run->seconds() !== null) {{ $run->seconds() }} {!! get_dialogs('seconds', $active_language) !!}. @endif
    </p>

    <h2 class="h5">{!! get_dialogs('What it did', $active_language) !!}</h2>
    <pre class="border rounded p-3" style="white-space: pre-wrap; max-height: 70vh; overflow: auto; font-size: .8rem">{{ $run->log }}</pre>
</div>
@endsection
