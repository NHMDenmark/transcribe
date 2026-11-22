@extends('layouts.app')

@section('content')
@php $editing = $block->exists; @endphp
<div class="container">
    <h1>
        @if($editing) {!! get_dialogs('Edit footer block', $active_language) !!}
        @elseif($type === 'links') {!! get_dialogs('New links block', $active_language) !!}
        @else {!! get_dialogs('New text block', $active_language) !!}
        @endif
    </h1>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $editing ? url('footer/' . $block->id) : url('footer') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        @unless($editing)<input type="hidden" name="type" value="{{ $type }}">@endunless

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="width" class="form-label">{!! get_dialogs('Width', $active_language) !!}</label>
                        <select name="width" id="width" class="form-select">
                            @foreach($widths as $w)
                                <option value="{{ $w }}" @selected((int) old('width', $block->width ?: 4) === $w)>{{ $w }}/12 @if($w === 12) ({{ get_dialogs_simple('the whole row', $active_language) }}) @endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active', $editing ? $block->active : true))>
                            <label class="form-check-label" for="active">{!! get_dialogs('Shown', $active_language) !!}</label>
                        </div>
                    </div>

                    @if($type === 'links')
                    <div class="col-12">
                        <label class="form-label d-block">{!! get_dialogs('Menu items to link to', $active_language) !!}</label>
                        @foreach($nodes as $node)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="nodes[]" id="node-{{ $node['id'] }}" value="{{ $node['id'] }}" @checked(in_array($node['id'], array_map('intval', (array) old('nodes', $chosen)), true))>
                                <label class="form-check-label" for="node-{{ $node['id'] }}">{{ $node['label'] }}</label>
                            </div>
                        @endforeach
                        <div class="form-text">{!! get_dialogs('For a link to anywhere else, create a menu item of the kind "link" and turn off "Show in the menu".', $active_language) !!}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{!! get_dialogs('Texts, by language', $active_language) !!}</div>
            <div class="card-body">
                <ul class="nav nav-tabs" role="tablist">
                    @foreach($langCodes as $language)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#lang-{{ $language }}" type="button" role="tab">{{ $language }}</button>
                        </li>
                    @endforeach
                </ul>
                <div class="tab-content pt-3">
                    @foreach($langCodes as $language)
                    <div class="tab-pane fade @if($loop->first) show active @endif" id="lang-{{ $language }}" role="tabpanel">
                        <div class="mb-3">
                            <label class="form-label" for="title-{{ $language }}">{!! get_dialogs('Title', $active_language) !!}</label>
                            <input type="text" id="title-{{ $language }}" name="titles[{{ $language }}]" class="form-control" value="{{ old('titles.' . $language, strip_tags($titles[$language] ?? '')) }}">
                        </div>
                        @if($type === 'text')
                        <div class="mb-3">
                            <label class="form-label" for="body-{{ $language }}">{!! get_dialogs('Text', $active_language) !!}</label>
                            <textarea id="body-{{ $language }}" name="bodies[{{ $language }}]" class="form-control ckeditor" rows="8">{{ old('bodies.' . $language, $bodies[$language] ?? '') }}</textarea>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
                <div class="form-text">{!! get_dialogs('A language left empty shows the default language instead.', $active_language) !!}</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">{!! get_dialogs('Save', $active_language) !!}</button>
        <a href="{{ url('footer') }}" class="btn btn-link">{!! get_dialogs('Cancel', $active_language) !!}</a>
    </form>
</div>
@endsection
