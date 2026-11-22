@extends( 'layouts.app' )

@section( 'content' )
<div class="container">
	<h1>{!! get_dialogs('Fields', $active_language) !!}</h1>
</div>
<div class="container">
	<div id="flash-message" class="alert d-none">
	</div>
	<div class="card">
		<div class="card-body">
			<button type="button" class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#field_Modal" title="{{ get_dialogs_simple('Add a new field', $active_language) }}">
				<i class="bi bi-plus-lg"></i>
			</button>

			@include('layouts.partials.table_tools', ['tableId' => 'fields-table', 'rowSelector' => 'tbody tr[id^="item-"]', 'azColumn' => 1, 'placeholder' => get_dialogs_simple('Search fields...', $active_language)])

			<table class="table table-striped" id="fields-table">
				<thead>
					<tr>
						<th scope="col"></th>
						<th scope="col">{!! get_dialogs('Name', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Type', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Options', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Required', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Deducted box', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Have doubts box', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Multiple values', $active_language) !!}</th>
						<th scope="col">{!! get_dialogs('Minimum user level', $active_language) !!}</th>
						<th></th>
						<th></th>
						<th></th>
					</tr>
				</thead>
				<tbody class="sort">

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

					/**
					 * Removes characters from the middle of the string to ensure it is no more
					 * than $maxLength characters long.
					 *
					 * Removed characters are replaced with "..."
					 *
					 * This method will give priority to the right-hand side of the string when
					 * data is truncated.
					 *
					 * @param $string
					 * @param $maxLength
					 * @return string
					 */
					if (!function_exists('truncateMiddle')) {

						function truncateMiddle($string, $maxLength = 150)
						{
							// Early exit if no truncation necessary
							if (strlen($string) <= $maxLength) return $string;
							$numRightChars = ceil($maxLength / 2);
							$numLeftChars = floor($maxLength / 2) - 3; // to accommodate the "..."
							return sprintf("%s ...<br> [...] <br>... %s", substr($string, 0, $numLeftChars), substr($string, 0 - $numRightChars));
						}
					}
					foreach ($fields as $field) {
					?>
						<tr id="item-{{$field->id}}">
							<td style="cursor:grab;"><i class="bi bi-grip-vertical"></i></td>

							<td>{!!$field->name!!}</td>
							<!--
						<td>{!!$field->question!!}</td>
						<td>{!!$field->text!!}</td>
					-->
							<td>{!!$field->type!!}</td>
							<td>{!!truncateMiddle(nl2br($field->option_values))!!}</td>
							<td>{!!binary_to_yes_no($field->required)!!}</td>
							<td>{!!binary_to_yes_no($field->deducted)!!}</td>
							<td>{!!binary_to_yes_no($field->have_doubts)!!}</td>
							<td>{!!binary_to_yes_no($field->multiple)!!}</td>
							<td>{!!$field->user_category->category!!}</td>
							<td>
								<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#field_Modal" data-name="{!!$field->name!!}" data-type="{!!$field->type!!}" data-explode_fields="{!!$field->explode_fields!!}" data-explode_char="{!!$field->explode_char!!}" data-explode_save_direction="{!!$field->explode_save_direction!!}" data-autocomplete="{!!$field->autocomplete!!}" data-option_values="{{$field->option_values}}" data-choices="{{ json_encode($field->choicesForEditor(), JSON_INVALID_UTF8_SUBSTITUTE) }}" data-choices_separator="{{ $field->choices_separator }}" data-required="{!!$field->required!!}" data-deducted="{!!$field->deducted!!}" data-have_doubts="{!!$field->have_doubts!!}" data-multiple="{!!$field->multiple!!}" data-min_user_level="{!!$field->min_user_level!!}" data-na_defaults="{{ $field->na_defaults }}" data-field_id="{!!$field->id!!}" <?php foreach ($languages as $language) {
																																																																																																																																																																					?> data-fields_name_{{$language->iso2}}="<?php echo get_dialogs_simple('fields_name_' . $field->id, $language->iso2); ?>" data-fields_question_{{$language->iso2}}="{{get_dialogs_simple('fields_question_'.$field->id, $language->iso2)}}" data-fields_text_{{$language->iso2}}="{{get_dialogs_simple('fields_text_'.$field->id, $language->iso2)}}" data-fields_helper_{{$language->iso2}}="{{get_dialogs_simple('fields_helper_'.$field->id, $language->iso2)}}" <?php
																																																																																																																																																																																																																																																																																					}
																																																																																																																																																																																																																																																																																						?>>
									<i class="bi bi-pencil"></i>
								</button>
							</td>
							<td>
								<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#api_Modal" data-name="{!!$field->name!!}" data-api_table_field="{!!$field->api_table_field!!}" data-explode_fields="{!!$field->explode_fields!!}" data-explode_char="{!!$field->explode_char!!}" data-field_id="{!!$field->id!!}" data-mappings="{{ $field->mapping->toJson() }}" data-required_defaults="{{ json_encode($field->required_defaults ?? new stdClass()) }}" data-concatenate_parts="{{ $field->concatenate_parts ? '1' : '0' }}">
									<i class="bi bi-link-45deg"></i>
								</button>

							</td>
							<td>
								<button type="button" class="btn btn-danger btn-sm delete_field" title="{{ get_dialogs_simple('Delete field', $active_language) }}" id="{{$field->id}}">
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



<div class="modal fade" id="field_Modal" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="">{!! get_dialogs('Edit Field', $active_language) !!}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form method="post" action="field_edit" enctype="multipart/form-data" id="field_edit">
					@csrf
					<div class="form-group">
						<label for="name" class="col-form-label">{!! get_dialogs('Name:', $active_language) !!}</label>
						<input type="text" class="form-control" id="name" name="name">
					</div>
					<div class="form-group">
						<label for="type" class="col-form-label">{!! get_dialogs('Type:', $active_language) !!} {!! help_hint('field_type', $active_language) !!}</label>
						<select class="form-select" id="type" name="type">
							{{-- Deliberately NOT localized: these <option> tags have no explicit
							     value= attribute, so the *visible text itself* is what gets
							     submitted as field->type — translating it would change the stored
							     value the rest of the app matches against (e.g. == 'text'). --}}
							<option>text</option>
							<option>date</option>
							<option>textarea</option>
							<option>select</option>
							<option>radio</option>
							<option>checkboxes</option>
						</select>
						<div class="form-text mb-2">{!! get_dialogs('Only what applies to this type is shown. What does not apply is cleared when the field is saved.', $active_language) !!}</div>

						<div class="form-group" id="explode_fields_div" data-types="text date">
							<label for="explode_fields" class="col-form-label">{!! get_dialogs('Explode into fields (use the desired separator):', $active_language) !!} {!! help_hint('field_explode', $active_language) !!}</label>
							<input type="text" class="form-control" id="explode_fields" name="explode_fields">
						</div>

						<div class="form-group" id="explode_char_div" data-types="text date">
							<label for="explode_char" class="col-form-label">{!! get_dialogs('separator:', $active_language) !!}</label>
							<input type="text" class="form-control" id="explode_char" name="explode_char">
						</div>

<div class="form-group" id="na_defaults_div">
    <label for="na_defaults" class="col-form-label">
        {!! get_dialogs('Default for', $active_language) !!} <code>n.a.</code> {!! help_hint('field_na_defaults', $active_language) !!}
        <small class="text-muted d-block">
            {!! get_dialogs('Value to ingest when the transcribed value is', $active_language) !!} <code>n.a.</code>
            {!! get_dialogs('Leave empty to skip ingestion of n.a. records entirely.', $active_language) !!}
            {!! get_dialogs('Can itself be n.a. if that is a valid value in Specify.', $active_language) !!}
        </small>
    </label>
    <input type="text" class="form-control" id="na_defaults" name="na_defaults"
           placeholder="{{ get_dialogs_simple('e.g. n.a.||Denmark|||||Denmark|Denmark / leave empty to skip ingestion of n.a.', $active_language) }}">
</div>


						<div class="form-group" id="explode_save_direction_div" data-types="text date">
							<label for="explode_save_direction" class="col-form-label">{!! get_dialogs('Elements save direction:', $active_language) !!}</label>
							<select class="form-select" id="explode_save_direction" name="explode_save_direction">
								<option value="direct">{{ get_dialogs_simple('direct', $active_language) }}</option>
								<option value="reverse">{{ get_dialogs_simple('reverse', $active_language) }}</option>
							</select>
						</div>

						<div class="form-group" id="autocomplete_div" data-types="text textarea">
							<label for="autocomplete" class="col-form-label">{!! get_dialogs('Autocomplete:', $active_language) !!} {!! help_hint('field_autocomplete', $active_language) !!}</label>
							<select class="form-select" id="autocomplete" name="autocomplete">
								<option value="1">{{ get_dialogs_simple('yes', $active_language) }}</option>
								<option value="0">{{ get_dialogs_simple('no', $active_language) }}</option>
							</select>
						</div>


						{{-- One box for every type that has a list: the choices of a select/radio/checkboxes field, or the
						     suggestions the autocomplete of a text/textarea field offers (it also offers what was already
						     submitted). Which one it is, and whether it shows at all, follows the type (see applyType()). --}}
						<div id="options_group" class="form-group m-2">
							<label for="option_values" class="col-form-label">
								<span class="options-label-choice">{!! get_dialogs('Options (separated by a line break):', $active_language) !!}</span>
								<span class="options-label-suggest d-none">{!! get_dialogs('Suggestions for the autocomplete (separated by a line break). What was already submitted is suggested too:', $active_language) !!}</span>
							</label>
							<textarea class="form-control" id="option_values" name="option_values"></textarea>
						</div>

						{{-- The options of a select / radio / checkboxes field: the value that is saved (no language) and, per
						     language, what is shown instead. Sent as one JSON field (a long list, such as a list of countries, would
						     otherwise be hundreds of inputs and be cut by the server's limit on the number of fields). --}}
						<div id="choices_group" class="form-group m-2 d-none">
							<label class="col-form-label">{!! get_dialogs('Options', $active_language) !!} {!! help_hint('field_options', $active_language) !!}</label>
							<div class="form-text mb-2">{!! get_dialogs('The value is what is saved and sent to Specify, the same in every language. The label is what each language shows instead; where a language has none, the value is shown.', $active_language) !!}</div>
							<div class="d-flex flex-wrap gap-2 mb-2">
								<button type="button" class="btn btn-outline-success btn-sm" id="choice_add"><i class="bi bi-plus-lg"></i> {!! get_dialogs('Add option', $active_language) !!}</button>
								<button type="button" class="btn btn-outline-secondary btn-sm" id="choice_import_toggle"><i class="bi bi-clipboard-plus"></i> {!! get_dialogs('Import from text', $active_language) !!}</button>
								<button type="button" class="btn btn-outline-secondary btn-sm" id="choice_fill" title="{{ get_dialogs_simple('Adds the values people have already submitted for this field', $active_language) }}"><i class="bi bi-magic"></i> {!! get_dialogs('Fill with submitted values', $active_language) !!}</button>
							</div>
							<div id="choice_import_panel" class="d-none mb-2 p-2 border rounded bg-light">
								<div class="form-text mb-1">{!! get_dialogs('Paste one option per line. Separate with ";" the value and then the label in each language, in this order:', $active_language) !!} <code>{{ collect($languages)->pluck('iso2')->implode(' ; ') }}</code>. {!! get_dialogs('Only the value is needed.', $active_language) !!}</div>
								<textarea id="choice_import_text" class="form-control form-control-sm mb-2" rows="5" placeholder="Spikes&#10;January ; January ; Januar"></textarea>
								<button type="button" class="btn btn-primary btn-sm" id="choice_import_do">{!! get_dialogs('Import', $active_language) !!}</button>
								<button type="button" class="btn btn-link btn-sm" id="choice_import_close">{!! get_dialogs('Close', $active_language) !!}</button>
							</div>
							<div class="table-responsive" style="max-height: 22rem; overflow-y: auto">
								<table class="table table-sm align-middle mb-0">
									<thead>
										<tr>
											<th style="width: 1.5rem"></th>
											<th>{!! get_dialogs('Value saved', $active_language) !!}</th>
											@foreach($languages as $language)
												<th>{{ $language->iso2 }}</th>
											@endforeach
											<th style="width: 2rem"></th>
										</tr>
									</thead>
									<tbody id="choices_body"></tbody>
								</table>
							</div>
							<div class="form-text" id="choices_count"></div>
							<input type="hidden" name="choices_json" id="choices_json" disabled>
						</div>

						{{-- Only for checkboxes: how the ticked options are saved. --}}
						<div class="form-group m-2" id="choices_separator_div" data-types="checkboxes">
							<label for="choices_separator" class="col-form-label">{!! get_dialogs('Separator for the ticked options:', $active_language) !!} {!! help_hint('field_choices_separator', $active_language) !!}</label>
							<input type="text" class="form-control" id="choices_separator" name="choices_separator" maxlength="10" placeholder=";" style="max-width: 8rem">
							<div class="form-text">{!! get_dialogs('Empty: each ticked option is saved as a line of its own. With a separator (for example ;) the ticked options are saved as one value, joined with it and in the order of the list, e.g. 1;3;5. Spaces at the ends are ignored, and the separator cannot be part of an option.', $active_language) !!}</div>
						</div>
					</div>
					<div class="form-group">
						<label for="required" class="col-form-label">{!! get_dialogs('Required:', $active_language) !!}</label>
						<select class="form-select" id="required" name="required">
							<option value="1">{{ get_dialogs_simple('yes', $active_language) }}</option>
							<option value="0">{{ get_dialogs_simple('no', $active_language) }}</option>
						</select>
					</div>
					<div class="form-group">
						<label for="deducted" class="col-form-label">{!! get_dialogs('Deducted box:', $active_language) !!} {!! help_hint('field_deducted', $active_language) !!}</label>
						<select class="form-select" id="deducted" name="deducted">
							<option value="1">{{ get_dialogs_simple('yes', $active_language) }}</option>
							<option value="0">{{ get_dialogs_simple('no', $active_language) }}</option>
						</select>
					</div>
					<div class="form-group">
						<label for="have_doubts" class="col-form-label">{!! get_dialogs('Have doubts box:', $active_language) !!} {!! help_hint('field_have_doubts', $active_language) !!}</label>
						<select class="form-select" id="have_doubts" name="have_doubts">
							<option value="1">{{ get_dialogs_simple('yes', $active_language) }}</option>
							<option value="0">{{ get_dialogs_simple('no', $active_language) }}</option>
						</select>
					</div>
					<div class="form-group">
						<label for="multiple" class="col-form-label">{!! get_dialogs('Multiple values:', $active_language) !!} {!! help_hint('field_multiple', $active_language) !!}</label>
						<select class="form-select" id="multiple" name="multiple">
							<option value="1">{{ get_dialogs_simple('yes', $active_language) }}</option>
							<option value="0">{{ get_dialogs_simple('no', $active_language) }}</option>
						</select>
					</div>

					<div class="form-group ">
						<label for="min_user_level" class="col-form-label">{!! get_dialogs('Minimum user level:', $active_language) !!} {!! help_hint('field_min_level', $active_language) !!}</label>
						<select class="form-select" id="min_user_level" name="min_user_level">
							<?php
							foreach ($user_categories as $user_category) {
							?>
								<option value="{{$user_category->id}}">{{$user_category->category}}</option>
							<?php
							}
							?>
						</select>
					</div>

					<div class="form-group mt-4">

						<ul class="nav nav-tabs" id="myTab" role="tablist">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" id="fields_name-tab" data-bs-toggle="tab" data-bs-target="#fields_name-tab-pane" type="button" role="tab" aria-controls="fields_name-tab-pane" aria-selected="true">{!! get_dialogs('Name to display', $active_language) !!}</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="fields_question-tab" data-bs-toggle="tab" data-bs-target="#fields_question-tab-pane" type="button" role="tab" aria-controls="fields_question-tab-pane" aria-selected="false">{!! get_dialogs('Question', $active_language) !!}</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="fields_text-tab" data-bs-toggle="tab" data-bs-target="#fields_text-tab-pane" type="button" role="tab" aria-controls="fields_text-tab-pane" aria-selected="false">{!! get_dialogs('Text', $active_language) !!}</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="fields_helper-tab" data-bs-toggle="tab" data-bs-target="#fields_helper-tab-pane" type="button" role="tab" aria-controls="fields_helper-tab-pane" aria-selected="false">{!! get_dialogs('Helper', $active_language) !!}</button>
							</li>
						</ul>
						<div class="tab-content" id="myTabContent">
							<div class="tab-pane fade show active" id="fields_name-tab-pane" role="tabpanel" aria-labelledby="fields_name-tab" tabindex="0">
								<?php
								foreach ($languages as $language) {
								?>
									<div class="form-group">
										<label for="name" class="col-form-label">{{$language->iso2}}:</label>
										<textarea class="form-control ckeditor" id="fields_name_{{$language->iso2}}" name="fields_name_{{$language->iso2}}"></textarea>
									</div>
								<?php
								}
								?>
							</div>
							<div class="tab-pane fade" id="fields_question-tab-pane" role="tabpanel" aria-labelledby="fields_question-tab" tabindex="0">
								<?php
								foreach ($languages as $language) {
								?>
									<div class="form-group">
										<label for="question" class="col-form-label">{{$language->iso2}}:</label>
										<textarea class="form-control ckeditor" id="fields_question_{{$language->iso2}}" name="fields_question_{{$language->iso2}}"></textarea>
									</div>
								<?php
								}
								?>
							</div>
							<div class="tab-pane fade" id="fields_text-tab-pane" role="tabpanel" aria-labelledby="fields_text-tab" tabindex="0">
								<?php
								foreach ($languages as $language) {
								?>
									<div class="form-group">
										<label for="text" class="col-form-label">{{$language->iso2}}:</label>
										<textarea class="form-control ckeditor" id="fields_text_{{$language->iso2}}" name="fields_text_{{$language->iso2}}"></textarea>
									</div>
								<?php
								}
								?>
							</div>
							<div class="tab-pane fade" id="fields_helper-tab-pane" role="tabpanel" aria-labelledby="fields_helper-tab" tabindex="0">
								<?php
								foreach ($languages as $language) {
								?>
									<div class="form-group">
										<label for="text" class="col-form-label">{{$language->iso2}}:</label>
										<textarea class="form-control ckeditor" id="fields_helper_{{$language->iso2}}" name="fields_helper_{{$language->iso2}}"></textarea>
									</div>
								<?php
								}
								?>
							</div>
						</div>
					</div>

					<input type="hidden" name="field_id" id="field_id" value="">
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
				<button type="submit" class="btn btn-primary" form="field_edit">{!! get_dialogs('Submit', $active_language) !!}</button>
			</div>
		</div>
	</div>
</div>


<div class="modal fade" id="api_Modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl" role="document">
		<div class="modal-content">

			<div class="modal-header">
				<h5 class="modal-title">{!! get_dialogs('Edit API connection', $active_language) !!}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>

			<div class="modal-body">

				<label class="col-form-label">{!! get_dialogs('This field should be mapped to:', $active_language) !!}</label>

        <form method="post" action="api_field_edit" enctype="multipart/form-data" id="api_field_edit">
            @csrf
            <input type="hidden" name="field_id" id="api_field_id">
    <div id="concatenate-wrapper" class="mb-3 d-none">
        <div class="form-check">
            <input class="form-check-input" type="checkbox"
                   name="concatenate_parts" id="concatenate_parts" value="1">
            <label class="form-check-label" for="concatenate_parts">
                {!! get_dialogs('Concatenate all parts into a single mapped field', $active_language) !!}
                <small class="text-muted d-block">
                    {!! get_dialogs('Parts will be joined with the field separator', $active_language) !!} (<span id="concat-char-preview"></span>)
                    {!! get_dialogs('and mapped as one value.', $active_language) !!}
                </small>
            </label>
        </div>
    </div>
    <div id="nodeContainer"></div>
    <div id="required-defaults-global"></div>
        </form>


			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{!! get_dialogs('Close', $active_language) !!}</button>
				<button type="submit" class="btn btn-primary" form="api_field_edit">{!! get_dialogs('Submit', $active_language) !!}</button>
			</div>

		</div>
	</div>
</div>


<?php
$tables_options_array = [];
foreach ($api_schema as $specify_table_name => $specify_table) {
	ksort($specify_table['properties']);
	foreach ($specify_table['properties'] as $specify_field_name => $specify_field) {
		$tables_options_array[] = $specify_table_name . '.' . $specify_field_name;
	}
}
?>


<script src="{{ asset('js/ckeditor/ckeditor.js') }}"></script>

<script type="text/javascript">
// $j is just this page's own alias for jQuery, not a real noConflict() need
// (this page loads only CKEditor, which doesn't touch "$" at all) — calling
// noConflict() here released the global "$" for every OTHER script on the
// page (the layout, the shared dialog-edit modal, ...), breaking all of them
// with "$ is not a function" the moment this page's own script ran.
var $j = jQuery;
var api_schema = @json($api_schema);
var APP_URL = "{{ url('/') }}";

// UI strings for this admin-only mapping editor, computed once via PHP
// (json-encoded, not embedded raw, so an admin-edited dialog containing a
// quote can't break this script) instead of hardcoded in the template
// literals below.
var STR_RANK_PLACEHOLDER = @json(get_dialogs_simple('-- rank --', $active_language));
var STR_CHOOSE_PLACEHOLDER = @json(get_dialogs_simple('-- choose --', $active_language));
var STR_ACTION_PLACEHOLDER = @json(get_dialogs_simple('-- action --', $active_language));
var STR_CONFIRM_DELETE_FIELD = @json(get_dialogs_simple('Delete this field?', $active_language));
var STR_ERROR_DELETING_FIELD = @json(get_dialogs_simple('Error deleting field.', $active_language));
var STR_DATE_FIELD_DETECTED = @json(get_dialogs_simple('Date field detected', $active_language));
var STR_ALSO_SUBMITTED_AUTOMATICALLY = @json(get_dialogs_simple('will also be submitted automatically:', $active_language));
var STR_FULL_DATE = @json(get_dialogs_simple('full date (year-month-day),', $active_language));
var STR_YEAR_MONTH = @json(get_dialogs_simple('year-month,', $active_language));
var STR_YEAR_ONLY = @json(get_dialogs_simple('year only.', $active_language));
var STR_PRECISION_DEDUCED = @json(get_dialogs_simple('The precision is deduced from the validated value.', $active_language));
var STR_REQUIRED_FIELD_DEFAULTS = @json(get_dialogs_simple('Required field defaults', $active_language));
var STR_USED_ONLY_WHEN_NOT_PROVIDED = @json(get_dialogs_simple('used only when not provided by the transcription', $active_language));
var STR_MAPPED_BADGE = @json(get_dialogs_simple('mapped', $active_language));
var STR_MAPPED_TITLE = @json(get_dialogs_simple('This field is covered by the mapping - value here is a fallback only', $active_language));
var STR_FALLBACK_PLACEHOLDER = @json(get_dialogs_simple('fallback if transcribed value is empty', $active_language));
var STR_DEFAULT_PLACEHOLDER = @json(get_dialogs_simple('default value if not transcribed', $active_language));

// Cache de campos obrigatórios por tabela (evita AJAX repetidos)
var requiredFieldsCache = {};
 
$j(document).ready(function () {
 
    // ── loadRanks ────────────────────────────────────────────
    function loadRanks(tableName, nodeId, currentRankId = null) {
        var $placeholder = $j(`#rank-placeholder-${nodeId}`);
        if (api_schema[tableName] && api_schema[tableName].properties.rankid) {
            $j.get(APP_URL + '/api_get-table-ranks/' + tableName, function (ranks) {
                var html = `<select name="levels[${nodeId}][rank_id]" class="form-select">
                                <option value="">${STR_RANK_PLACEHOLDER}</option>`;
                ranks.forEach(function (r) {
                    var selected = (currentRankId == r.rank) ? 'selected' : '';
                    html += `<option value="${r.rank}" ${selected}>${r.name}</option>`;
                });
                html += '</select>';
                $placeholder.html(html);
            });
        } else {
            $placeholder.empty();
        }
    }

function checkDateField(param, nodeId) {
    $j('#date-notice-' + nodeId).remove(); // clear previous notice

    if (!param) return;

    var parts = param.split('.');
    if (parts.length < 2) return;

    var tableName = parts[0];
    var fieldName = parts[1].toLowerCase();

    var tableSchema = api_schema[tableName];
    if (!tableSchema) return;

    var fieldSchema = tableSchema.properties[fieldName];
    if (!fieldSchema || fieldSchema.format !== 'date') return;

    // Check that the precision field also exists
    var precisionField = fieldName + 'precision';
    if (!tableSchema.properties[precisionField]) return;

    var notice = `<div id="date-notice-${nodeId}" class="alert alert-info py-1 px-2 mt-1 mb-0" style="font-size:12px;">
        <i class="bi bi-calendar-check"></i>
        <b>${STR_DATE_FIELD_DETECTED}</b> — <code>${tableName}.${precisionField}</code> ${STR_ALSO_SUBMITTED_AUTOMATICALLY}
        <code>1</code> = ${STR_FULL_DATE}
        <code>2</code> = ${STR_YEAR_MONTH}
        <code>3</code> = ${STR_YEAR_ONLY}
        ${STR_PRECISION_DEDUCED}
    </div>`;

    $j('#rank-placeholder-' + nodeId).closest('.node-level').append(notice);
}    
 
    // ── rebuildRequiredDefaults ──────────────────────────────
    // Recolhe todas as tabelas únicas dos nós activos,
    // pede os campos obrigatórios (com cache), e reconstrói
    // a secção global no fundo do formulário.
    function rebuildRequiredDefaults(savedDefaults) {
        savedDefaults = savedDefaults || currentSavedDefaults || {};
 
        // Tabelas únicas presentes nos nós activos (excluindo collectionobject)
        var tables = [];
        $j('#nodeContainer .param-select').each(function () {
            var val = $j(this).val();
            if (!val) return;
            var table = val.split('.')[0].toLowerCase();
            if (table === 'collectionobject') return;
            if (!tables.includes(table)) tables.push(table);
        });
 
        // Campos já cobertos pelo mapeamento (param.campo -> tabela.campo)
        var mappedFields = {};
        $j('#nodeContainer .param-select').each(function () {
            var val = $j(this).val();
            if (!val) return;
            var parts = val.split('.');
            if (parts.length < 2) return;
            var key = parts[0].toLowerCase() + '.' + parts[1].toLowerCase();
            mappedFields[key] = true;
        });
 
        if (tables.length === 0) {
            $j('#required-defaults-global').empty();
            return;
        }
 
        // Carregar campos obrigatórios de cada tabela (com cache)
        var promises = tables.map(function (table) {
            if (requiredFieldsCache[table] !== undefined) {
                return $j.when(requiredFieldsCache[table]);
            }
            return $j.get(APP_URL + '/api_get-table-required-fields/' + table)
                .then(function (data) {
                    requiredFieldsCache[table] = data;
                    return data;
                });
        });
 
        $j.when.apply($j, promises).then(function () {
            var allRequired = {}; // "Table.fieldName" -> {name, type, length, relatedModel}
 
            tables.forEach(function (table, i) {
                var fields = arguments.length > 1 ? arguments[i] : arguments[0];
                // $j.when com um único argumento não envolve em array
                if (!Array.isArray(fields)) fields = [fields];
                // quando só há uma tabela, $.when passa os args directamente
                var data = requiredFieldsCache[table] || [];
                data.forEach(function (f) {
                    var key = table + '.' + f.name.toLowerCase();
                    allRequired[key] = { table: table, field: f };
                });
            });
 
            // Reconstruir com os dados já em cache
            var html = '';
            var keys = Object.keys(allRequired).sort();
 
            if (keys.length === 0) {
                $j('#required-defaults-global').empty();
                return;
            }
 
            html += `<div class="mt-3 p-3 border rounded bg-light">
                        <h6 class="mb-2">
                            <i class="bi bi-exclamation-circle text-warning"></i>
                            ${STR_REQUIRED_FIELD_DEFAULTS}
                            <small class="text-muted fw-normal">— ${STR_USED_ONLY_WHEN_NOT_PROVIDED}</small>
                        </h6>`;
 
            var currentTable = null;
            keys.forEach(function (key) {
                var entry    = allRequired[key];
                var table    = entry.table;
                var f        = entry.field;
                var isMapped = mappedFields[key] === true;
                var savedVal = savedDefaults[key] || '';
 
                if (table !== currentTable) {
                    if (currentTable !== null) html += '</div>'; // close previous group
                    html += `<div class="mb-2">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1">${table}</small>`;
                    currentTable = table;
                }
 
                var typeHint = f.type === 'relation'
                    ? `URI → ${f.relatedModel}`
                    : f.type.split('.').pop() + (f.length ? `/${f.length}` : '');
 
                var mappedBadge = isMapped
                    ? `<span class="badge bg-success ms-1" title="${STR_MAPPED_TITLE}">${STR_MAPPED_BADGE} ✓</span>`
                    : '';
 
                // a required relation is chosen among the existing objects (filled in below); the rest is typed
                var control = f.type === 'relation'
                    ? `<select class="form-select form-select-sm relation-default"
                              name="required_defaults[${key}]"
                              data-related="${$j('<div>').text(f.relatedModel).html()}"
                              data-saved="${$j('<div>').text(savedVal).html()}">
                           <option value="">${isMapped ? STR_FALLBACK_PLACEHOLDER : STR_DEFAULT_PLACEHOLDER}</option>
                       </select>`
                    : `<input type="text"
                              class="form-control form-control-sm"
                              name="required_defaults[${key}]"
                              value="${$j('<div>').text(savedVal).html()}"
                              placeholder="${isMapped ? STR_FALLBACK_PLACEHOLDER : STR_DEFAULT_PLACEHOLDER}">`;

                html += `<div class="row align-items-center mb-1">
                            <label class="col-sm-3 col-form-label col-form-label-sm text-end">
                                ${f.name} ${mappedBadge}
                                <small class="text-muted d-block">${typeHint}</small>
                            </label>
                            <div class="col-sm-9">${control}</div>
                          </div>`;
            });
 
            if (currentTable !== null) html += '</div>'; // close last group
            html += '</div>';
 
            $j('#required-defaults-global').html(html);

            // fill each list of a required relation with the objects it can point to
            $j('#required-defaults-global .relation-default').each(function () {
                var $select = $j(this);
                var saved   = $select.data('saved') || '';
                $j.get(APP_URL + '/api_get-related-options/' + $select.data('related')).done(function (options) {
                    var found = false;
                    (options || []).forEach(function (o) {
                        found = found || o.uri === saved;
                        $select.append($j('<option>').val(o.uri).text(o.label).prop('selected', o.uri === saved));
                    });
                    // a saved value the list does not have (another collection) stays, so saving does not lose it
                    if (saved && !found) {
                        $select.append($j('<option>').val(saved).text(saved).prop('selected', true));
                    }
                });
            });
        });
    }
 
    // ── Modal API ────────────────────────────────────────────
    var currentSavedDefaults = {};
 
$j('#api_Modal').on('show.bs.modal', function (event) {
    var button             = $j(event.relatedTarget);
    var name               = button.data('name') || "";
    var explode_fields     = button.data('explode_fields') || "";
    var explode_char       = button.data('explode_char') || ",";
    var field_id           = button.data('field_id');
    var existingMappings   = button.data('mappings') || [];
    var isConcatenated     = button.data('concatenate_parts') == '1';
    currentSavedDefaults   = button.data('required_defaults') || {};
 
    if (typeof currentSavedDefaults === 'string') {
        try { currentSavedDefaults = JSON.parse(currentSavedDefaults); } catch(e) { currentSavedDefaults = {}; }
    }
 
    var $container = $j("#nodeContainer");
    $container.empty();
    $j('#required-defaults-global').empty();
    $j('#api_field_id').val(field_id);
    requiredFieldsCache = {};
 
    var mapping_parts = explode_fields !== "" ? explode_fields.split(explode_char) : ["value"];
    var nodeCounter   = 0;
    var allOptions    = @json($tables_options_array);
 
    // Show concatenate checkbox only when there are multiple parts
    var $concatWrapper = $j('#concatenate-wrapper');
    if (mapping_parts.length > 1) {
        $concatWrapper.removeClass('d-none');
        $j('#concat-char-preview').text('"' + explode_char + '"');
    } else {
        $concatWrapper.addClass('d-none');
    }
    $j('#concatenate_parts').prop('checked', isConcatenated);
 
    function filterOptions(prevValue) {
        if (!prevValue) return allOptions;
        var parts = prevValue.split('.');
        if (parts.length < 2) return allOptions;
        var prefix        = parts[1].toLowerCase();
        var singularPrefix = prefix.endsWith('s') ? prefix.slice(0, -1) : prefix;
        return allOptions.filter(function (opt) {
            var optParts  = opt.split('.');
            if (optParts.length < 2) return false;
            var candidate = optParts[0].toLowerCase();
            return candidate === prefix || candidate === singularPrefix;
        });
    }
 
    function addLevel(parentNodeId, itemLabel, part, mapping, depth, parentSelectedParam) {
        depth               = depth     || 0;
        mapping             = mapping   || null;
        parentSelectedParam = parentSelectedParam || null;
 
        nodeCounter++;
        var currentNodeId  = nodeCounter;
        var selectedParam  = mapping ? mapping.api_table_field : "";
        var selectedAction = mapping ? mapping.type            : "";
        var selectedRank   = mapping ? mapping.rank_id         : null;
 
        var options     = filterOptions(parentSelectedParam);
        var optionsHtml = options.map(function (o) {
            return `<option value="${o}">${o}</option>`;
        }).join('');
 
        var html = `
<div class="node-level mb-2" data-node-id="${currentNodeId}" data-parent-id="${parentNodeId || ''}" data-depth="${depth}" style="margin-left:${depth * 20}px;">
    <div class="row align-items-center">
        <label class="col-sm-2 col-form-label text-truncate">${itemLabel}</label>
        <div class="col-sm-3">
            <select class="form-select param-select" name="levels[${currentNodeId}][param]">
                <option value="">${STR_CHOOSE_PLACEHOLDER}</option>
                ${optionsHtml}
            </select>
        </div>
        <div class="col-sm-3">
            <select class="form-select action-select" name="levels[${currentNodeId}][action]">
                <option value="">${STR_ACTION_PLACEHOLDER}</option>
                {{-- node/write/pick are Fields_mapping.type's stored values, not display
                     labels — like the field-type <option>s above, not localized on purpose. --}}
                <option value="node">node</option>
                <option value="write">write</option>
                <option value="pick">pick</option>
            </select>
        </div>
        <div class="col-sm-3 rank-placeholder" id="rank-placeholder-${currentNodeId}"></div>
        <input type="hidden" name="levels[${currentNodeId}][depth]"        value="${depth}">
        <input type="hidden" name="levels[${currentNodeId}][part]"         value="${part}">
        <input type="hidden" name="levels[${currentNodeId}][parentNodeId]" value="${parentNodeId || ''}">
    </div>
</div>`;
 
        var $newNode = $j(html);
        if (parentNodeId) {
            $container.find(`[data-node-id="${parentNodeId}"]`).after($newNode);
        } else {
            $container.append($newNode);
        }
 
$newNode.find(".param-select").val(selectedParam);
$newNode.find(".action-select").val(selectedAction);

if (selectedParam) {
    loadRanks(selectedParam.split('.')[0], currentNodeId, selectedRank);
    if (selectedAction === 'write') {
        checkDateField(selectedParam, currentNodeId);
    }
}
 
$newNode.find(".param-select").on("change", function () {
    var newVal = $j(this).val();
    if (newVal) loadRanks(newVal.split('.')[0], currentNodeId);
    checkDateField(newVal, currentNodeId);
    rebuildRequiredDefaults();
});
 
        $newNode.find(".action-select").on("change", function () {
            var val          = $j(this).val();
            var currentDepth = $newNode.data("depth");
            var currentParam = $newNode.find(".param-select").val();
            if (val === 'write') {
                checkDateField(currentParam, currentNodeId);
            } else {
                $j('#date-notice-' + currentNodeId).remove();
            }
 
            $newNode.nextAll().each(function () {
                if ($j(this).data("depth") > currentDepth) $j(this).remove();
                else return false;
            });
 
            if (val === "node" && currentParam !== "") {
                addLevel(currentNodeId, itemLabel, part, null, currentDepth + 1, currentParam);
            }
 
            rebuildRequiredDefaults();
        });
 
        if (mapping) {
            existingMappings.forEach(function (child) {
                if (child.parent === mapping.id) {
                    addLevel(currentNodeId, child.api_table_field, child.part, child, depth + 1, selectedParam);
                }
            });
        }
 
        return currentNodeId;
    }
 
    // Builds the node structure based on current concatenate state
    function buildNodes() {
        $container.empty();
        $j('#required-defaults-global').empty();
        nodeCounter = 0;
 
        var isConcatChecked = $j('#concatenate_parts').is(':checked');
 
        if (isConcatChecked) {
            // Single node for all parts — part = '__concat__'
            var concatMapping = existingMappings.find(function (m) {
                return m.part == 0 && !m.parent;
            }) || null;
            addLevel(null, "All parts (concatenated with '" + explode_char + "')", 0, concatMapping, 0, null);
        } else {
            mapping_parts.forEach(function (item, index) {
                var mapping = existingMappings.find(function (m) {
                    return m.part == index && !m.parent;
                }) || null;
                addLevel(null, item, index, mapping, 0, null);
            });
        }
 
        rebuildRequiredDefaults(currentSavedDefaults);
    }
 
    // Toggle between concatenated and per-part when checkbox changes
    $j('#concatenate_parts').off('change').on('change', function () {
        buildNodes();
    });
 
    // Initial build
    buildNodes();
});

 
    // ── Field Modal ──────────────────────────────────────────
    $j('#field_Modal').on('show.bs.modal', function (event) {
        var button = $j(event.relatedTarget);
        var modal  = $j(this);
 
        modal.find('#name').val(button.data('name'));
        // a type the list does not offer (e.g. a tree field) is added, so editing the field cannot lose it
        var storedType = button.data('type');
        if (storedType && !modal.find('#type option').filter(function () { return this.value === storedType; }).length) {
            modal.find('#type').append($j('<option>').text(storedType));
        }
        modal.find('#type').val(storedType || 'text');
        modal.find('#explode_fields').val(button.data('explode_fields'));
        modal.find('#explode_char').val(button.data('explode_char'));
        modal.find('#na_defaults').val(button.data('na_defaults') || '');
        modal.find('#explode_save_direction').val(button.data('explode_save_direction') || 'direct');
        // a field saved without a choice has the autocomplete off; a new one starts with it on
        modal.find('#autocomplete').val(button.data('field_id') ? (button.data('autocomplete') || '0') : '1');
        modal.find('[name=option_values]').val([button.data('option_values')]);
        modal.find('#required').val(button.data('required') ?? '1');
        modal.find('#deducted').val(button.data('deducted') ?? '1');
        modal.find('#have_doubts').val(button.data('have_doubts') ?? '1');
        modal.find('#multiple').val(button.data('multiple') ?? '0');
        modal.find('#min_user_level').val(button.data('min_user_level') ?? '0');
        modal.find('#field_id').val(button.data('field_id'));
 
        <?php foreach ($languages as $language) { ?>
            var fields_name_<?php echo $language->iso2; ?> = button.data('fields_name_{{$language->iso2}}');
            modal.find('#fields_name_{{$language->iso2}}').val(fields_name_<?php echo $language->iso2; ?>);
            CKEDITOR.instances.fields_name_<?php echo $language->iso2; ?>.setData(fields_name_<?php echo $language->iso2; ?>);
 
            var fields_question_<?php echo $language->iso2; ?> = button.data('fields_question_{{$language->iso2}}');
            modal.find('#fields_question_{{$language->iso2}}').val(fields_question_<?php echo $language->iso2; ?>);
            CKEDITOR.instances.fields_question_<?php echo $language->iso2; ?>.setData(fields_question_<?php echo $language->iso2; ?>);
 
            var fields_text_<?php echo $language->iso2; ?> = button.data('fields_text_{{$language->iso2}}');
            modal.find('#fields_text_{{$language->iso2}}').val(fields_text_<?php echo $language->iso2; ?>);
            CKEDITOR.instances.fields_text_<?php echo $language->iso2; ?>.setData(fields_text_<?php echo $language->iso2; ?>);
 
            var fields_helper_<?php echo $language->iso2; ?> = button.data('fields_helper_{{$language->iso2}}');
            modal.find('#fields_helper_{{$language->iso2}}').val(fields_helper_<?php echo $language->iso2; ?>);
            CKEDITOR.instances.fields_helper_<?php echo $language->iso2; ?>.setData(fields_helper_<?php echo $language->iso2; ?>);
        <?php } ?>
 
        // the options of a list field (jQuery reads the JSON of the data attribute itself)
        var storedChoices = button.data('choices');
        renderChoices(Array.isArray(storedChoices) ? storedChoices : []);
        // read as text: jQuery would turn a separator such as "1" into a number
        modal.find('#choices_separator').val(String(button.attr('data-choices_separator') || ''));
        $j('#choice_import_panel').addClass('d-none');

        applyType();
    });
 
    // ── The table of options (select / radio / checkboxes) ────
    var CHOICE_LANGS = {!! json_encode(collect($languages)->pluck('iso2')->values()) !!};
    var CHOICE_TEXTS = {
        duplicate: {!! json_encode(get_dialogs_simple('Two options have the same value:', $active_language)) !!},
        replace: {!! json_encode(get_dialogs_simple('This replaces the current options with the imported lines. Continue?', $active_language)) !!},
        saveFirst: {!! json_encode(get_dialogs_simple('Save the field first: the values are taken from what has been submitted for it.', $active_language)) !!},
        nothing: {!! json_encode(get_dialogs_simple('Nothing has been submitted for this field yet.', $active_language)) !!},
        added: {!! json_encode(get_dialogs_simple('added', $active_language)) !!},
        options: {!! json_encode(get_dialogs_simple('options', $active_language)) !!},
        separatorInOption: {!! json_encode(get_dialogs_simple('The separator {sep} is part of an option, so a joined value could not be split back:', $active_language)) !!},
        tooMany: {!! json_encode(get_dialogs_simple('The suggestions have {n} lines, too many to bring in automatically. Use Import from text if you really want them as options.', $active_language)) !!},
        slow: {!! json_encode(get_dialogs_simple('That is {n} lines and it may be slow. Continue?', $active_language)) !!}
    };
    // a longer list than this is never brought in on its own (a field of suggestions can have tens of thousands of lines)
    var AUTO_IMPORT_MAX = 500;
    var IMPORT_CONFIRM_ABOVE = 2000;

    function updateChoicesCount(extra) {
        $j('#choices_count').text($j('#choices_body tr').length + ' ' + CHOICE_TEXTS.options + (extra ? ' - ' + extra : ''));
    }

    function buildChoiceRow(choice) {
        var row = $j('<tr class="choice-row"></tr>');
        row.append('<td class="choice-handle text-muted" style="cursor: grab"><i class="bi bi-grip-vertical"></i></td>');

        var value = $j('<input type="text" class="form-control form-control-sm choice-value">').val(choice ? choice.value : '');
        row.append($j('<td></td>').append(value));

        CHOICE_LANGS.forEach(function (iso) {
            var label = (choice && choice.labels && choice.labels[iso]) ? choice.labels[iso] : '';
            // empty = the value is shown, so the placeholder says so
            var input = $j('<input type="text" class="form-control form-control-sm choice-label">')
                .attr('data-lang', iso).attr('placeholder', choice ? choice.value : '').val(label);
            row.append($j('<td></td>').append(input));
        });

        row.append('<td><button type="button" class="btn btn-link text-danger p-0 choice-remove"><i class="bi bi-trash"></i></button></td>');

        return row;
    }

    function addChoiceRow(choice) {
        var row = buildChoiceRow(choice);
        $j('#choices_body').append(row);
        updateChoicesCount();

        return row;
    }

    // all the rows at once: a long list is built outside the page and put in with one append
    function renderChoices(list) {
        var fragment = $j(document.createDocumentFragment());
        list.forEach(function (choice) { fragment.append(buildChoiceRow(choice)); });
        $j('#choices_body').empty().append(fragment);
        updateChoicesCount();
    }
    function gatherChoices() {
        var list = [];
        $j('#choices_body tr').each(function () {
            var value = $j.trim($j(this).find('.choice-value').val());
            if (value === '') { return; }
            var labels = {};
            $j(this).find('.choice-label').each(function () {
                var text = $j.trim($j(this).val());
                if (text !== '') { labels[$j(this).attr('data-lang')] = text; }
            });
            list.push({value: value, labels: labels});
        });
        return list;
    }

    // one option per line; ";" separates the value from the label in each language, in the order of the columns.
    // What is imported replaces the table, but an option that stays keeps the labels the line does not give.
    function importLines(text) {
        var before = {};
        gatherChoices().forEach(function (c) { before[c.value] = c.labels; });

        var list = [];
        var seen = {};
        String(text).split(/\r?\n/).forEach(function (line) {
            var parts = line.split(';').map(function (p) { return $j.trim(p); });
            var value = parts[0];
            if (!value || seen[value]) { return; }
            seen[value] = true;

            var labels = {};
            CHOICE_LANGS.forEach(function (iso, i) {
                var given = parts[i + 1];
                if (given) { labels[iso] = given; } else if (before[value] && before[value][iso]) { labels[iso] = before[value][iso]; }
            });
            list.push({value: value, labels: labels});
        });
        renderChoices(list);
    }

    $j('#choice_add').on('click', function () { addChoiceRow(null).find('.choice-value').focus(); });
    $j(document).on('click', '.choice-remove', function () { $j(this).closest('tr').remove(); updateChoicesCount(); });
    // the placeholder of a row's labels follows its value
    $j(document).on('input', '.choice-value', function () { $j(this).closest('tr').find('.choice-label').attr('placeholder', $j(this).val()); });

    $j('#choice_import_toggle').on('click', function () {
        $j('#choice_import_panel').toggleClass('d-none');
        $j('#choice_import_text').val('').focus();
    });
    $j('#choice_import_close').on('click', function () { $j('#choice_import_panel').addClass('d-none'); });
    $j('#choice_import_do').on('click', function () {
        var text = $j('#choice_import_text').val();
        if ($j.trim(text) === '') { return; }
        var lines = text.split(/\r?\n/).length;
        if (lines > IMPORT_CONFIRM_ABOVE && !confirm(CHOICE_TEXTS.slow.replace('{n}', lines))) { return; }
        if ($j('#choices_body tr').length && !confirm(CHOICE_TEXTS.replace)) { return; }
        importLines(text);
        $j('#choice_import_panel').addClass('d-none');
    });

    // add what people have already submitted or validated for this field (only the values the table lacks)
    $j('#choice_fill').on('click', function () {
        var id = $j('#field_id').val();
        if (!id) { alert(CHOICE_TEXTS.saveFirst); return; }

        $j.getJSON(APP_URL + '/field_submitted_values/' + id).done(function (data) {
            var have = {};
            gatherChoices().forEach(function (c) { have[c.value] = true; });
            var added = 0;
            (data.values || []).forEach(function (value) {
                if (!have[value]) { addChoiceRow({value: value, labels: {}}); added++; }
            });
            updateChoicesCount(added ? added + ' ' + CHOICE_TEXTS.added : CHOICE_TEXTS.nothing);
        });
    });

    $j('#choices_body').sortable({
        handle: '.choice-handle',
        axis: 'y',
        helper: function (event, row) {
            var cells = row.children();
            var clone = row.clone();
            clone.children().each(function (i) { $j(this).width(cells.eq(i).width()); });
            return clone;
        }
    });

    // on saving: the table goes as one JSON field; two options with the same value are refused
    $j('#field_edit').on('submit', function (event) {
        if ($j('#choices_group').hasClass('d-none')) { return; }

        var list = gatherChoices();
        var seen = {};
        for (var i = 0; i < list.length; i++) {
            if (seen[list[i].value]) {
                event.preventDefault();
                alert(CHOICE_TEXTS.duplicate + ' ' + list[i].value);
                return;
            }
            seen[list[i].value] = true;
        }

        var separator = $j('#choices_separator').prop('disabled') ? '' : $j.trim($j('#choices_separator').val());
        if (separator !== '') {
            for (var j = 0; j < list.length; j++) {
                if (list[j].value.indexOf(separator) !== -1) {
                    event.preventDefault();
                    alert(CHOICE_TEXTS.separatorInOption.replace('{sep}', separator) + ' ' + list[j].value);
                    return;
                }
            }
        }
        $j('#choices_json').val(JSON.stringify(list));
    });

    // ── What applies to each type ─────────────────────────────
    // Groups marked data-types="..." show only for those types (they are not sent otherwise; the server
    // clears them for the types that do not use them). The list of options is shared:
    //   select / radio / checkboxes (and any other, e.g. tree): the choices, always shown
    //   text / textarea: suggestions for the autocomplete, shown while the autocomplete is on
    //   date: none
    function applyType() {
        var type = $j('#type').val();

        $j('#field_Modal [data-types]').each(function () {
            var applies = String($j(this).data('types')).split(' ').indexOf(type) !== -1;
            $j(this).toggleClass('d-none', !applies);
            $j(this).find('input,select,textarea').prop('disabled', !applies);
        });

        var suggests = type === 'text' || type === 'textarea';
        var isChoice = ['select', 'radio', 'checkboxes'].indexOf(type) !== -1;

        // a list field has the table of options; the plain box is for the suggestions of text/textarea (and a tree)
        $j('#choices_group').toggleClass('d-none', !isChoice);
        $j('#choices_json').prop('disabled', !isChoice);
        // a text field turned into a list: what it suggested becomes its first options
        if (isChoice && $j('#choices_body tr').length === 0 && $j.trim($j('#option_values').val()) !== '') {
            var suggestionLines = $j('#option_values').val().split(/\r?\n/).filter(function (l) { return $j.trim(l) !== ''; }).length;
            if (suggestionLines <= AUTO_IMPORT_MAX) {
                importLines($j('#option_values').val());
            } else {
                updateChoicesCount(CHOICE_TEXTS.tooMany.replace('{n}', suggestionLines));
            }
        }

        var showOptions = type !== 'date' && !isChoice && (!suggests || $j('#autocomplete').val() === '1');
        var group = $j('#options_group');
        group.toggleClass('d-none', !showOptions);
        // a date field has no list, and a list field takes its options from the table (the server mirrors them); for
        // text/textarea with the autocomplete off the box is only hidden, so the list is kept
        group.find('textarea').prop('disabled', type === 'date' || isChoice);
        group.find('.options-label-choice').toggleClass('d-none', suggests);
        group.find('.options-label-suggest').toggleClass('d-none', !suggests);
    }

    $j('#type, #autocomplete').on('change', applyType);

    $j(document).on('click', '.delete_field', function () {
        if (!confirm(STR_CONFIRM_DELETE_FIELD)) return;
        var field_id = $j(this).attr('id');
        $j.post(APP_URL + '/field_delete', {
            field_id: field_id,
            _token: '{{ csrf_token() }}'
        }).done(function () {
            location.reload();
        }).fail(function () {
            alert(STR_ERROR_DELETING_FIELD);
        });
    });
});
</script>

<style>
    /* while the list is shown in another order (A-Z), dragging is off: the handles say so */
    #fields-table.order-locked tbody tr td:first-child { opacity: .25; cursor: not-allowed !important; }
</style>

<script type="text/javascript">
// Drag a row by its handle to change the order of the fields (the order of the forms). It is saved at
// once. It is switched off while the list is shown in another order, e.g. A-Z (see table_tools).
(function () {
    var $body = jQuery('#fields-table tbody.sort');
    var table = document.getElementById('fields-table');
    var $flash = jQuery('#flash-message');
    var texts = {
        saving: {!! json_encode(get_dialogs_simple('processing', $active_language)) !!},
        saved: {!! json_encode(get_dialogs_simple('Order saved', $active_language)) !!},
        failed: {!! json_encode(get_dialogs_simple('The order could not be saved', $active_language)) !!}
    };

    function flash(kind, text) {
        $flash.removeClass('d-none hidden alert-warning alert-success alert-danger').addClass('alert-' + kind).text(text);
    }

    $body.sortable({
        items: 'tr[id^="item-"]',
        handle: 'td:first-child',
        axis: 'y',
        cursor: 'grabbing',
        // keep the cells as wide as they were while the row is carried
        helper: function (event, row) {
            var cells = row.children();
            var clone = row.clone();
            clone.children().each(function (i) { jQuery(this).width(cells.eq(i).width()); });
            return clone;
        },
        update: function () {
            flash('warning', texts.saving);
            jQuery.ajax({
                type: 'POST',
                url: {!! json_encode(url('fields_sort')) !!},
                data: {_token: {!! json_encode(csrf_token()) !!}, id: $body.sortable('serialize')}
            }).done(function () {
                flash('success', texts.saved);
                setTimeout(function () { $flash.addClass('d-none'); }, 2000);
            }).fail(function () {
                flash('danger', texts.failed);
            });
        }
    });

    table.addEventListener('tabletools:order', function (event) {
        var locked = !event.detail.original;
        $body.sortable('option', 'disabled', locked);
        table.classList.toggle('order-locked', locked);
    });
})();
</script>

@endsection