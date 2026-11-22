<div class="card">
    <div class="card-header">
        {!! get_dialogs('Project', $active_language) !!}
    </div>
    <div class="card-body">
        <form method="POST" action="{{URL('projects')}}" enctype="multipart/form-data" id="project_edit">
            @csrf
            <div class="form-check form-switch">
                <input class="form-check-input dataset_status" type="checkbox" role="switch" name="active" id="active" <?php if ($project->active == 1) {
                                                                                                                            echo 'checked';
                                                                                                                        } ?>>
                <label class="form-check-label" for="flexSwitchCheckDefault">{!! get_dialogs('Visible to users', $active_language) !!} {!! help_hint('project_visible', $active_language) !!}</label>
            </div>
            <div class="mb-3">
                <label for="name" class="col-form-label">{!! get_dialogs('Name:', $active_language) !!}</label>
                <input type="text" class="form-control" id="name" name="name" value="{{$project->name}}">
            </div>

            <div class="mb-3">
                <label for="field_view_default" class="col-form-label">{!! get_dialogs('Field display (default):', $active_language) !!} {!! help_hint('project_field_view', $active_language) !!}</label>
                <select class="form-select" id="field_view_default" name="field_view_default">
                    <option value="">{{ get_dialogs_simple('Global default (all fields on one screen)', $active_language) }}</option>
                    <option value="all"    <?php if (($project->field_view_default ?? '') === 'all') echo 'selected'; ?>>{{ get_dialogs_simple('All fields on one screen', $active_language) }}</option>
                    <option value="single" <?php if (($project->field_view_default ?? '') === 'single') echo 'selected'; ?>>{{ get_dialogs_simple('One field at a time', $active_language) }}</option>
                </select>
                <small class="form-text text-muted">{!! get_dialogs('Users can still choose their own preference.', $active_language) !!}</small>
            </div>

            <div class="mb-3">
                <label class="col-form-label">{!! get_dialogs('Sync identifier (advanced):', $active_language) !!} {!! help_hint('project_sync_identifier', $active_language) !!}</label>
                <div class="row g-2">
                    <div class="col-6">
                        <select class="form-select" id="sync_root_table" name="sync_root_table"></select>
                        <small class="form-text text-muted">{!! get_dialogs('Specify table', $active_language) !!}</small>
                    </div>
                    <div class="col-6">
                        <select class="form-select" id="sync_root_field" name="sync_root_field"></select>
                        <small class="form-text text-muted">{!! get_dialogs('field to match specimen_id against', $active_language) !!}</small>
                    </div>
                </div>
                <small class="form-text text-muted">{!! get_dialogs("What each specimen's identifier is matched against in Specify to find the record to sync into. Leave as collectionobject / catalognumber unless this project is rooted at a different level (e.g. preparation).", $active_language) !!}</small>
            </div>

            <div class="mb-3">
                <label for="specify_collection" class="col-form-label">{!! get_dialogs('Collection to sync into (advanced):', $active_language) !!} {!! help_hint('project_sync_collection', $active_language) !!}</label>
                <input type="number" min="1" class="form-control" id="specify_collection" name="specify_collection" value="{{ old('specify_collection', $project->specify_collection) }}" placeholder="{{ config('services.specify.collection') ?: get_dialogs_simple('the installation default', $active_language) }}">
                <small class="form-text text-muted">{!! get_dialogs('The id of the Specify collection this project sends its validated values to. Leave empty to use the default of the installation.', $active_language) !!}</small>
            </div>

            @can('manage-users')
            <?php
            $manager_choices = \App\Models\User::where('role', 'project_manager')->orderBy('name')->get();
            $chosen_managers = $project->exists ? $project->managers()->pluck('users.id')->all() : [];
            ?>
            <div class="mb-3">
                <label class="col-form-label">{!! get_dialogs('Project managers (besides its creator):', $active_language) !!} {!! help_hint('project_managers', $active_language) !!}</label>
                {{-- tells the server this form really carries the list, so an unchecked box means "remove" and a form without it leaves the managers alone --}}
                <input type="hidden" name="managers_present" value="1">
                @forelse($manager_choices as $manager)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="managers[]" id="manager_{{ $manager->id }}" value="{{ $manager->id }}" {{ in_array($manager->id, $chosen_managers) ? 'checked' : '' }}>
                    <label class="form-check-label" for="manager_{{ $manager->id }}">{{ $manager->name }} <span class="text-muted small">{{ $manager->email }}</span></label>
                </div>
                @empty
                <p class="text-muted small mb-1">{!! get_dialogs('No user has the "Project manager" role yet. Give it to someone in Admin, Users.', $active_language) !!}</p>
                @endforelse
                <small class="form-text text-muted">{!! get_dialogs('Tick the people who may manage this project; untick to remove them. Only users with the "Project manager" role are listed.', $active_language) !!}</small>
            </div>
            @endcan

            <div class="mb-3">
                <label for="others_panel_audience" class="col-form-label">{!! get_dialogs('Show what other users submitted, to:', $active_language) !!} {!! help_hint('project_others_panel', $active_language) !!}</label>
                <?php $audience = $project->others_panel_audience ?? 'project_manager'; ?>
                <select class="form-select" id="others_panel_audience" name="others_panel_audience">
                    <option value="off" {{ $audience === 'off' ? 'selected' : '' }}>{{ get_dialogs_simple('Nobody (off)', $active_language) }}</option>
                    <option value="root" {{ $audience === 'root' ? 'selected' : '' }}>{{ get_dialogs_simple('Root only', $active_language) }}</option>
                    <option value="admin" {{ $audience === 'admin' ? 'selected' : '' }}>{{ get_dialogs_simple('Root and administrators', $active_language) }}</option>
                    <option value="project_manager" {{ $audience === 'project_manager' ? 'selected' : '' }}>{{ get_dialogs_simple('Root, administrators and this project\'s managers', $active_language) }}</option>
                    <option value="all" {{ $audience === 'all' ? 'selected' : '' }}>{{ get_dialogs_simple('All users', $active_language) }}</option>
                </select>
                <small class="form-text text-muted">{!! get_dialogs('On the transcription form, under each field, the values other users already submitted, with a button to use one. With "All users", transcribers see what others typed before submitting, which weakens independent transcription.', $active_language) !!}</small>
            </div>

            <div class="mb-3">
                <label for="sync_overwrite" class="col-form-label">{!! get_dialogs('Sync when Specify already has a value (advanced):', $active_language) !!} {!! help_hint('project_sync_overwrite', $active_language) !!}</label>
                <select class="form-select" id="sync_overwrite" name="sync_overwrite">
                    <option value="overwrite" <?php if (($project->sync_overwrite ?? 'overwrite') !== 'keep_existing') echo 'selected'; ?>>{{ get_dialogs_simple('Overwrite it', $active_language) }}</option>
                    <option value="keep_existing" <?php if (($project->sync_overwrite ?? '') === 'keep_existing') echo 'selected'; ?>>{{ get_dialogs_simple('Keep it and flag a conflict', $active_language) }}</option>
                </select>
                <small class="form-text text-muted">{!! get_dialogs('Empty values and values equal to the validated one are always written. A different value is only replaced when overwriting is on; otherwise that part is left alone and the record is flagged as a conflict for an admin to resolve.', $active_language) !!}</small>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sync_compare_case_insensitive" name="sync_compare_case_insensitive" value="1" <?php if (!empty($project->sync_compare_case_insensitive)) echo 'checked'; ?>>
                            <label class="form-check-label" for="sync_compare_case_insensitive">{!! get_dialogs('Ignore upper/lower case when comparing', $active_language) !!}</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <input type="number" min="0" max="10" class="form-control form-control-sm" id="sync_compare_round_decimals" name="sync_compare_round_decimals" value="{{ $project->sync_compare_round_decimals }}">
                        <small class="form-text text-muted">{!! get_dialogs('Round numbers to this many decimals when comparing (empty = exact)', $active_language) !!}</small>
                    </div>
                </div>
            </div>

            @can('run-sync')
            <?php
            $auto = app(\App\Services\AutoSyncSchedule::class);
            $global = \App\Models\Setting::current();
            $frequency = $project->auto_sync_frequency ?: 'daily';
            ?>
            <div class="mb-3" id="auto_sync_box">
                <input type="hidden" name="auto_sync_present" value="1">
                <label class="col-form-label">{!! get_dialogs('Automatic sync (advanced):', $active_language) !!} {!! help_hint('project_auto_sync', $active_language) !!}</label>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="auto_sync" id="auto_sync" value="1" {{ $project->auto_sync ? 'checked' : '' }}>
                    <label class="form-check-label" for="auto_sync">{!! get_dialogs('Send the validated values to the collection database by itself', $active_language) !!}</label>
                </div>
                <div class="row g-2" id="auto_sync_schedule">
                    <div class="col-md-4">
                        <select class="form-select" name="auto_sync_frequency" id="auto_sync_frequency">
                            <option value="hourly" {{ $frequency === 'hourly' ? 'selected' : '' }}>{{ get_dialogs_simple('Every hour', $active_language) }}</option>
                            <option value="every_n_hours" {{ $frequency === 'every_n_hours' ? 'selected' : '' }}>{{ get_dialogs_simple('Every few hours', $active_language) }}</option>
                            <option value="daily" {{ $frequency === 'daily' ? 'selected' : '' }}>{{ get_dialogs_simple('Every day', $active_language) }}</option>
                            <option value="weekly" {{ $frequency === 'weekly' ? 'selected' : '' }}>{{ get_dialogs_simple('Every week', $active_language) }}</option>
                        </select>
                    </div>
                    <div class="col-md-3" data-for="every_n_hours">
                        <select class="form-select" name="auto_sync_every">
                            @foreach (\App\Services\AutoSyncSchedule::EVERY as $hours)
                                <option value="{{ $hours }}" {{ (int) ($project->auto_sync_every ?: 6) === $hours ? 'selected' : '' }}>{{ $hours }} {{ get_dialogs_simple('hours', $active_language) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3" data-for="weekly">
                        <select class="form-select" name="auto_sync_weekday">
                            @foreach ([1, 2, 3, 4, 5, 6, 0] as $day)
                                <option value="{{ $day }}" {{ (int) $project->auto_sync_weekday === $day || ($project->auto_sync_weekday === null && $day === 1) ? 'selected' : '' }}>{{ $auto->weekday($day) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2" data-for="daily weekly">
                        <input type="time" class="form-control" name="auto_sync_time" value="{{ $project->auto_sync_time ?: '02:00' }}">
                    </div>
                </div>
                <small class="form-text text-muted d-block mt-1">
                    {!! get_dialogs('Times are in the time zone chosen in Settings:', $active_language) !!} {{ $global->schedule_timezone }}.
                    @if (! $global->auto_sync)
                        <span class="text-danger">{!! get_dialogs('The automatic sync is switched off for the whole platform (Settings), so nothing is sent until it is switched on.', $active_language) !!}</span>
                    @endif
                    @if ($project->exists && $project->auto_sync)
                        {!! get_dialogs('Next run:', $active_language) !!} {{ $auto->nextSlot($project, now(), $global->schedule_timezone)->setTimezone($global->schedule_timezone)->format('Y-m-d H:i') }}.
                    @endif
                    @if ($project->exists)
                        <a href="{{ url('sync-runs?project=' . $project->id) }}">{!! get_dialogs('History of the runs', $active_language) !!}</a>
                    @endif
                </small>
            </div>
            <script>
            (function () {
                var frequency = document.getElementById('auto_sync_frequency');
                function show() {
                    document.querySelectorAll('#auto_sync_schedule [data-for]').forEach(function (box) {
                        box.style.display = box.dataset.for.split(' ').indexOf(frequency.value) >= 0 ? '' : 'none';
                    });
                }
                frequency.addEventListener('change', show);
                show();
            })();
            </script>
            @endcan

            <script>
            (function () {
                var apiSchema = @json($api_schema ?? []);
                var currentTable = @json(strtolower($project->sync_root_table ?? 'collectionobject'));
                var currentField = @json(strtolower($project->sync_root_field ?? 'catalognumber'));

                var tableSelect = document.getElementById('sync_root_table');
                var fieldSelect = document.getElementById('sync_root_field');
                var tableNames = Object.keys(apiSchema).sort();

                function addOption(select, value, label, selected) {
                    var opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = label || value;
                    if (selected) opt.selected = true;
                    select.appendChild(opt);
                }

                // The stored value is always lowercase (that's what gets saved and what
                // ApiController::resolveSyncRoot() compares against), but Specify's schema
                // lists table names capitalized (e.g. "Preparation", not "preparation") —
                // match case-insensitively so a previously-saved value still highlights the
                // right option instead of always falling into the "not in schema" fallback.
                function findCaseInsensitive(list, value) {
                    var lower = value.toLowerCase();
                    for (var i = 0; i < list.length; i++) {
                        if (list[i].toLowerCase() === lower) return list[i];
                    }
                    return null;
                }

                function populateTables() {
                    tableSelect.innerHTML = '';
                    var matched = findCaseInsensitive(tableNames, currentTable);
                    tableNames.forEach(function (name) {
                        addOption(tableSelect, name, name, name === matched);
                    });
                    if (!matched) {
                        // Specify schema unreachable, or this project points at a table the
                        // schema no longer lists — keep the stored value selectable either way.
                        addOption(tableSelect, currentTable, currentTable + (tableNames.length ? ' (not in current Specify schema)' : ''), true);
                    }
                }

                function populateFields(preferredField) {
                    fieldSelect.innerHTML = '';
                    var tableSchema = apiSchema[tableSelect.value];
                    var fieldNames = tableSchema ? Object.keys(tableSchema.properties || {}).sort() : [];
                    // Specify's REST API always exposes a table's primary key as "id" —
                    // never as the DB column name the schema documents it under (e.g.
                    // "preparationid" for table "Preparation"), which isn't a real,
                    // queryable REST field at all: drop that alias (it only ever misleads)
                    // and offer "id" instead, which the schema never lists on its own.
                    if (fieldNames.length) {
                        var pkAlias = tableSelect.value.toLowerCase() + 'id';
                        fieldNames = fieldNames.filter(function (name) { return name.toLowerCase() !== pkAlias; });
                        if (fieldNames.indexOf('id') === -1) {
                            fieldNames.unshift('id');
                        }
                    }
                    var matched = preferredField ? findCaseInsensitive(fieldNames, preferredField) : null;
                    fieldNames.forEach(function (name) {
                        addOption(fieldSelect, name, name, name === matched);
                    });
                    if (preferredField && !matched) {
                        addOption(fieldSelect, preferredField, preferredField + (fieldNames.length ? ' (not in current Specify schema)' : ''), true);
                    }
                }

                populateTables();
                populateFields(currentField);

                tableSelect.addEventListener('change', function () {
                    populateFields('');
                });
            })();
            </script>

            <div class="mb-3">
                <label for="name" class="col-form-label">{!! get_dialogs('Cover image:', $active_language) !!}</label>
                <input name="cover_image" type="file">
            </div>


            <div class="form-group mt-4">
                <ul class="nav nav-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="project_title-tab" data-bs-toggle="tab" data-bs-target="#project_title-tab-pane" type="button" role="tab" aria-controls="project_title-tab-pane" aria-selected="true">{!! get_dialogs('Title', $active_language) !!}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="project_abstract-tab" data-bs-toggle="tab" data-bs-target="#project_abstract-tab-pane" type="button" role="tab" aria-controls="project_abstract-tab-pane" aria-selected="false">{!! get_dialogs('Abstract', $active_language) !!}</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="project_body-tab" data-bs-toggle="tab" data-bs-target="#project_body-tab-pane" type="button" role="tab" aria-controls="project_body-tab-pane" aria-selected="false">{!! get_dialogs('Description', $active_language) !!}</button>
                    </li>
                </ul>
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade show active" id="project_title-tab-pane" role="tabpanel" aria-labelledby="project_title-tab" tabindex="0">
                        <?php
                        foreach ($languages as $language) {
                        ?>
                            <div class="form-group">
                                <label for="project_title_{{$language->iso2}}" class="col-form-label"><img width="20px" src="{{url('/flags/4x3/'.$language->iso2 .'.svg')}}" /> {{$language->iso2}}:</label>
                                <textarea class="form-control ckeditor" id="project_title_{{$language->iso2}}" name="project_title_{{$language->iso2}}"><?php echo get_dialogs_simple('project_title_' . $project->id, $language->iso2); ?></textarea>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <div class="tab-pane fade" id="project_abstract-tab-pane" role="tabpanel" aria-labelledby="project_abstract-tab" tabindex="0">
                        <?php
                        foreach ($languages as $language) {
                        ?>
                            <div class="form-group">
                                <label for="project_abstract_{{$language->iso2}}" class="col-form-label"><img width="20px" src="{{url('/flags/4x3/'.$language->iso2 .'.svg')}}" /> {{$language->iso2}}:</label>
                                <textarea class="form-control ckeditor" id="project_abstract_{{$language->iso2}}" name="project_abstract_{{$language->iso2}}"><?php echo get_dialogs_simple('project_abstract_' . $project->id, $language->iso2); ?></textarea>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <div class="tab-pane fade" id="project_body-tab-pane" role="tabpanel" aria-labelledby="project_body-tab" tabindex="0">
                        <?php
                        foreach ($languages as $language) {
                        ?>
                            <div class="form-group">
                                <label for="project_body_{{$language->iso2}}" class="col-form-label"><img width="20px" src="{{url('/flags/4x3/'.$language->iso2 .'.svg')}}" /> {{$language->iso2}}:</label>
                                <textarea class="form-control ckeditor" id="project_body_{{$language->iso2}}" name="project_body_{{$language->iso2}}"><?php echo get_dialogs_simple('project_body_' . $project->id, $language->iso2); ?></textarea>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            </div>

            <input type="hidden" name="project_id" id="project_id" value="{{$project->id}}">
            <br>
            <button type="submit" class="btn btn-primary" form="project_edit">{!! get_dialogs('Submit', $active_language) !!}</button>

        </form>
    </div>
</div>