@extends('layouts.app')

@section('content')
<style>
    .page-body img { max-width: 100%; height: auto; }
    .page-body-wrap { position: relative; }
    body.dialog-edit-mode .page-body-wrap > .dialog-edit-trigger { position: absolute; top: -.2rem; right: 0; margin: 0; }
</style>
<div class="container">

    @if(count($chain) > 1)
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            @foreach($chain as $step)
                @if($loop->last)
                    <li class="breadcrumb-item active" aria-current="page">{{ $nav->label($step, $active_language) }}</li>
                @elseif($step['type'] === 'group')
                    <li class="breadcrumb-item">{{ $nav->label($step, $active_language) }}</li>
                @else
                    <li class="breadcrumb-item"><a href="{{ $nav->url($step) }}">{{ $nav->label($step, $active_language) }}</a></li>
                @endif
            @endforeach
        </ol>
    </nav>
    @endif

    {{-- root/admin can see what visitors cannot (drafts, scheduled, archived, empty...) --}}
    @if($canEdit && in_array($state, \App\Services\SiteNavigation::NOTICE_STATES, true))
    <div class="alert alert-warning">
        <strong>{!! get_dialogs('Not public yet', $active_language) !!}:</strong>
        {{ $state }}.
        {!! get_dialogs('Only root and administrators see this page the way it is now.', $active_language) !!}
    </div>
    @endif

    {{-- one content: its title is the page title; several: the item's name is, and each content has its own --}}
    @if(count($contents) !== 1)
        <h1>{{ $nav->label($node, $active_language) }}{!! dialog_edit_trigger('navigation_name_' . $node['id']) !!}</h1>
    @endif

    @if($canEdit)
        <div class="mb-3 text-end">
            <a class="btn btn-outline-primary btn-sm" href="{{ url('contents/create?navigation_id=' . $node['id'] . '&back=page') }}"><i class="bi bi-file-earmark-plus"></i> {!! get_dialogs('New content here', $active_language) !!}</a>
        </div>
    @endif

    @forelse($contents as $content)
        <article class="mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3">
                @if(count($contents) === 1)
                    <h1>{{ $content['title'] }}{!! dialog_edit_trigger('content_title_' . $content['id']) !!}</h1>
                @else
                    <h2>{{ $content['title'] }}{!! dialog_edit_trigger('content_title_' . $content['id']) !!}</h2>
                @endif
                @if($canEdit)
                    <a class="btn btn-outline-secondary btn-sm text-nowrap" href="{{ url('contents/' . $content['id'] . '/edit?back=page') }}"><i class="bi bi-pencil"></i> {!! get_dialogs('Edit', $active_language) !!}</a>
                @endif
            </div>
            @if($canEdit && in_array($content['state'], \App\Services\SiteNavigation::NOTICE_STATES, true))
                <p><span class="badge text-bg-warning">{{ $content['state'] }}</span></p>
            @endif
            {{-- the text is HTML: its pencil opens the rich editor, and sits at the top right of the text --}}
            <div class="page-body-wrap">
                <div class="page-body">{!! $content['body'] !!}</div>
                {!! dialog_edit_trigger('content_body_' . $content['id'], true) !!}
            </div>
        </article>
    @empty
        <p class="text-muted">{!! get_dialogs('There is nothing here yet.', $active_language) !!}</p>
    @endforelse
</div>
@endsection
