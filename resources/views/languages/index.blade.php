@extends('layouts.app')

@section('content')
<div class="container">
    <h1>{!! get_dialogs('Languages', $active_language) !!}</h1>
</div>
<div class="container">
    <div id="flash-message" class="alert d-none">
    </div>

    <div class="card">
        <div class="card-body">
            <button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#language_modal" title="{{ get_dialogs_simple('Add a new language', $active_language) }}">
                <i class="bi bi-plus-lg"></i>
            </button>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col" class="col-3">{!! get_dialogs('Name', $active_language) !!}</th>
                        <th scope="col" class="col-2">{!! get_dialogs('ISO2 Country Code', $active_language) !!}</th>
                        <th scope="col" class="col-1">{!! get_dialogs('Active', $active_language) !!}</th>
                        <th scope="col" class="col-1">{!! get_dialogs('Master language', $active_language) !!}</th>
                        <th class="col-1"></th>
                        <th class="col-1"></th>
                        <th class="col-1"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    if (!function_exists('binary_to_yes_no')) {
                        function binary_to_yes_no($value)
                        {
                            if ($value == '0') {
                                $value = get_dialogs_simple('no', app()->getLocale());
                            }
                            if ($value == '1') {
                                $value = get_dialogs_simple('yes', app()->getLocale());
                            }
                            return $value;
                        }
                    }

                    foreach ($languages as $language) {
                    ?>
                        <tr id="item-{{$language->id}}">
                            <td>{!!$language->text!!}</td>
                            <td>{!!$language->iso2!!}</td>
                            <td>{!!binary_to_yes_no($language->active)!!}</td>
                            <td>{!!binary_to_yes_no($language->master)!!}</td>
                            <td><a href="{{URL('/dialogs/'.$language->id)}}" title="{{ get_dialogs_simple('Edit all dialogs for this language', $active_language) }}"><i class="bi bi-chat-square-text"></i></a>
                            </td>

                            <td>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#language_modal" data-text="{!!$language->text!!}" data-iso2="{!!$language->iso2!!}" data-active="{!!$language->active!!}" data-master="{!!$language->master!!}" data-language_id="{!!$language->id!!}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm delete_language" title="{{ get_dialogs_simple('Delete language', $active_language) }}" id="{{$language->id}}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="language_modal" tabindex="-1" role="dialog" aria-labelledby="language_modalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="language_modalLabel">{!! get_dialogs('Edit language', $active_language) !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="languages" enctype="multipart/form-data" id="language_edit">
                    @csrf
                    <div class="form-group">
                        <label for="text" class="col-form-label">{!! get_dialogs('Name:', $active_language) !!}</label>
                        <input type="text" class="form-control" id="text" name="text">
                    </div>
                    <div class="form-group">
                        <label for="iso2" class="col-form-label">{!! get_dialogs('ISO2 Country Code:', $active_language) !!} {!! help_hint('language_code', $active_language) !!}</label>
                        <input type="text" class="form-control" id="iso2" name="iso2">
                    </div>
                    <div class="form-group">
                        <label for="active" class="col-form-label">{!! get_dialogs('Active:', $active_language) !!}</label>
                        <select class="form-select form-select-sm" aria-label=".form-select-sm example" id="active" name="active">
                            <option value="0">{{ get_dialogs_simple('no', $active_language) }}</option>
                            <option value="1">{{ get_dialogs_simple('yes', $active_language) }}</option>
                        </select>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="master" name="master" checked>
                        <label class="form-form-label" for="master">
                            {!! get_dialogs('Master language', $active_language) !!}
                        </label>
                    </div>
                    <input type="hidden" name="language_id" id="language_id" value="">
                </form>
            </div>
            <div class="col">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
                <button type="submit" class="btn btn-primary" form="language_edit">{!! get_dialogs('Submit', $active_language) !!}</button>
            </div>

        </div>
    </div>


</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('#language_modal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var language_id = button.data('language_id'); // Extract info from data-bs-* attributes
            var text = button.data('text'); // Extract info from data-bs-* attributes
            var iso2 = button.data('iso2'); // Extract info from data-bs-* attributes
            var active = button.data('active'); // Extract info from data-bs-* attributes
            var master = button.data('master'); // Extract info from data-bs-* attributes

            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this);
            modal.find('#language_id').val(language_id);
            modal.find('#text').val(text);
            modal.find('#iso2').val(iso2);
            modal.find('#active').val(active);
            if (master != 1) {
                modal.find('#master').prop('checked', false);
            }
        });
    })
</script>

<script type="text/javascript">
    $(document).on('click', '.delete_language', function(e) {
        e.preventDefault();
        var language_id = this.id;
        if (!confirm(<?php echo json_encode(get_dialogs_simple('Are you sure you want to delete this item?', $active_language)); ?>)) {
            return false;
        }

        $.ajax({
            type: "POST",
            cache: false,
            url: "{{URL('languages')}}" + "/" + language_id,
            data: {
                "_token": "{{ csrf_token() }}",
                "language_id": language_id,
                _method: 'DELETE'
            },
            success: function(data) {
                window.location.reload();
            }
        });
    });
</script>
@endsection