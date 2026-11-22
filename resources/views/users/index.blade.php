@extends('layouts.app')

@section('content')

<div class="container">
	<h1>{!! get_dialogs('Users', $active_language) !!}</h1>
</div>
<div class="container">
	<div id="flash-message" class="alert d-none">
	</div>

	<div class="row">
		<div class="col-md-12">
			<div class="card">
				<div class="card-body">
				<button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#user_modal" title="{{ get_dialogs_simple('Add a new user', $active_language) }}">
				<i class="bi bi-plus-lg"></i>
			</button>

					@include('layouts.partials.table_tools', ['tableId' => 'example', 'placeholder' => get_dialogs_simple('Search users (name, e-mail, role...)', $active_language)])

					<table class="table table-hover" id="example">
						<thead>
							<tr>
								<th data-sort="text">{!! get_dialogs('Role', $active_language) !!}</th>
								<th data-sort="number">{!! get_dialogs('Level', $active_language) !!}</th>
								<th data-sort="text">{!! get_dialogs('Name', $active_language) !!}</th>
								<th data-sort="text">{!! get_dialogs('E-mail', $active_language) !!}</th>
								<th data-sort="text">{!! get_dialogs('Created at', $active_language) !!}</th>
								<th data-sort="number">{!! get_dialogs('Submitted', $active_language) !!}</th>
								<th data-sort="number">{!! get_dialogs('Approved', $active_language) !!}</th>
								<th data-sort="number">{!! get_dialogs('Rejected', $active_language) !!}</th>
								<th></th>
							</tr>
						</thead>

						<tbody>
							<?php
							foreach ($users as $user) {

							?>
								<tr>
									<td>{{ $roles[$user->role ?? 'user'] ?? $user->role }}</td>
								<td><?php echo $user->category; ?> - <?php echo get_dialogs_simple('user_categories_name_' . $user->category, $active_language); ?></td>
									<td>{!! display_avatar($user, 24) !!} {{ $user->name }}</td>
									<td><?php echo $user->email; ?></td>
									<td><?php echo $user->created_at; ?></td>
									<td><?php echo $user->submitted; ?></td>
									<td><?php echo $user->validated; ?></td>
									<td><?php echo $user->rejected; ?></td>
									<td class="text-nowrap">
										@if($user->anonymized_at)
											<span class="badge text-bg-secondary">{!! get_dialogs('deleted', $active_language) !!}</span>
										@else
										<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#user_modal" data-name="{!!$user->name!!}" data-email="{!!$user->email!!}" data-category="{!!$user->category!!}" data-role="{{ $user->role ?? 'user' }}" data-public="{{ $user->public_name ? 1 : 0 }}" data-avatar="{{ $user->avatar ? url('avatars/' . $user->avatar) : '' }}" data-user_id="{!!$user->id!!}">
																					<i class="bi bi-pencil"></i>
																				</button>
										@if($user->id !== auth()->id())
										<form method="post" action="{{ url('user_anonymize') }}" class="d-inline" onsubmit="return confirm({{ json_encode(get_dialogs_simple('Delete this account? The name, e-mail address and photo are removed and the person can no longer sign in. Their contributions stay, as Deleted user. This cannot be undone.', $active_language)) }});">
											@csrf
											<input type="hidden" name="user_id" value="{{ $user->id }}">
											<button type="submit" class="btn btn-outline-danger btn-sm" title="{{ get_dialogs_simple('Delete this account', $active_language) }}"><i class="bi bi-person-x"></i></button>
										</form>
										@endif
										@endif
									</td>

								</tr>
							<?php
							}
							//echo $users->links();
							?>
						</tbody>
					</table>

				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="user_modal" tabindex="-1" role="dialog" aria-labelledby="user_modalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="user_modalLabel">{!! get_dialogs('Edit User', $active_language) !!}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form method="post" action="user_edit" enctype="multipart/form-data" id="user_edit">
					@csrf
					<div class="form-group">
						<label for="name" class="col-form-label">{!! get_dialogs('Name or pseudonym:', $active_language) !!}</label>
						<input type="text" class="form-control" id="name" name="name">
					</div>
					<div class="form-group">
						<label for="email" class="col-form-label">{!! get_dialogs('E-mail:', $active_language) !!}</label>
						<input type="text" class="form-control" id="email" name="email">
					</div>
					@can('manage-roles')
					<div class="form-group">
						<label for="role" class="col-form-label">{!! get_dialogs('Role:', $active_language) !!} {!! help_hint('user_role', $active_language) !!}</label>
						<select class="form-select form-select-sm" id="role" name="role">
							@foreach($roles as $key => $label)
							<option value="{{ $key }}">{{ $label }}</option>
							@endforeach
						</select>
					</div>
					@endcan
					<div class="form-group">
						<label for="category" class="col-form-label">{!! get_dialogs('Level:', $active_language) !!} {!! help_hint('user_level', $active_language) !!}</label>
						<select class="form-select form-select-sm" aria-label=".form-select-sm example" id="category" name="category">
							<?php
							foreach ($user_categories as $user_category) {
							?>
								<option value="{{$user_category->id}}">{{$user_category->category}}</option>
							<?php
							}
							?>
						</select>
					</div>
					<input type="hidden" name="profile_fields" value="1">
					<div class="form-check mt-3">
						<input class="form-check-input" type="checkbox" id="public_name" name="public_name" value="1" checked>
						<label class="form-check-label" for="public_name">{!! get_dialogs('Show this name publicly (leaderboard & activity feed)', $active_language) !!}</label>
					</div>
					@if(\App\Services\AvatarStore::available())
					<div class="form-group mt-3">
						<label for="avatar" class="col-form-label">{!! get_dialogs('Profile photo', $active_language) !!}</label>
						<div id="user_avatar_preview" class="mb-2"></div>
						<input type="file" class="form-control form-control-sm" id="avatar" name="avatar" accept="{{ implode(',', array_map(fn($f) => '.' . $f, \App\Services\AvatarStore::formats())) }}">
						<div class="form-check mt-1" id="remove_avatar_wrap" style="display:none">
							<input class="form-check-input" type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
							<label class="form-check-label" for="remove_avatar">{!! get_dialogs('Remove photo', $active_language) !!}</label>
						</div>
					</div>
					@endif
					<div class="form-group">
					<input type="hidden" name="user_id" id="user_id" value="">
					</div>
			</div>
			<div class="col">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
				<button type="submit" class="btn btn-primary" form="user_edit">{!! get_dialogs('Submit', $active_language) !!}</button>
			</div>

		</div>
	</div>

	</form>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#user_modal').on('show.bs.modal', function(event) {
			var button = $(event.relatedTarget); // Button that triggered the modal
			var user_id = button.data('user_id'); // Extract info from data-bs-* attributes
			var name = button.data('name'); // Extract info from data-bs-* attributes
			var email = button.data('email'); // Extract info from data-bs-* attributes
			var category = button.data('category'); // Extract info from data-bs-* attributes

			// If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
			// Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
			var modal = $(this);
			modal.find('#user_id').val(user_id);
			modal.find('#name').val(name);
			modal.find('#email').val(email);
			modal.find('#category').val(category);
			modal.find('#role').val(button.data('role'));
			// the profile fields: a new user starts visible and without a photo
			modal.find('#public_name').prop('checked', button.data('public') !== 0);
			var avatar = button.data('avatar') || '';
			modal.find('#user_avatar_preview').html(avatar ? $('<img>', {src: avatar, width: 56, height: 56, style: 'border-radius:50%;object-fit:cover'}) : '');
			modal.find('#remove_avatar_wrap').toggle(!!avatar);
			modal.find('#remove_avatar').prop('checked', false);
			modal.find('#avatar').val('');
		});
	})
</script>


@endsection