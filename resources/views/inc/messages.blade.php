@if(count($errors) > 0)
@foreach($errors->all() as $error)
<div id="myToastEl" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2500" data-bs-animation="true">
	<div class="toast-header">
		<strong class="me-auto"></strong>
		<small class="text-body-secondary"></small>
		<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
	</div>
	<div class="toast-body">
		<div class="alert alert-danger">
			<?php echo get_dialogs_simple($error, $active_language); ?>
		</div>
	</div>
</div>
@endforeach
@endif

@if(session('success'))
<?php
$text = get_dialogs_simple(session('success'), $active_language);
?>
<div id="myToastEl" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2500" data-bs-animation="true">
	<div class="toast-header">
		<strong class="me-auto"></strong>
		<small class="text-body-secondary"></small>
		<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
	</div>
	<div class="toast-body">
		<div class="alert alert-success">
			{!!$text!!}
		</div>
	</div>
</div>
@endif

@if(session('error'))
<?php
$text = get_dialogs_simple(session('error'), $active_language);
?>
<div id="myToastEl" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2500" data-bs-animation="true">
	<div class="toast-header">
		<strong class="me-auto"></strong>
		<small class="text-body-secondary"></small>
		<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
	</div>
	<div class="toast-body">
		<div class="alert alert-danger">
			{!!$text!!}
		</div>
	</div>
</div>
@endif

@if(session('warning'))
<?php
$text = get_dialogs_simple(session('warning'), $active_language);
?>
<div id="myToastEl" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="2500" data-bs-animation="true">
	<div class="toast-header">
		<strong class="me-auto"></strong>
		<small class="text-body-secondary"></small>
		<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
	</div>
	<div class="toast-body">
		<div class="alert alert-warning">
			{!!$text!!}
		</div>
	</div>
</div>

@endif