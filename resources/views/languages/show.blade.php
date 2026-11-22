@extends('layouts.app')

@section('content')
<div class="container">
    <a href="{{ url('/languages') }}" class="btn btn-outline-secondary">{!! get_dialogs('Go Back', $active_language) !!}</a>
    <h1>{{$language->text}}</h1>
    <br><br>
    <hr>
    <small>{!! get_dialogs('Written on', $active_language) !!} {{$language->created_at}} {!! get_dialogs('by', $active_language) !!} {{$language->user->name}}</small>
    <hr>
    @if(!Auth::guest())
        @if(Auth::user()->id == $language->user_id)
            <a href="{{ url('/languages/'.$language->id.'/edit') }}" class="btn btn-primary">{!! get_dialogs('Edit', $active_language) !!}</a>

            <form method="POST" action="{{ url('/languages/'.$language->id) }}" class="d-inline float-end">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">{!! get_dialogs('Delete', $active_language) !!}</button>
            </form>
        @endif
    @endif
</div>
@endsection
