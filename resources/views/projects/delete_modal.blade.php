@if(Auth::check() && Gate::allows('manage-projects'))
{{-- Project deletion modal — included once in layouts.app, available wherever controls appear --}}
<div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{!! get_dialogs('Delete project', $active_language) !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>{!! get_dialogs('Are you sure you want to delete this project?', $active_language) !!}</p>
                <hr>
                <p class="fw-bold">{!! get_dialogs("Do you want to permanently delete all validated values for this project's specimens and fields?", $active_language) !!}</p>
                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="delete_validated_choice" id="dv_yes" value="yes">
                        <label class="form-check-label text-danger fw-bold" for="dv_yes">
                            {!! get_dialogs('Yes - delete all validated values for this project', $active_language) !!}
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="delete_validated_choice" id="dv_no" value="no" checked>
                        <label class="form-check-label" for="dv_no">
                            {!! get_dialogs('No - keep validated values', $active_language) !!}
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Cancel', $active_language) !!}</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-project-btn">{!! get_dialogs('Delete project', $active_language) !!}</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
var pendingDeleteProjectId = null;

$(document).on('click', '.delete_project', function(e) {
    e.preventDefault();
    pendingDeleteProjectId = this.id;
    $('#dv_no').prop('checked', true);
    $('#deleteProjectModal').modal('show');
});

$(document).on('click', '#confirm-delete-project-btn', function() {
    var deleteValidated = $('input[name=delete_validated_choice]:checked').val() === 'yes' ? 1 : 0;

    $('#deleteProjectModal').modal('hide');

    $.ajax({
        type: "POST",
        cache: false,
        url: "{{ URL('projects') }}" + "/" + pendingDeleteProjectId,
        data: {
            "_token": "{{ csrf_token() }}",
            "project_id": pendingDeleteProjectId,
            "delete_validated": deleteValidated,
            _method: 'DELETE'
        },
        success: function(data) {
            window.location.href = "{{ URL('projects') }}";
        }
    });
});
</script>
@endif