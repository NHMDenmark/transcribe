@extends('layouts.app')

@section('content')

<div class="container">
	<div id="flash-message" class="alert d-none">
	</div>

@auth
@if(Auth::user())

@include('projects.form')

@endif
@endauth

</div>

@endsection