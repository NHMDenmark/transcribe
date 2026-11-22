@extends( 'layouts.app' )

@section( 'content' )
<div class="container">
	<div class="row justify-content-center">
		<div class="album py-2">
			<div class="col float-end">
				@include('projects.controls')
			</div>
		</div>
	</div>
</div>
<div class="container">
		@include('projects.form')
</div>
@endsection