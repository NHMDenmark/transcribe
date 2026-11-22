@extends('layouts.app')

@section('content')
<?php
if (!function_exists('status_translate')) {
    function status_translate($value)
    {
        // A plain function, not a closure — see the identical comment in
        // users/dashboard.blade.php's copy. These identifiers are deliberately
        // distinct from that file's submission_status_* ones: status here means
        // something different (validated_fields/Specify-sync semantics, not
        // collaborative_fields).
        $active_language = app()->getLocale();
        if ($value == '0') {
            $value = '<i style="color:red" class="bi bi-trash3"></i> ' . get_dialogs_simple('validated_status_overwritten', $active_language);
        }
        if ($value == '1') {
            $value = '<i style="color:#FFBF00" class="bi bi-check-circle-fill"></i> ' . get_dialogs_simple('validated_status_validated', $active_language);
        }
        if ($value == '2') {
            $value = '<i style="color:green" class="bi bi-check-circle-fill"></i> ' . get_dialogs_simple('validated_status_sent_to_specify', $active_language);
        }
        if ($value == '3') {
            $value = '<i style="color:red" class="bi bi-x-circle-fill"></i> ' . get_dialogs_simple('validated_status_error', $active_language);
        }
        if ($value == '4') {
            $value = '<i style="color:#e67e00" class="bi bi-exclamation-triangle-fill"></i> ' . get_dialogs_simple('Conflict with Specify', $active_language);
        }
        if ($value == '5') {
            $value = '<i style="color:#6c757d" class="bi bi-check-circle"></i> ' . get_dialogs_simple('Resolved: kept the Specify value', $active_language);
        }
        if ($value == '6') {
            $value = '<i style="color:#FFBF00" class="bi bi-arrow-repeat"></i> ' . get_dialogs_simple('Overwrite approved (awaiting sync)', $active_language);
        }
        if ($value == '7') {
            $value = '<i style="color:#6c757d" class="bi bi-slash-circle"></i> ' . get_dialogs_simple('Ignored: not sent to Specify', $active_language);
        }

        return $value;
    }
}


?>

<div class="container-fluid px-lg-4">
    <h1>{!! get_dialogs('Validated values', $active_language) !!}</h1>

    <div class="row">
        <div class="col-md-12">
            <div class="float-end"><strong>{!! get_dialogs('Total records:', $active_language) !!}</strong> {{$collaborative_fields->total()}} {!! get_dialogs('(includes overwritten)', $active_language) !!}</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="float-end">
                @can('run-sync')
                <h3>
                    <a title="{{ get_dialogs_simple('Export to CSV', $active_language) }}" href="#" data-bs-toggle="modal" data-bs-target="#exportCSV">
                        <i class="bi bi-filetype-csv" style="color: green"></i>
                    </a>
                </h3>
                @endcan
            </div>
            <div class="float-end mx-2">
                <h3>
                    <a href="#" title="{{ get_dialogs_simple('Export to JSON', $active_language) }}"  data-bs-toggle="modal" data-bs-target="#exportJSON">
                        <i class="bi bi-filetype-json" style="color: violet"></i>
                    </a>
                </h3>
            </div>
        </div>
    </div>
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
                    <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>{{ get_dialogs_simple('validated_status_overwritten', $active_language) }}</option>
                    <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>{{ get_dialogs_simple('validated_status_validated', $active_language) }}</option>
                    <option value="2" {{ request('status') == '2' ? 'selected' : '' }}>{{ get_dialogs_simple('validated_status_sent_to_specify', $active_language) }}</option>
                    <option value="3" {{ request('status') == '3' ? 'selected' : '' }}>{{ get_dialogs_simple('validated_status_error', $active_language) }}</option>
                    <option value="4" {{ request('status') == '4' ? 'selected' : '' }}>{{ get_dialogs_simple('Conflict with Specify', $active_language) }}</option>
                    <option value="5" {{ request('status') == '5' ? 'selected' : '' }}>{{ get_dialogs_simple('Resolved: kept the Specify value', $active_language) }}</option>
                    <option value="6" {{ request('status') == '6' ? 'selected' : '' }}>{{ get_dialogs_simple('Overwrite approved (awaiting sync)', $active_language) }}</option>
                    <option value="7" {{ request('status') == '7' ? 'selected' : '' }}>{{ get_dialogs_simple('Ignored: not sent to Specify', $active_language) }}</option>
                </select>
            </div>

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
                        // every column the table can show (the Sync column is always there)
                        $projectNames = collect($projects ?? [])->pluck('name', 'id');
                        $columns = [
                            'id' => ['label' => get_dialogs_simple('ID', $active_language), 'sort' => 'id', 'default' => true],
                            'specimen' => ['label' => get_dialogs_simple('Specimen', $active_language), 'sort' => 'specimen_id', 'default' => true],
                            'project' => ['label' => get_dialogs_simple('Project', $active_language), 'sort' => 'project_id', 'default' => true],
                            'field' => ['label' => get_dialogs_simple('Field', $active_language), 'sort' => 'field_id', 'default' => true],
                            'position' => ['label' => get_dialogs_simple('Position', $active_language), 'sort' => 'position', 'default' => true],
                            'value' => ['label' => get_dialogs_simple('Value', $active_language), 'sort' => 'value', 'default' => true],
                            'status' => ['label' => get_dialogs_simple('Status', $active_language), 'sort' => 'status', 'default' => true],
                            'error' => ['label' => get_dialogs_simple('validated_status_error', $active_language), 'sort' => null, 'default' => true],
                            'created_at' => ['label' => get_dialogs_simple('Created at', $active_language), 'sort' => 'created_at', 'default' => true],
                            'updated_at' => ['label' => get_dialogs_simple('Updated at', $active_language), 'sort' => 'updated_at', 'default' => true],
                            'user' => ['label' => get_dialogs_simple('User', $active_language), 'sort' => 'user_id', 'default' => true],
                        ];
                        $hide = fn($key) => $columns[$key]['default'] ? '' : ' d-none';
                        $dir = request('direction') == 'asc' ? 'desc' : 'asc';
                    @endphp
                    <div class="d-flex justify-content-end mb-2">
                        @include('layouts.partials.column_picker', ['tableId' => 'example', 'storageKey' => 'validated', 'columns' => $columns])
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
                @if($key === 'status'){!! help_hint('validated_status', $active_language) !!}@endif
            </th>
        @endforeach
        <th>{{ get_dialogs_simple('Sync', $active_language) }}</th>
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
                                    <td data-col="error" class="<?php echo trim($hide('error')); ?>"><?php echo $collaborative_field->error; ?></td>
                                    <td data-col="created_at" class="<?php echo trim($hide('created_at')); ?>"><?php echo $collaborative_field->created_at; ?></td>
                                    <td data-col="updated_at" class="<?php echo trim($hide('updated_at')); ?>"><?php echo $collaborative_field->updated_at; ?></td>
                                    <td data-col="user" class="<?php echo trim($hide('user')); ?>"><?php echo $collaborative_field->user_id ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                        <?php
                                        $has_mapping = $collaborative_field->field && $collaborative_field->field->mapping->isNotEmpty();
                                        $can_sync = \Illuminate\Support\Facades\Gate::allows('run-sync');
                                        if ($collaborative_field->status != '4' && !$can_sync) {
                                            // a project manager only gets the conflicts to resolve, not the sync buttons
                                        } elseif (!$has_mapping) {
                                        ?>
                                            <span class="btn btn-sm btn-outline-secondary disabled" title="{{ get_dialogs_simple('This field has no API mapping configured - there is nothing to sync yet.', $active_language) }}">{!! get_dialogs('No mapping', $active_language) !!}</span>
                                        <?php
                                        } else {
                                            $sync_url = URL('/validate/manual/' . $collaborative_field->id);
                                            if ($collaborative_field->status == '4') {
                                                $sync_url = URL('/conflicts/' . $collaborative_field->id);
                                                $sync_label = '<i class="bi bi-exclamation-triangle"></i> ' . get_dialogs_simple('Resolve', $active_language);
                                                $sync_class = 'btn-warning';
                                            } elseif ($collaborative_field->status == '3') {
                                                $sync_label = '<i class="bi bi-arrow-repeat"></i> ' . get_dialogs_simple('Retry', $active_language);
                                                $sync_class = 'btn-outline-danger';
                                            } elseif ($collaborative_field->status == '2') {
                                                $sync_label = '<i class="bi bi-arrow-repeat"></i> ' . get_dialogs_simple('Re-sync', $active_language);
                                                $sync_class = 'btn-outline-secondary';
                                            } elseif ($collaborative_field->status == '1') {
                                                $sync_label = '<i class="bi bi-cloud-upload"></i> ' . get_dialogs_simple('Send', $active_language);
                                                $sync_class = 'btn-outline-primary';
                                            } else {
                                                $sync_label = '<i class="bi bi-cloud-upload"></i> ' . get_dialogs_simple('Force', $active_language);
                                                $sync_class = 'btn-outline-secondary';
                                            }
                                        ?>
                                            <a href="<?php echo $sync_url; ?>" target="_blank" class="btn btn-sm <?php echo $sync_class; ?>" title="<?php echo get_dialogs_simple('Send this record to Specify now (opens the ingestion log in a new tab)', $active_language); ?>"><?php echo $sync_label; ?></a>
                                        <?php
                                        }
                                        // a value that is waiting or failed can be set aside: it will not be sent, and can still be resubmitted
                                        if ($can_sync && in_array((int) $collaborative_field->status, [1, 3], true)) {
                                        ?>
                                            <form method="post" action="{{ url('validated/' . $collaborative_field->id . '/ignore') }}" class="d-inline" onsubmit="return confirm({{ json_encode(get_dialogs_simple('Ignore this value? It will not be sent to Specify.', $active_language)) }});">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ get_dialogs_simple('Do not send this value to Specify', $active_language) }}"><i class="bi bi-slash-circle"></i> {!! get_dialogs('Ignore', $active_language) !!}</button>
                                            </form>
                                        <?php
                                        }
                                        ?>
                                        </div>
                                    </td>
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


<div class="modal fade" id="exportCSV" tabindex="-1" aria-labelledby="exportCSVLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportCSVLabel">{!! get_dialogs('Export to CSV', $active_language) !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{!! get_dialogs('Validated values will be exported to a comma separated values (CSV) file.', $active_language) !!}</p>
                <p>{!! get_dialogs('You can define the record id from which the list should start by providing a value for the parameter start_id in the url (for example:', $active_language) !!} {{URL('/validated/csv?start_id=1500')}})</p>
                <p>{!! get_dialogs('Link to export CSV:', $active_language) !!} <a href="{{URL('/validated/csv')}}" target="_blank">{{URL('/validated/csv')}}</a>
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
                <p>{!! get_dialogs('Validated values will be exported to a JSON file.', $active_language) !!}</p>
                <p>{!! get_dialogs('This feature is intended to allow values to be read by machines.', $active_language) !!}</p>
                <p>{!! get_dialogs('Some parameters can be used to define the output', $active_language) !!}</p>
                <ul>
                    <li><b>start_id</b> - {!! get_dialogs('record id from which the list should start (default = 1)', $active_language) !!}</li>
                    <li><b>per_page</b> - {!! get_dialogs('number of records per page (default = 15)', $active_language) !!}</li>
                    <li><b>page</b> - {!! get_dialogs('page number', $active_language) !!}</li>
                </ul>
                <p>{!! get_dialogs('You can provide a value for the parameters in the url (for example:', $active_language) !!} {{URL('/validated/json?start_id=1500&per_page=20&page=4')}})</p>
                <p>{!! get_dialogs('Link to export JSON:', $active_language) !!} <a href="{{URL('/validated/json')}}" target="_blank">{{URL('/validated/json')}}</a>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
            </div>
        </div>
    </div>
</div>

@endsection