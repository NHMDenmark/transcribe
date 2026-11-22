@extends('layouts.app')

@section('content')
<?php
$scope = $scope ?? 'all';
$fields = $fields ?? collect();
if (!function_exists('binary_to_yes_no')) {
    function binary_to_yes_no($value)
    {
        // See status_translate() below for why app()->getLocale(), not $active_language.
        if ($value == '0') {
            $value = get_dialogs_simple('no', app()->getLocale());
        }
        if ($value == '1') {
            $value = get_dialogs_simple('yes', app()->getLocale());
        }
        return $value;
    }
}
if (!function_exists('status_translate')) {
    function status_translate($value)
    {
        // A plain function, not a closure — it can't see the template's own
        // $active_language variable, so it reads Laravel's own locale instead
        // (AppServiceProvider::active_language() already calls
        // App::setLocale() with the exact same value before any view renders).
        $active_language = app()->getLocale();
        if ($value == '0') {
            $value = '<i style="color:red" class="bi bi-trash3"></i> ' . get_dialogs_simple('submission_status_deleted', $active_language);
        }
        if ($value == '1') {
            $value = '<i style="color:#FFBF00" class="bi bi-check-circle-fill"></i> ' . get_dialogs_simple('submission_status_pending', $active_language);
        }
        if ($value == '2') {
            $value = '<i style="color:green" class="bi bi-check-circle-fill"></i> ' . get_dialogs_simple('submission_status_validated', $active_language);
        }
        if ($value == '3') {
            $value = '<i style="color:red" class="bi bi-x-circle-fill"></i> ' . get_dialogs_simple('submission_status_rejected', $active_language);
        }

        return $value;
    }
}


?>

<div class="container-fluid px-lg-4">
    <h1>{!! get_dialogs('User Submissions', $active_language) !!}</h1>

    <div class="row">
        <div class="col-md-12">
            <div class="float-end"><strong>{!! get_dialogs('Total submissions:', $active_language) !!}</strong> {{$collaborative_fields->total()}} {!! get_dialogs('(includes overwritten)', $active_language) !!}</div>
        </div>
    </div>

    @if(in_array($scope, ['all', 'mine'], true))
    <div class="row">
        <div class="col-md-12">
            <div class="float-end">
                <h3>
                    <a title="{{ get_dialogs_simple('Export to CSV', $active_language) }}" href="#" data-bs-toggle="modal" data-bs-target="#exportCSV">
                        <i class="bi bi-filetype-csv" style="color: green"></i>
                    </a>
                </h3>
            </div>
            <div class="float-end mx-2">
                <h3>
                    <a href="#" title="{{ get_dialogs_simple('Export to JSON', $active_language) }}" data-bs-toggle="modal" data-bs-target="#exportJSON">
                        <i class="bi bi-filetype-json" style="color: violet"></i>
                    </a>
                </h3>
            </div>
        </div>
    </div>
    @endif
</div>
<div class="container-fluid px-lg-4">
    <div id="flash-message" class="alert d-none">
    </div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ url()->current() }}" class="row g-3">
            <div class="col-md-1">
                <label class="form-label">{!! get_dialogs('ID', $active_language) !!}</label>
                <input type="number" name="id" class="form-control" placeholder="ID" value="{{ request('id') }}">
            </div>

            <div class="col-md-2">
                <label class="form-label">{!! get_dialogs('Specimen ID', $active_language) !!}</label>
                <input type="text" name="specimen_id" class="form-control" placeholder="{{ get_dialogs_simple('Search Specimen...', $active_language) }}" value="{{ request('specimen_id') }}">
            </div>

            <div class="col-md-2">
                <label class="form-label">{!! get_dialogs('Project', $active_language) !!}</label>
                <select name="project_id" class="form-select">
                    <option value="">{{ get_dialogs_simple('All Projects', $active_language) }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" {{ (string) request('project_id') === (string) $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                    @endforeach
                    <option value="none" {{ request('project_id') === 'none' ? 'selected' : '' }}>{{ get_dialogs_simple('No project', $active_language) }}</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">{!! get_dialogs('Field', $active_language) !!}</label>
                <select name="field_id" class="form-select">
                    <option value="">{{ get_dialogs_simple('All Fields', $active_language) }}</option>
                    @foreach($fields as $field)
                        <option value="{{ $field->id }}" {{ request('field_id') == $field->id ? 'selected' : '' }}>
                            {{ $field->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">{!! get_dialogs('Value', $active_language) !!}</label>
                <input type="text" name="value" class="form-control" placeholder="{{ get_dialogs_simple('Search Value...', $active_language) }}" value="{{ request('value') }}">
            </div>

            <div class="col-md-2">
                <label class="form-label">{!! get_dialogs('Status', $active_language) !!}</label>
                <select name="status" class="form-select">
                    <option value="">{{ get_dialogs_simple('All Status', $active_language) }}</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ get_dialogs_simple('submission_status_deleted', $active_language) }}</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ get_dialogs_simple('submission_status_pending', $active_language) }}</option>
                    <option value="2" {{ request('status') === '2' ? 'selected' : '' }}>{{ get_dialogs_simple('submission_status_validated', $active_language) }}</option>
                    <option value="3" {{ request('status') === '3' ? 'selected' : '' }}>{{ get_dialogs_simple('submission_status_rejected', $active_language) }}</option>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label">{!! get_dialogs('Doubt', $active_language) !!}</label>
                <select name="doubt" class="form-select">
                    <option value="">{{ get_dialogs_simple('All', $active_language) }}</option>
                    <option value="1" {{ request('doubt') === '1' ? 'selected' : '' }}>{{ get_dialogs_simple('yes', $active_language) }}</option>
                    <option value="0" {{ request('doubt') === '0' ? 'selected' : '' }}>{{ get_dialogs_simple('no', $active_language) }}</option>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label">{!! get_dialogs('Deducted', $active_language) !!}</label>
                <select name="deducted" class="form-select">
                    <option value="">{{ get_dialogs_simple('All', $active_language) }}</option>
                    <option value="1" {{ request('deducted') === '1' ? 'selected' : '' }}>{{ get_dialogs_simple('yes', $active_language) }}</option>
                    <option value="0" {{ request('deducted') === '0' ? 'selected' : '' }}>{{ get_dialogs_simple('no', $active_language) }}</option>
                </select>
            </div>

            @if($scope === 'all')
            <div class="col-md-2">
                <label class="form-label">{!! get_dialogs('User', $active_language) !!}</label>
                <input type="text" name="user" class="form-control" placeholder="{{ get_dialogs_simple('Name or email...', $active_language) }}" value="{{ request('user') }}">
            </div>
            @endif

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">{!! get_dialogs('Filter', $active_language) !!}</button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary">{!! get_dialogs('Clear', $active_language) !!}</a>
            </div>
        </form>
    </div>
</div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    @php
                        // every column the table can show; the user picks (the choice is kept in the browser)
                        $projectNames = collect($projects ?? [])->pluck('name', 'id');
                        $columns = [
                            'id' => ['label' => get_dialogs_simple('ID', $active_language), 'sort' => 'id', 'default' => true],
                            'specimen' => ['label' => get_dialogs_simple('Specimen', $active_language), 'sort' => 'specimen_id', 'default' => true],
                            'project' => ['label' => get_dialogs_simple('Project', $active_language), 'sort' => 'project_id', 'default' => true],
                            'field' => ['label' => get_dialogs_simple('Field', $active_language), 'sort' => 'field_id', 'default' => true],
                            'position' => ['label' => get_dialogs_simple('Position', $active_language), 'sort' => 'position', 'default' => true],
                            'value' => ['label' => get_dialogs_simple('Value', $active_language), 'sort' => 'value', 'default' => true],
                            'status' => ['label' => get_dialogs_simple('Status', $active_language), 'sort' => 'status', 'default' => true],
                            'doubt' => ['label' => get_dialogs_simple('Doubt', $active_language), 'sort' => 'doubt', 'default' => true],
                            'deducted' => ['label' => get_dialogs_simple('Deducted', $active_language), 'sort' => 'deducted', 'default' => true],
                            'points' => ['label' => get_dialogs_simple('Points', $active_language), 'sort' => 'points', 'default' => true],
                            'created_at' => ['label' => get_dialogs_simple('Created at', $active_language), 'sort' => 'created_at', 'default' => true],
                            'updated_at' => ['label' => get_dialogs_simple('Updated at', $active_language), 'sort' => 'updated_at', 'default' => true],
                            'displayed_at' => ['label' => get_dialogs_simple('Displayed at', $active_language), 'sort' => 'displayed_at', 'default' => false],
                            'time_spent' => ['label' => get_dialogs_simple('Time spent', $active_language), 'sort' => 'time_spent', 'default' => true],
                            'user' => ['label' => get_dialogs_simple('User', $active_language), 'sort' => 'user_id', 'default' => true],
                            'remarks' => ['label' => get_dialogs_simple('Remarks', $active_language), 'sort' => null, 'default' => false],
                        ];
                        $hide = fn($key) => $columns[$key]['default'] ? '' : ' d-none';
                        $dir = request('direction') == 'asc' ? 'desc' : 'asc';
                    @endphp
                    <div class="d-flex justify-content-end mb-2">
                        @include('layouts.partials.column_picker', ['tableId' => 'example', 'storageKey' => 'submissions.' . $scope, 'columns' => $columns])
                    </div>                    <div class="table-responsive"><table class="table table-hover table-sm align-middle" id="example" style="font-size: .85rem">
                        <thead>
                            <tr>
                                @foreach($columns as $key => $col)
                                    <th data-col="{{ $key }}" class="{{ trim($hide($key)) }}">
                                        @if($col['sort'])
                                            <a href="{{ request()->fullUrlWithQuery(['sort' => $col['sort'], 'direction' => $dir]) }}">{{ $col['label'] }}</a>
                                        @else
                                            {{ $col['label'] }}
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            foreach ($collaborative_fields as $collaborative_field) {

                                $project_id = $collaborative_field->project_id ?? 0;
                                $specimen_id = $collaborative_field->specimen_id ?? 0;
                                $field_name = $collaborative_field->field->name ?? 0;

                            ?>
                                <tr>
                                    <td data-col="id" class="<?php echo trim($hide('id')); ?>"><?php echo $collaborative_field->id; ?></td>
                                    <td data-col="specimen" class="<?php echo trim($hide('specimen')); ?>"><a href="{{URL('projects/' .$project_id . '/' . $specimen_id )}}"><?php echo $collaborative_field->specimen_id; ?></a></td>
                                    <td data-col="project" class="<?php echo trim($hide('project')); ?>"><?php
                                        if ($project_id && isset($projectNames[$project_id])) {
                                            echo '<a href="' . e(url('projects/' . $project_id)) . '">' . e($projectNames[$project_id]) . '</a>';
                                        } else {
                                            echo '&mdash;';
                                        }
                                    ?></td>
                                    <td data-col="field" class="<?php echo trim($hide('field')); ?>"><?php echo $field_name; ?></td>
                                    <td data-col="position" class="<?php echo trim($hide('position')); ?>"><?php echo $collaborative_field->position; ?></td>
                                    <td data-col="value" class="<?php echo trim($hide('value')); ?>"><?php echo $collaborative_field->value; ?></td>
                                    <td data-col="status" class="<?php echo trim($hide('status')); ?>"><?php echo status_translate($collaborative_field->status); ?></td>
                                    <td data-col="doubt" class="<?php echo trim($hide('doubt')); ?>"><?php echo binary_to_yes_no($collaborative_field->doubt); ?></td>
                                    <td data-col="deducted" class="<?php echo trim($hide('deducted')); ?>"><?php echo binary_to_yes_no($collaborative_field->deducted); ?></td>
                                    <td data-col="points" class="<?php echo trim($hide('points')); ?>"><?php echo $collaborative_field->points; ?></td>
                                    <td data-col="created_at" class="<?php echo trim($hide('created_at')); ?>"><?php echo $collaborative_field->created_at; ?></td>
                                    <td data-col="updated_at" class="<?php echo trim($hide('updated_at')); ?>"><?php echo $collaborative_field->updated_at; ?></td>
                                    <td data-col="displayed_at" class="<?php echo trim($hide('displayed_at')); ?>"><?php echo $collaborative_field->displayed_at; ?></td>
                                    <td data-col="time_spent" class="<?php echo trim($hide('time_spent')); ?>">
                                        <?php
                                        if ($collaborative_field->displayed_at) {
                                            $seconds = max(0, \Carbon\Carbon::parse($collaborative_field->displayed_at)->diffInSeconds($collaborative_field->created_at, false));
                                            echo intdiv($seconds, 60) . ':' . str_pad($seconds % 60, 2, '0', STR_PAD_LEFT);
                                        }
                                        ?>
                                    </td>
                                    <td data-col="user" class="<?php echo trim($hide('user')); ?>"><?php echo e($collaborative_field->user->name ?? get_dialogs_simple('guest', $active_language)); ?></td>
                                    <td data-col="remarks" class="<?php echo trim($hide('remarks')); ?>"><?php echo e($collaborative_field->remarks); ?></td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table></div>
                    {{$collaborative_fields->links()}}
                </div>
            </div>
        </div>
    </div>
</div>


@if(in_array($scope, ['all', 'mine'], true))
@php
    // what is exported follows the filters of the list (not the page or the sort order)
    $exportQuery = http_build_query(request()->except(['page', 'sort', 'direction', 'per_page']));
    $exportBase = $scope === 'mine' ? '/dashboard' : '/all_submissions';
    $csvUrl = url($exportBase . '/csv') . ($exportQuery ? '?' . $exportQuery : '');
    $jsonUrl = url($exportBase . '/json') . ($exportQuery ? '?' . $exportQuery : '');
@endphp
<div class="modal fade" id="exportCSV" tabindex="-1" aria-labelledby="exportCSVLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportCSVLabel">{!! get_dialogs('Export to CSV', $active_language) !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{!! get_dialogs('The submissions will be exported to a comma separated values (CSV) file, with the filters currently applied to this list.', $active_language) !!}</p>
                <p>{!! get_dialogs('You can define the record id from which the list should start by adding the parameter start_id to the url (for example: start_id=1500).', $active_language) !!}</p>
                <p><a class="btn btn-outline-success" href="{{ $csvUrl }}"><i class="bi bi-download"></i> {!! get_dialogs('Download CSV', $active_language) !!}</a></p>
                <p class="small text-muted">{!! get_dialogs('Link to export CSV:', $active_language) !!} <a href="{{ $csvUrl }}" target="_blank">{{ $csvUrl }}</a></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exportJSON" tabindex="-1" aria-labelledby="exportJSONLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportJSONLabel">{!! get_dialogs('Export to JSON', $active_language) !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{!! get_dialogs('The submissions will be exported to a JSON file, with the filters currently applied to this list. This feature is intended to allow values to be read by machines.', $active_language) !!}</p>
                <p>{!! get_dialogs('Some parameters can be used to define the output', $active_language) !!}</p>
                <ul>
                    <li><b>start_id</b> - {!! get_dialogs('record id from which the list should start (default = 1)', $active_language) !!}</li>
                    <li><b>per_page</b> - {!! get_dialogs('number of records per page (default = 15)', $active_language) !!}</li>
                    <li><b>page</b> - {!! get_dialogs('page number', $active_language) !!}</li>
                </ul>
                <p class="small text-muted">{!! get_dialogs('Link to export JSON:', $active_language) !!} <a href="{{ $jsonUrl }}" target="_blank">{{ $jsonUrl }}</a></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection
