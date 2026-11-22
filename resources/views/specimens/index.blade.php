@extends( 'layouts.app' )

@section( 'content' )
	<h1>@lang('Specimens')</h1>
	<div class="row">

		@if(count($specimens) > 0) @foreach($specimens as $specimen)
		<div class="col-sm-6 col-md-4">
			<div class="thumbnail bg-white">
<?php
$link = 'specimens/'.$specimen->id;
if($specimen['fieldtosubmit'] != '')
{
	$link .= '/'.$specimen['fieldtosubmit'];
}
?>
				<a href="{{$link}}">
					<div class="caption">
						<h3>{{$specimen->id}}</h3>
						<p>{{$specimen->Collection->CollectionName}}</p>
					</div>
					<img src="images/COI000{{$specimen->id}}.jpg" style="height: 500px">
				</a>
			</div>
		</div>
		@endforeach
	</div>


		{{$specimens->links()}}


@else
<p>No specimens found</p>
@endif
@endsection