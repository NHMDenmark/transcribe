@extends('layouts.app')

@section('content')
<div class="container">
<h1>{!! get_dialogs('Edit Language', $active_language) !!}</h1>
<form action="editdialog" method="post" enctype="multipart/form-data">
	<input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
	<table class="table">
		<thead>
			<tr>
				<th class="table-header" width="10%">{!! get_dialogs('Identifier', $active_language) !!}</th>
				<th class="table-header" width="45%">{!! get_dialogs('Master language value', $active_language) !!}</th>
				<th class="table-header" width="45%">{!! get_dialogs('Language value', $active_language) !!}</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($dialogstoedit as $dialogtoedit) {
				$mastervalue = array_search($dialogtoedit->identifier, array_column($masterdialogs, 'identifier'));
			?>
				<tr class="table-row">
					<td><?php echo $dialogtoedit->identifier; ?></td>
					<td><?php echo $masterdialogs[$mastervalue]['text']; ?></td>
					<td>
						<div style="border: 1px solid; background: #FFF;" contenteditable="true" onBlur="saveToDatabase(this,'answer','<?php echo $dialogtoedit->id; ?>')" onClick="showEdit(this);"><?php echo $dialogtoedit->text; ?></div>
					</td>
				</tr>
				<tr class="table-row">
					<td></td>
					<td></td>
					<td></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>

</form>
</div>
@endsection


<script>
	function showEdit(editableObj) {
		$(editableObj).css("background", "#f7ecb5");
	}

	function saveToDatabase(editableObj, column, id) {
		$(editableObj).css("background", "#FFF url(loaderIcon.gif) no-repeat right");
		//	var text = editableObj.innerHTML.replace(/&amp;/, "&");
		var text = encodeURIComponent(editableObj.innerHTML);
		$.ajax({
			url: "{{ URL('editdialog') }}",
			type: "POST",
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			data: 'editval=' + text + '&id=' + id,
			success: function(data) {
				$(editableObj).css("background", "#c1e2b3");
			}
		});


	}
</script>