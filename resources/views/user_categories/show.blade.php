@extends( 'layouts.app' )

@section( 'content' )
<div class="container">
    <h1>{!! get_dialogs('User Categories', $active_language) !!}</h1>
</div>
<div class="container">
	<div id="flash-message" class="alert d-none">
	</div>

	<div class="card">
		<div class="card-body">
			<button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#user_category_Modal" title="{{ get_dialogs_simple('Add a new user category', $active_language) }}">
				<i class="bi bi-plus-lg"></i>
			</button>

			<table class="table table-striped">
				<thead>
					<tr>
						<th scope="col"></th>
						<th scope="col">{!! get_dialogs('Name', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Validations required', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Points', $active_language) !!}</th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody class="sort">

					<?php

					foreach ($user_categories as $user_category) {
					?>
						<tr id="item-{{$user_category->id}}">
							<td style="cursor:grab;"><i class="bi bi-grip-vertical"></i></td>

							<td>{!!$user_category->category!!}</td>
							<!--
						<td>{!!$user_category->question!!}</td>
						<td>{!!$user_category->text!!}</td>
					-->
							<td>{!!$user_category->validations_required!!}</td>
							<td>{!!$user_category->points!!}</td>
							<td>
								<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#user_category_Modal" data-category="{!!$user_category->category!!}" data-validations_required="{!!$user_category->validations_required!!}" data-points="{!!$user_category->points!!}" data-user_category_id="{!!$user_category->id!!}" <?php foreach ($languages as $language) {
																																																																																									?> data-user_categories_name_{{$language->iso2}}="<?php echo get_dialogs_simple('user_categories_name_' . $user_category->id, $language->iso2); ?>" data-user_categories_description_{{$language->iso2}}="{!!get_dialogs_simple('user_categories_description_'.$user_category->id, $language->iso2)!!}" <?php
																																																																																																																																																																					}
																																																																																																																																																																						?>>
									<i class="bi bi-pencil"></i>
								</button>

							</td>
							<td>
								<button type="button" class="btn btn-danger btn-sm delete_user_category" title="{{ get_dialogs_simple('Delete user category', $active_language) }}" id="{{$user_category->id}}">
									<i class="bi bi-trash"></i>
								</button>
							</td>
						</tr>
					<?php
					}
					?>
				<tfoot>
				</tfoot>
				</tbody>
			</table>
			</table>
		</div>
	</div>
</div>



<div class="modal fade" id="user_category_Modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="">{!! get_dialogs('Edit user category', $active_language) !!}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form method="POST" action="user_categories" enctype="multipart/form-data" id="user_category_edit">
					@csrf
					<div class="form-group">
						<label for="category" class="col-form-label">{!! get_dialogs('Category:', $active_language) !!}</label>
						<input type="text" class="form-control" id="category" name="category">
					</div>
					<div class="form-group">
						<label for="validations_required" class="col-form-label">{!! get_dialogs('Validations required to achieve this level:', $active_language) !!} {!! help_hint('category_validations', $active_language) !!}</label>
						<input type="text" class="form-control" id="validations_required" name="validations_required">
					</div>

					<div class="form-group">
						<label for="points" class="col-form-label">{!! get_dialogs('Points attributed to this level submissions:', $active_language) !!} {!! help_hint('category_points', $active_language) !!}</label>
						<input type="text" class="form-control" id="points" name="points">
					</div>

					<div class="form-group mt-4">

						<ul class="nav nav-tabs" id="myTab" role="tablist">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" id="user_categories_name-tab" data-bs-toggle="tab" data-bs-target="#user_categories_name-tab-pane" type="button" role="tab" aria-controls="user_categories_name-tab-pane" aria-selected="true">{!! get_dialogs('Name to display', $active_language) !!}</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="user_categories_description-tab" data-bs-toggle="tab" data-bs-target="#user_categories_description-tab-pane" type="button" role="tab" aria-controls="user_categories_description-tab-pane" aria-selected="false">{!! get_dialogs('Text', $active_language) !!}</button>
							</li>
						</ul>
						<div class="tab-content" id="myTabContent">
							<div class="tab-pane fade show active" id="user_categories_name-tab-pane" role="tabpanel" aria-labelledby="user_categories_name-tab" tabindex="0">
								<?php
								foreach ($languages as $language) {
								?>
									<div class="form-group">
										<label for="name" class="col-form-label">{{$language->iso2}}:</label>
										<textarea class="form-control ckeditor" id="user_categories_name_{{$language->iso2}}" name="user_categories_name_{{$language->iso2}}"></textarea>
									</div>
								<?php
								}
								?>
							</div>
							<div class="tab-pane fade" id="user_categories_description-tab-pane" role="tabpanel" aria-labelledby="user_categories_description-tab" tabindex="0">
								<?php
								foreach ($languages as $language) {
								?>
									<div class="form-group">
										<label for="text" class="col-form-label">{{$language->iso2}}:</label>
										<textarea class="form-control ckeditor" id="user_categories_description_{{$language->iso2}}" name="user_categories_description_{{$language->iso2}}"></textarea>
									</div>
								<?php
								}
								?>
							</div>
						</div>
					</div>

					<input type="hidden" name="user_category_id" id="user_category_id" value="">
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
				<button type="submit" class="btn btn-primary" form="user_category_edit">{!! get_dialogs('Submit', $active_language) !!}</button>
			</div>
		</div>
	</div>
</div>

<script src="{{ asset('js/ckeditor/ckeditor.js') }}"></script>

<script type="text/javascript">
	// $j is just this page's own alias for jQuery — noConflict() here released
	// the global "$" for the whole page (this page loads only CKEditor, which
	// doesn't touch "$"), breaking the layout's own scripts and the shared
	// dialog-edit modal with "$ is not a function".
	var $j = jQuery;

	$j(document).ready(function() {
		$j('#user_category_Modal').on('show.bs.modal', function(event) {
			var button = $j(event.relatedTarget); // Button that triggered the modal
			var category = button.data('category'); // Extract info from data-* attributes
			var validations_required = button.data('validations_required'); // Extract info from data-* attributes
			var points = button.data('points'); // Extract info from data-* attributes
			var user_category_id = button.data('user_category_id'); // Extract info from data-* attributes


			// If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
			// Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
			var modal = $j(this);

			modal.find('#category').val(category);
			modal.find('#validations_required').val(validations_required);
			modal.find('#points').val(points);
			modal.find('#user_category_id').val(user_category_id);

			<?php
			foreach ($languages as $language) {
			?>
				var user_categories_name_<?php echo $language->iso2; ?> = button.data('user_categories_name_{{$language->iso2}}'); // Extract info from data-* attributes
				modal.find('#user_categories_name_{{$language->iso2}}').val(user_categories_name_<?php echo $language->iso2; ?>);

				CKEDITOR.instances.user_categories_name_<?php echo $language->iso2; ?>.setData(user_categories_name_<?php echo $language->iso2; ?>);

				var user_categories_description_<?php echo $language->iso2; ?> = button.data('user_categories_description_{{$language->iso2}}'); // Extract info from data-* attributes
				modal.find('#user_categories_description_{{$language->iso2}}').val(user_categories_description_<?php echo $language->iso2; ?>);

				CKEDITOR.instances.user_categories_description_<?php echo $language->iso2; ?>.setData(user_categories_description_<?php echo $language->iso2; ?>);

			<?php
			}
			?>
		});
	})
</script>

<script type="text/javascript">
	$j(document).on('click', '.delete_user_category', function(e) {
		e.preventDefault();
		var user_category_id = this.id;
		if (!confirm(<?php echo json_encode(get_dialogs_simple('Are you sure you want to delete this item?', $active_language)); ?>)) {
			return false;
		}

		$j.ajax({
			type: "POST",
			cache: false,
			url: "{{URL('user_categories')}}" + "/" + user_category_id,
			data: {
				"_token": "{{ csrf_token() }}",
				"user_category_id": user_category_id,
				_method: 'DELETE'
			},
			success: function(data) {
				window.location.reload();
			}
		});
	});
</script>


<script type="text/javascript">
	// Sortable rows
	$j('.sort').sortable({
		cursor: 'move',
		axis: 'y',
		update: function(e, ui) {
			$j('#flash-message').removeClass('hidden');
			$j('#flash-message').addClass('alert-warning');

			$j('#flash-message').html(<?php echo json_encode(get_dialogs_simple('processing', $active_language)); ?>);

			//$j(this).sortable('refresh');
			var params = $j(this).sortable('serialize');
			$j.ajax({
				type: 'POST',
				data: {
					"_token": "{{ csrf_token() }}",
					"id": params
				},
				url: 'user_categories_sort',
				ajaxSend: function(msg) {
					// Your sorted data.
				},
				success: function(msg) {
					// Your sorted data.
					$j('#flash-message').removeClass('alert-warning');
					$j('#flash-message').addClass('alert-success');
					$j('#flash-message').html(msg.message);
				}
			});
		}
	});
</script>


@endsection