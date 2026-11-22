@extends('layouts.app')

@section('content')
<div class="container">
<h1>{!! get_dialogs('Edit Project Fields', $active_language) !!}</h1>
<div class="container">
    <div class="row justify-content-center">
        <div class="album py-2">
            <div class="col float-end">
                @include('projects.controls')
            </div>
        </div>
    </div>
</div>

<form method="post" action="{{URL('project_update_fields')}}" enctype="multipart/form-data" id="update_fields">
@csrf
    <div class="input-group input-group-sm mb-3" style="max-width: 22rem">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" id="field-search" class="form-control" placeholder="{{ get_dialogs_simple('Search fields...', $active_language) }}" aria-label="{{ get_dialogs_simple('Search fields...', $active_language) }}">
        <span class="input-group-text" id="field-search-count"></span>
    </div>
    <div class="form-group">
        <?php
        $projectFieldIds = $project->fields()->get()->pluck('id')->toArray();
        foreach ($fields as $field) {
            $isChecked = in_array($field->id, $projectFieldIds);
        ?>
            <div class="field-group">
                <input name="field[]"
                       type="checkbox"
                       {{ $isChecked ? 'checked' : '' }}
                       value="{{$field->id}}"
                       data-field-name="{{$field->name}}"
                       data-originally-checked="{{ $isChecked ? '1' : '0' }}">
                {{$field->name}}<br>
                <?php
                foreach ($field->subfields as $subfield) {
                    $isSubChecked = in_array($subfield->id, $projectFieldIds);
                ?>
                    <div class="field-sub" style="text-indent: 50px">
                        <input name="field[]"
                               type="checkbox"
                               {{ $isSubChecked ? 'checked' : '' }}
                               value="{{$subfield->id}}"
                               data-field-name="{{$subfield->name}}"
                               data-originally-checked="{{ $isSubChecked ? '1' : '0' }}">
                        {{$subfield->name}}<br>
                    </div>
                <?php
                }
                ?>
            </div>
        <?php
        }
        ?>
    </div>

    {{-- Hidden inputs for delete decisions injected by JS before submit --}}
    <div id="delete-decisions"></div>

<script>
    (function () {
        var input = document.getElementById('field-search');
        var count = document.getElementById('field-search-count');
        if (!input) { return; }

        function normalize(text) {
            return String(text).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        function matches(words, text) {
            return words.every(function (w) { return text.indexOf(w) !== -1; });
        }

        input.addEventListener('input', function () {
            var words = normalize(input.value).split(/\s+/).filter(Boolean);
            var shown = 0, total = 0;

            document.querySelectorAll('.field-group').forEach(function (group) {
                var own = group.querySelector(':scope > input[type="checkbox"]');
                var groupName = normalize(own ? own.getAttribute('data-field-name') : '');
                var groupMatches = matches(words, groupName);
                var anySub = false;

                group.querySelectorAll('.field-sub').forEach(function (sub) {
                    var box = sub.querySelector('input[type="checkbox"]');
                    var subName = normalize(box ? box.getAttribute('data-field-name') : '');
                    var visible = groupMatches || matches(words, groupName + ' ' + subName);
                    sub.classList.toggle('d-none', !visible);
                    anySub = anySub || visible;
                    total++;
                    if (visible) { shown++; }
                });

                total++;
                var groupVisible = groupMatches || anySub;
                group.classList.toggle('d-none', !groupVisible);
                if (groupVisible) { shown++; }
            });

            count.textContent = words.length ? shown + ' / ' + total : '';
        });
    })();
</script>

    <input type="hidden" name="project_id" id="project_id" value="{{$project->id}}">
    <button type="button" class="btn btn-primary" id="submit-fields-btn">{!! get_dialogs('Submit', $active_language) !!}</button>
</form>
</div>

{{-- Confirmation modal --}}
<div class="modal fade" id="removeFieldsModal" tabindex="-1" aria-labelledby="removeFieldsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeFieldsModalLabel">{!! get_dialogs('Fields removed from project', $active_language) !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{!! get_dialogs('The following fields have been removed from this project:', $active_language) !!}</p>
                <ul id="removed-fields-list"></ul>
                <hr>
                <p class="fw-bold">{!! get_dialogs('Do you want to permanently delete the validated values for these fields from this project?', $active_language) !!}</p>
                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="delete_choice" id="delete_yes" value="yes">
                        <label class="form-check-label text-danger fw-bold" for="delete_yes">
                            {!! get_dialogs('Yes - delete all validated values for removed fields', $active_language) !!}
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="delete_choice" id="delete_no" value="no" checked>
                        <label class="form-check-label" for="delete_no">
                            {!! get_dialogs('No - keep validated values', $active_language) !!}
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Cancel', $active_language) !!}</button>
                <button type="button" class="btn btn-primary" id="confirm-submit-btn">{!! get_dialogs('Save changes', $active_language) !!}</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    var removedFields = [];

    $('#submit-fields-btn').on('click', function () {
        removedFields = [];

        $('#update_fields input[type=checkbox]').each(function () {
            var wasChecked = $(this).data('originally-checked') == '1';
            var nowChecked = $(this).is(':checked');
            if (wasChecked && !nowChecked) {
                removedFields.push({ id: $(this).val(), name: $(this).data('field-name') });
            }
        });

        if (removedFields.length === 0) {
            $('#update_fields').submit();
            return;
        }

        // Populate modal list
        var $list = $('#removed-fields-list').empty();
        $.each(removedFields, function (i, f) {
            $list.append($('<li>').text(f.name));
        });

        $('#delete_no').prop('checked', true);
        $('#removeFieldsModal').modal('show');
    });

    $('#confirm-submit-btn').on('click', function () {
        var deleteChoice = $('input[name=delete_choice]:checked').val();
        var $container   = $('#delete-decisions').empty();

        $.each(removedFields, function (i, f) {
            $container.append(
                $('<input>').attr({
                    type:  'hidden',
                    name:  'delete_validated[' + f.id + ']',
                    value: deleteChoice === 'yes' ? '1' : '0'
                })
            );
        });

        $('#removeFieldsModal').modal('hide');
        $('#update_fields').submit();
    });

});
</script>
@endsection