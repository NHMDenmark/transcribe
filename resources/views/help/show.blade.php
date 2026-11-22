@extends('layouts.app')

@section('content')
<style>
    .help-content h1 { font-size: 1.9rem; margin-bottom: 1rem; }
    .help-content h2 { font-size: 1.4rem; margin: 2.2rem 0 .8rem; padding-top: .4rem; border-top: 1px solid var(--bs-border-color, #dee2e6); }
    .help-content h3 { font-size: 1.1rem; margin-top: 1.4rem; }
    .help-content pre { background: #f1f3f5; border-radius: .375rem; padding: .75rem 1rem; overflow-x: auto; }
    .help-content code { font-size: .875em; }
    .help-content table { font-size: .9rem; }
    .help-content .help-anchor { display: none; }
    .help-content h2, .help-content h3 { scroll-margin-top: 5rem; }
</style>
<div class="container">
    <div class="row mt-3">
        <div class="col-md-3 mb-3">
            <div class="list-group">
                @foreach($pages as $key => $title)
                    <a href="{{ url('help/' . $key) }}" class="list-group-item list-group-item-action @if($key === $current) active @endif">{!! get_dialogs($title, $active_language) !!}</a>
                @endforeach
            </div>
        </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-body help-content">
                    {!! $html !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
