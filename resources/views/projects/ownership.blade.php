{{-- Who created and who manages a project; only shown to people who can manage it. --}}
<?php
$creator = $project->creator;
$other_managers = $project->managers()->orderBy('name')->pluck('name')->all();
?>
<p class="text-muted small mb-2">
    {!! get_dialogs('Created by', $active_language) !!}:
    @if ($creator)
    <strong>{{ $creator->name }}</strong> <span>({{ $creator->email }})</span>
    @else
    <em>{!! get_dialogs('a user who no longer exists', $active_language) !!}</em>
    @endif
    @if ($project->created_at)
    · {{ $project->created_at->format('Y-m-d') }}
    @endif
    @if (count($other_managers))
    <br>{!! get_dialogs('Also managed by', $active_language) !!}: {{ implode(', ', $other_managers) }}
    @endif
</p>
