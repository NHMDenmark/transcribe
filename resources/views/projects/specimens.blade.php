@extends( 'layouts.app' )

@section( 'content' )
<div class="container">
    <div id="flash-message" class="alert d-none">
    </div>
</div>
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



    <div class="card my-2">

        <div class="card-header">
            {!! get_dialogs('Data', $active_language) !!}
            <div class="col float-end">
                @auth
                @if(Gate::allows('manage-projects', $project))
                    <button type="button" class=" btn btn-danger btn-sm specimens_delete" title="{{ get_dialogs_simple('Delete records', $active_language) }}" id="{{$project->id}}">
                        <i class="bi bi-trash"></i>
                    </button>
                    @endif
                    @endauth
            </div>
        </div>
        <div class="card-body table-responsive">
            <table class="table">
                <thead>
                    <tr>
                    <th scope="col">{!! get_dialogs('specimen_id', $active_language) !!}</th>
                    <th scope="col">{!! get_dialogs('images', $active_language) !!}</th>
                    <th scope="col">{!! get_dialogs('min_user_level', $active_language) !!}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($project->specimen as $specimen)
                    <tr>
                        <td>{{$specimen->specimen_id}}</td>
                        <td>{{$specimen->images}}</td>
                        <td>{{$specimen->min_user_category->category}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<script type="text/javascript">
    // $j is just this page's own alias for jQuery — noConflict() here released
    // the global "$" for the whole page, breaking the layout's own scripts and
    // the shared dialog-edit modal with "$ is not a function".
    var $j = jQuery;

    $j(document).on('click', '.dataset_records_delete', function(e) {
        e.preventDefault();
        var dataset_guid = this.id;
        if (!confirm(<?php echo json_encode(get_dialogs_simple('Are you sure you want to delete all records from this dataset?', $active_language)); ?>)) {
            return false;
        }

        $j.ajax({
            type: "POST",
            cache: false,
            url: "{{URL('dataset_records_delete')}}",
            data: {
                "_token": "{{ csrf_token() }}",
                "dataset_guid": dataset_guid
            },
            success: function(data) {
                window.location.reload();
            }
        });
    });
</script>

<script type="text/javascript">
    var $j = jQuery;

    $j(document).on('click', '.dataset_fields_delete', function(e) {
        e.preventDefault();
        var dataset_guid = this.id;
        if (!confirm(<?php echo json_encode(get_dialogs_simple('Are you sure you want to delete all fields (headers) and records from this dataset?', $active_language)); ?>)) {
            return false;
        }

        $j.ajax({
            type: "POST",
            cache: false,
            url: "{{URL('dataset_fields_delete')}}",
            data: {
                "_token": "{{ csrf_token() }}",
                "dataset_guid": dataset_guid
            },
            success: function(data) {
                window.location.reload();
            }
        });
    });
</script>

@endsection