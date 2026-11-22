<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Specimen;
use App\Models\Collaborative_fields;
use App\Models\Autocompletefields;
use App\Models\Validate_fields;
use App\Models\Specify_fields;
use App\Models\User;
use App\Models\Project;
use App\Models\User_categories;
use App\Models\Comments;
use App\Models\Fields;

use Illuminate\Support\Facades\Mail;

use DB;
use Auth;

class SpecimensController extends Controller
{
	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public

	function __construct()
	{
		//$this->middleware('auth', ['except' => ['index', 'show', 'store', 'autocomplete']]);

	}

	/**
	 * Display the specified resource.
	 *
	 * @paramint$id
	 * @return \Illuminate\Http\Response
	 */
	public

	function show($project_id, $specimen_id, $fieldtosubmit = null)
	{

		// A specimen_id can belong to more than one project (one row per project), so
		// resolve the row for the project actually requested in the URL rather than
		// an arbitrary one.
		$specimen = Specimen::where('specimen_id', '=', $specimen_id)
			->where('project_id', '=', $project_id)
			->first();

		if (!$specimen) {
			// not (or no longer) linked to that project — fall back to any row for
			// this specimen_id, and if a real project was requested, send the user
			// to the "no project" view instead of showing the wrong project's fields.
			$specimen = Specimen::where('specimen_id', '=', $specimen_id)->first();

			if (!$specimen) {
				return redirect('/projects')->with('error', 'specimen_not_found');
			}

			if ($project_id != 0) {
				return redirect('/projects/0/' . $specimen_id . '/' . $fieldtosubmit)->with('error', 'No project selected');
			}
		}

		$project_id = $specimen->project_id;
		$project = $project_id != 0 ? Project::find($project_id) : null;

		if (!$project) {
			// no project, or the project this specimen belonged to has since been
			// deleted — projects can be deleted while specimens keep the reference,
			// so degrade to the "no project" pseudo-project instead of crashing.
			$project = new Project;
			$project->id = 0;
		}

		//determine if the user is authenticated or not
		if (Auth::check()) {
			$user = auth()->user();
			$user_id = $user->id;
			$user_points = $user->user_categories->points;

			//			$points = auth()->user()->User_categories->points;
			//			$user_category = auth()->user()->category;
		} else {
			$user_id = session()->getId();
			$user_points = User_categories::min('points');
		}

		$field_view = field_view_for($project);

		//fields of this project the user may work on (within their level) — with and without their bans
		$allowed_ids = project_fields_for_user($project, $user_id);          // level + not banned
		$level_ok_ids = project_fields_for_user($project, $user_id, true);   // level only
		$user_banned = Auth::check() ? array_filter(explode('|', auth()->user()->banned_fields ?? '')) : array();

		//in "one field at a time" mode, a bare specimen URL (or a request for a
		//field the user does not work on here) is resolved to the first field of
		//this specimen still outstanding for the user
		$requested_id = $fieldtosubmit ? Fields::where('name', '=', $fieldtosubmit)->value('id') : null;
		if ($field_view === 'single' && $project->id != 0 && (!$requested_id || !in_array($requested_id, $allowed_ids))) {
			$remaining = outstanding_fields_for($specimen_id, $project, $user_id);
			if (!empty($remaining)) {
				$first_field = Fields::where('id', '=', $remaining[0])->value('name');
				return redirect('/projects/' . $project_id . '/' . $specimen_id . '/' . $first_field);
			}
			//this specimen is done for the user — move on to the next one
			return redirect('/projects/' . $project_id . '/start');
		}

		//find the field requested. It may be one field, or none (for the full form)
		$field_id = Fields::where('name', '=', $fieldtosubmit)->value('id');
		//store this specimen_id on session to not display it again if skiped by the user
		if ($field_id == '') {
			$field_id = '%';
		}
		session()->push('visited_specimen', $specimen_id . '*' . $field_id);

		//fields to render as forms: project fields within the user's level, minus banned ones
		$all_project_fields = $project->fields()->orderBy('parent')->orderBy('position')->get();
		$fields = $all_project_fields->filter(function ($f) use ($allowed_ids) {
			return in_array($f->id, $allowed_ids);
		})->values();

		//banned fields of this project (within level) — shown as stubs the user can restore
		$banned_fields = $all_project_fields->filter(function ($f) use ($level_ok_ids, $user_banned) {
			return in_array($f->id, $level_ok_ids) && in_array($f->id, $user_banned);
		})->values();

		//in "one field at a time" mode, render only the requested field
		if ($field_view === 'single' && $fieldtosubmit) {
			$fields = $fields->where('name', $fieldtosubmit)->values();
			$banned_fields = collect();
		}

		//get the values submitted by this user to the fields displayed in this form, in order to show the submitted values instead of blank fields
		$Collaborative_fields = Collaborative_fields::where('user_id', '=', $user_id)->where('specimen_id', '=', $specimen_id)->where('field_id', 'LIKE', $field_id)->where('status', '>', 0)->orderby('position')->get()->toarray();
		//the view reads valid_value on every row; only rows with a validated counterpart get one below
		$Collaborative_fields = array_map(fn($row) => $row + ['valid_value' => ''], $Collaborative_fields);

		//for the fields displayed in form, get all the values already valid (either the ones valideted within the platform, or the ones comming from the herbarium database)
		$Valid_fields = Validate_fields::where([
			['status', '=', 1],
			['specimen_id', '=', $specimen_id],
			['field_id', 'LIKE', $field_id]
		])->orderby('position')->orderby('id')->get()->toarray();

		$specify_fields = Specify_fields::where([
			['status', '>', 0],
			['specimen_id', '=', $specimen_id],
			['field_id', 'LIKE', $field_id],
			['value', '!=', ''],
			['value', '!=', '_'],
		])->orderby('position')->get()->toarray();

		$Valid_fields = array_merge($specify_fields, $Valid_fields);

		//foreach valid field, chek if the user has submitted a different value.
		foreach ($Valid_fields as $valid_field) {
			/*
			echo $valid_field['value'];
			echo $valid_field['field_id'];	
			echo $valid_field['position'];
			*/

			$keys_field = array_keys(array_column($Collaborative_fields, 'field_id'), $valid_field['field_id']);


			$valid_field_registado = 0;

			foreach ($keys_field as $key) {

				$position = $Collaborative_fields[$key]['position'];
				if ($position == $valid_field['position']) {
					$Collaborative_fields[$key]['valid_value'] = $valid_field['value'];
					$valid_field_registado = 1;
				}
			}


			if ($valid_field_registado == 0) {
				$Collaborative_fields[] = array('field_id' => $valid_field['field_id'], 'value' => '', 'doubt' => '0', 'deducted' => '0', 'position' => $valid_field['position'], 'valid_value' => $valid_field['value']);
			}
		}



		$images = explode('|', $specimen->images);

		foreach ($images as $image) {
			$array_image[] = $image;
		}

		$comments = Comments::where('specimen_id', '=', $specimen_id)->where('field_id', 'LIKE', $field_id)->where('status', '=', 1)->orderby('id')->get();

		//what the other users submitted (still pending) on these fields, to pick one to use: shown to the
		// audience the project chose (with no real project, only root/admin)
		$may_see_others = Auth::check() && ($project->id != 0
			? $project->showsOthersPanelTo(auth()->user())
			: auth()->user()->isAdmin());
		$others = $may_see_others
			? $this->pendingSubmissionsByOthers($specimen_id, $project->id, $user_id, $field_id)
			: [];

		$can_submit = Auth::check() || \App\Models\Setting::current()->anonymousAccess() === 'submit';

		return view('specimens.show')->with('can_submit', $can_submit)->with('others', $others)->with('specimen', $specimen)->with('Collaborative_fields', $Collaborative_fields)->with('fields', $fields)->with('fieldtosubmit', $fieldtosubmit)->with('project', $project)->with('images', $images)->with('comments', $comments)->with('field_view', $field_view)->with('banned_fields', $banned_fields);
	}

	/**
	 * Pending submissions by users other than $user_id for this specimen, per field, grouped into
	 * distinct sets: users who submitted exactly the same lines (position, value, doubt, deducted)
	 * share one set, with their points added up. Highest points first.
	 *
	 * @return array<int, array<int, array{users: string[], points: int, lines: array}>>
	 */
	private function pendingSubmissionsByOthers($specimen_id, $project_id, $user_id, $field_id): array
	{
		$project = $project_id != 0 ? Project::find($project_id) : null;

		$rows = Collaborative_fields::with('user')
			->where('specimen_id', $specimen_id)
			->where('field_id', 'LIKE', $field_id)
			->where('status', 1)
			->where('user_id', '!=', $user_id)
			->when($project_id != 0, fn($q) => $q->where('project_id', $project_id))
			->orderBy('position')
			->get();

		$perUser = [];
		foreach ($rows as $row) {
			$perUser[$row->field_id][$row->user_id]['name'] = $row->user ? display_name($row->user, 'auto', $project) : 'guest';
			$perUser[$row->field_id][$row->user_id]['avatar'] = $row->user ? display_avatar($row->user, 20, 'auto', $project)->toHtml() : '';
			$perUser[$row->field_id][$row->user_id]['points'] = $perUser[$row->field_id][$row->user_id]['points'] ?? (int) $row->points;
			$perUser[$row->field_id][$row->user_id]['lines'][] = [
				'position' => (int) $row->position,
				'value' => (string) $row->value,
				'doubt' => (int) $row->doubt,
				'deducted' => (int) $row->deducted,
			];
		}

		$out = [];
		foreach ($perUser as $fieldId => $users) {
			$sets = [];
			foreach ($users as $u) {
				$key = json_encode($u['lines']);
				$sets[$key]['lines'] = $u['lines'];
				$sets[$key]['users'][] = ['name' => $u['name'], 'avatar' => $u['avatar'] ?? '', 'points' => $u['points']];
				$sets[$key]['points'] = ($sets[$key]['points'] ?? 0) + $u['points'];   // only to order the sets
			}
			foreach ($sets as &$set) {
				usort($set['users'], fn($a, $b) => $b['points'] <=> $a['points']);
			}
			unset($set);
			usort($sets, fn($a, $b) => $b['points'] <=> $a['points']);
			$out[$fieldId] = array_values($sets);
		}

		return $out;
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public

	function create() {}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param\Illuminate\Http\Request$request
	 * @return \Illuminate\Http\Response
	 */
	public

	function store(Request $request)
	{
		$this->validate($request, [
			'field' => 'required',
		]);

		//Assign the points that should be given to this submission based on user's profile
		if (Auth::check()) {
			$user_id = auth()->user()->id;
			$points = auth()->user()->user_categories->points;
			$user_category = auth()->user()->category;
		} else {
			$user_id = session()->getId();
			$points = User_categories::min('points');   // an anonymous visitor counts with the lowest level's points
		}

		$specimen_id = $request->specimen_id;

		$project_id = $request->input('project_id');

		$different_values_message = 0;

		$different_values = array();
		$equal_values = array();

		// Server time when the form was rendered, encrypted so it can't be tampered with.
		$displayed_at = null;
		try {
			$displayed_at = \Illuminate\Support\Facades\Crypt::decryptString((string) $request->input('displayed_at'));
		} catch (\Throwable $e) {
		}

		foreach ($request->input('field') as $field_id => $field_value) {
			$field = Fields::find($field_id);
			$fields_position_submited = array();
			$position = 0;
			foreach ($field_value as $key => $value) {
				$position = $position + 1;
				$fields_position_submited[] = $position;

				if ($value == 'keepfield') {
					$Collaborative_field = new Collaborative_fields;
				} else {
					if (is_array($value) && $field->joinsChoices()) {
						// the ticked options of one line, as one value (a checkboxes field with a separator)
						$concatenated_value = $field->joinChoices($value);
					} elseif (is_array($value)) {
						if ($field->explode_save_direction == 'reverse') {
							$value = array_reverse($value);
						}
						$concatenated_value = '';
						foreach ($value as $individual_value) {
							if ($individual_value == '') {
								//$individual_value = 'n.a.';
							}
							$concatenated_value .= $individual_value . $field->explode_char;
						}
						$concatenated_value = trim($concatenated_value, $field->explode_char);
					} else {
						$concatenated_value = $value;
					}
					if ($concatenated_value == null) {
						$concatenated_value = '';
					}

					//doubt/deducted are posted keyed by the same position index as the field's own
					//value (see show.blade.php), so each line's flags line up with that line.
					$doubt = $request->input("doubt.$field_id.$key", 0);
					$deducted = $request->input("deducted.$field_id.$key", 0);

					//set status to 0 for any previous submission by this user to the same field
					Collaborative_fields::where([
						['user_id', 'LIKE', $user_id],
						['specimen_id', '=', $specimen_id],
						['field_id', '=', $field_id],
						['position', '=', $position]
					])->update(['status' => 0]);

					$Collaborative_field = new Collaborative_fields;
					$Collaborative_field->value = $concatenated_value;
					$Collaborative_field->position = $position;
					$Collaborative_field->specimen_id = $specimen_id;
					$Collaborative_field->field_id = $field_id;
					$Collaborative_field->user_id = $user_id;
					$Collaborative_field->doubt = $doubt;
					$Collaborative_field->deducted = $deducted;
					$Collaborative_field->points = $points;
					//project_id is known here (the submitted form's own project) — persisting
					//it removes the need for anything downstream to guess it from specimen_id,
					//which is ambiguous once a specimen_id can mean different things per project.
					$Collaborative_field->project_id = $project_id;
					$Collaborative_field->displayed_at = $displayed_at;
					$Collaborative_field->save();

					$fields_existent = Collaborative_fields::where('user_id', '!=', $user_id)->where('specimen_id', '=', $specimen_id)->where('field_id', '=', $field_id)->where('position', '=', $position)->where('status', '=', 1)->pluck('value')->toarray();

					$concatenated_value_to_compare = $concatenated_value;
					$new_array = array_count_values($fields_existent);
					foreach ($new_array as $field_existent => $count) {
						//$field_existent_to_compare = strtolower(trim($field_existent, '.'));
						$field_existent_to_compare = $field_existent;
						if ($field_existent_to_compare == $concatenated_value_to_compare) {
							$equal_values[$field_id][$position] = $count;
						} else {
							$submitted_values[$field_id][$position] = $concatenated_value;
							$different_values[$field_id][$position][$field_existent] = $count;
							$different_values_message = $different_values_message + 1;
						}
					}
				}
			}

			//altera para 0 o status dos registos que foram apagados pelo utilizador
			Collaborative_fields::where([
				['user_id', 'LIKE', $user_id],
				['specimen_id', '=', $specimen_id],
				['field_id', '=', $field_id]
			])->whereNotIn('position', $fields_position_submited)->update(['status' => 0]);
		}

		$this_specimen_id = $specimen_id;

		//autovalidate pendig submitted contributions
		app(ValidationController::class)->autovalidate_this($this_specimen_id);
		//update project stats
		app(ProjectsController::class)->update_project_stats(app(\App\Services\ProjectStats::class)->projectsOfSpecimen($specimen_id));
		//update user counts
		app(UsersController::class)->update_user_counts($user_id);

		//pick where to send the user next.
		// single mode: finish the outstanding fields of the current specimen first
		// (in field order), then move on; all mode: straight to the next specimen.
		$project = Project::find($project_id);
		$specimen_id = '';
		if (field_view_for($project) === 'single' && !empty(outstanding_fields_for($this_specimen_id, $project, $user_id))) {
			$specimen_id = $this_specimen_id;
		} else {
			$pick = $this->next_pick($project_id, $user_id);
			$specimen_id = $pick['specimen_id'] ?? '';
		}
		//land the user on the correct field via show() (bare specimen URL)
		$fieldtosubmit = '';

		if ($different_values_message > 0) {
			$next_form = URL('/projects/' . $project_id . '/' . $specimen_id . '/' . $fieldtosubmit);

			return redirect()->back()->with('equal_values', $equal_values)->with('different_values', $different_values)->with('next_form', $next_form)->with('success', 'successfully submitted')->with('submitted_values', $submitted_values);
		} else {

			if ($specimen_id != '') {
				return redirect('/projects/' . $project_id . '/' . $specimen_id . '/' . $fieldtosubmit)->with('success', 'successfully submitted');
			} else {
				return redirect('/projects')->with('warning', 'no_specimen');
			}
		}
	}


	/**
	 * Called from the "different values" page: a new submission, in the user's name, of one of the
	 * values other users submitted for that line - the same as going back to the form and
	 * resubmitting it (the previous submission for the line is superseded, then the usual
	 * validation, stats and counts follow). Same for every user.
	 */
	public function choose_value(Request $request)
	{
		$data = $request->validate([
			'specimen_id' => 'required',
			'project_id' => 'nullable|integer',
			'field_id' => 'required|integer',
			'position' => 'required|integer',
			'value' => 'present|string|nullable',
		]);
		$value = (string) ($data['value'] ?? '');

		if (Auth::check()) {
			$user_id = auth()->user()->id;
			$points = auth()->user()->user_categories->points;
		} else {
			$user_id = session()->getId();
			$points = User_categories::min('points');
		}

		$own = Collaborative_fields::where('user_id', 'LIKE', $user_id)
			->where('specimen_id', $data['specimen_id'])
			->where('field_id', $data['field_id'])
			->where('position', $data['position'])
			->where('status', '>', 0)
			->orderByDesc('id')
			->first();
		if (!$own) {
			return response()->json(['ok' => false, 'message' => 'no_submission'], 422);
		}
		if ($own->value === $value) {
			return response()->json(['ok' => true]);
		}

		Collaborative_fields::where('user_id', 'LIKE', $user_id)
			->where('specimen_id', $data['specimen_id'])
			->where('field_id', $data['field_id'])
			->where('position', $data['position'])
			->update(['status' => 0]);

		$row = new Collaborative_fields;
		$row->value = $value;
		$row->position = $data['position'];
		$row->specimen_id = $data['specimen_id'];
		$row->field_id = $data['field_id'];
		$row->user_id = $user_id;
		$row->doubt = $own->doubt;
		$row->deducted = $own->deducted;
		$row->points = $points;
		$row->project_id = $own->project_id ?? ($data['project_id'] ?? null);
		$row->save();

		// same follow-up as a normal submission (autovalidate_this prints debug output: discard it)
		ob_start();
		try {
			app(ValidationController::class)->autovalidate_this($data['specimen_id']);
		} finally {
			ob_end_clean();
		}
		app(ProjectsController::class)->update_project_stats(app(\App\Services\ProjectStats::class)->projectsOfSpecimen($data['specimen_id']));
		app(UsersController::class)->update_user_counts($user_id);

		return response()->json(['ok' => true]);
	}

	public

	function autocomplete(Request $request)

	{
		//$data = Autocompletefields::select( "value" )->whereRaw( "MATCH(value)AGAINST('*{$request->input('query')}*' IN BOOLEAN MODE)" )->limit( 6 )->where( 'field_id', '=', $request->id )->get()->toArray();


		/*
				$data = Autocompletefields::select( "value as name" )->limit( 6 )->where( [
					[ "value", "LIKE", "%{$request->input('query')}%" ],
					[ "field_id", "=", "$request->id" ]
				] )->get()->toArray();
		*/
		if (Auth::check()) {
			$user_id = auth()->user()->id;
			$points = auth()->user()->user_categories->points;
			$user_category = auth()->user()->category;
		} else {
			$user_id = session()->getId();
			$user_category = 6;
		}

		$query = $request->input('query');

		$field = Fields::where('id', '=', $request->id)->first();

		$options = $field->option_values;
		//echo $options;
		$explode_char = $field->explode_char ?? '|';

		$options_array = explode(PHP_EOL, $options);
		//print_r($options_array);

		$validated_options_array = array();
		//$validated_options_array = Validate_fields::where('field_id', '=', $request->id)->where('status', '>=', 1)->where('status', '!=', 3)->where('value', 'LIKE', "%$query%")->whereNotIn('value', $options_array)->distinct()->pluck('value')->toArray();
		//print_r($validated_options_array);

		$user_options_array = Collaborative_fields::where('field_id', '=', $request->id)->where('user_id', '=', $user_id)->where('status', '!=', 0)->where('value', 'LIKE', "%$query%")->whereNotIn('value', $options_array)->whereNotIn('value', $validated_options_array)->distinct()->pluck('value')->toArray();

		$options_list = array_merge($options_array, $validated_options_array, $user_options_array);

		$data = array($options_list);

		//print_r($data);

		//$data = array(array('fátima sales; maria sales', 'sales, fátima'));
		//$flat = call_user_func_array('array_merge', $data);

		$finalarray = array();
		foreach ($data as $flat) {
			foreach ($flat as $str) {
				$str_parts = explode($explode_char, $str);
				foreach($str_parts as &$part)
				{
					$part = trim($part);
				}
				$str = implode(' '.$explode_char.' ', $str_parts);
				$str = trim($str);
				$str = strip_tags($str);
				if (!in_array($str, $finalarray)) {
					$finalarray[] = $str;
				}
			}
		}
		return $finalarray;
		//		return response()->json($result);
	}


	function autocomplete_comments(Request $request)

	{
		//$data = Autocompletefields::select( "value" )->whereRaw( "MATCH(value)AGAINST('*{$request->input('query')}*' IN BOOLEAN MODE)" )->limit( 6 )->where( 'field_id', '=', $request->id )->get()->toArray();


		$data = Comments::select("value as name")->limit(6)->where([
			["value", "LIKE", "%{$request->input('query')}%"]
		])->get()->toArray();


		//$data = array(array('fátima sales; maria sales', 'sales, fátima'));
		//$flat = call_user_func_array('array_merge', $data);

		$finalarray = array();
		foreach ($data as $flat) {
			foreach ($flat as $str) {
				$str = trim($str);
				$str = strip_tags($str);
				if (!in_array($str, $finalarray)) {
					$finalarray[] = $str;
				}
			}
		}
		return $finalarray;
		//		return response()->json($result);
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @paramint$id
	 * @return \Illuminate\Http\Response
	 */
	public

	function edit($id) {}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param\Illuminate\Http\Request$request
	 * @paramint$id
	 * @return \Illuminate\Http\Response
	 */
	public

	function update(Request $request, $id) {}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @paramint$id
	 * @return \Illuminate\Http\Response
	 */
	public

	function destroy($id) {}

	/*
	function to give the next specimen with fields to submit. 
	It must be a specimen that:
	- different than the current specimen
	- has blank fields that the user never submitted
	- priority to fields other users have submited and are awaiting for more contributions in order to validate
	*/

	public

	function goto_next_specimen(Request $request)
	{
		$project_id = $request->project_id;

		if (Auth::check()) {
			$user_id = auth()->user()->id;
			$points = auth()->user()->user_categories->points;
			$user_category = auth()->user()->category;
		} else {
			$user_id = session()->getId();
			$user_category = 6;
			$points = DB::table('user_categories')->where('id', '=', "$user_category")->value('points');
		}

		/*
		echo '<pre>';
		var_dump(session());
		echo '</pre>';
		*/

		$pick = $this->next_pick($project_id, $user_id);
		$specimen_id = $pick['specimen_id'] ?? '';

		if ($specimen_id == '') {
			return redirect('/projects')->with('warning', 'no_specimen');
		}

		return redirect('/projects/' . $project_id . '/' . $specimen_id);
	}


	/*
	 * Choose the next specimen the user should work on in a project.
	 *
	 * Works at (specimen, field) granularity and returns
	 * ['specimen_id' => ..., 'field_id' => ...] for the first outstanding
	 * combination, or [] when the user has nothing left to do in this project.
	 * Both the "one field at a time" and "all fields on one screen" workflows
	 * only use ['specimen_id']; the exact field to land on is resolved by
	 * show() (see outstanding_fields_for()).
	 *
	 * A combination is outstanding when the field belongs to the project, is
	 * within the user's level and not banned by them, and the pair is neither
	 * validated, nor imported from Specify, nor already submitted by the user.
	 * Combinations with pending contributions from other users come first so
	 * consensus is reached sooner.
	 */
	public function next_pick($project_id, $user_id)
	{
		if (Auth::check()) {
			$user_id = auth()->user()->id;
			$user_points = auth()->user()->visibilityPoints();
		} else {
			$user_id = session()->getId();
			$user_points = User_categories::min('points');
		}

		$project = Project::find($project_id);
		if (!$project) {
			return array();
		}

		//specimens of this project available at the user's level
		$project_specimens = Specimen::where('project_id', '=', $project_id)->whereHas('min_user_category', function ($query) use ($user_points) {
			$query->where('points', '<=', $user_points);
		})->orderBy('id', 'asc')->pluck('specimen_id')->toArray();
		if (empty($project_specimens)) {
			return array();
		}

		//fields of this project the user may work on (level / not banned), in field order
		$project_field_ids = project_fields_for_user($project, $user_id);
		if (empty($project_field_ids)) {
			return array();
		}

		//build the set of specimen*field combinations that are already resolved for this user:
		// - validated within the platform
		// - already provided by the Specify import
		// - already submitted by this user
		$done = array();
		foreach (
			Validate_fields::where('status', '=', 1)
				->whereIn('specimen_id', $project_specimens)
				->whereIn('field_id', $project_field_ids)
				->get(['specimen_id', 'field_id']) as $row
		) {
			$done[$row->specimen_id . '*' . $row->field_id] = true;
		}
		foreach (
			Specify_fields::where('status', '>', 0)
				->whereIn('specimen_id', $project_specimens)
				->whereIn('field_id', $project_field_ids)
				->where('value', '!=', '')
				->where('value', '!=', '_')
				->get(['specimen_id', 'field_id']) as $row
		) {
			$done[$row->specimen_id . '*' . $row->field_id] = true;
		}
		foreach (
			Collaborative_fields::where('user_id', '=', $user_id)
				->where('status', '>', 0)
				->whereIn('specimen_id', $project_specimens)
				->whereIn('field_id', $project_field_ids)
				->get(['specimen_id', 'field_id']) as $row
		) {
			$done[$row->specimen_id . '*' . $row->field_id] = true;
		}

		//combinations skipped by this user during this session (see show())
		$visited = session()->get('visited_specimen') ?: array();
		$is_visited = function ($specimen_id, $field_id) use ($visited) {
			return in_array($specimen_id . '*' . $field_id, $visited)
				|| in_array($specimen_id . '*%', $visited);
		};

		//priority: combinations with pending contributions from other users (so consensus is reached sooner)
		foreach (
			Collaborative_fields::where('status', '=', 1)
				->where('user_id', '!=', $user_id)
				->whereIn('specimen_id', $project_specimens)
				->whereIn('field_id', $project_field_ids)
				->orderBy('specimen_id')
				->get(['specimen_id', 'field_id']) as $row
		) {
			$combo = $row->specimen_id . '*' . $row->field_id;
			if (!isset($done[$combo]) && !$is_visited($row->specimen_id, $row->field_id)) {
				return array('specimen_id' => $row->specimen_id, 'field_id' => $row->field_id);
			}
		}

		//otherwise: first specimen*field with no contribution yet
		foreach ($project_specimens as $specimen_id) {
			foreach ($project_field_ids as $field_id) {
				$combo = $specimen_id . '*' . $field_id;
				if (!isset($done[$combo]) && !$is_visited($specimen_id, $field_id)) {
					return array('specimen_id' => $specimen_id, 'field_id' => $field_id);
				}
			}
		}

		//nothing left for this user
		return array();
	}

	public function submit_comment(Request $request)
	{
		$this->validate($request, [
			'comment_value' => 'required',
		]);

		$user_id = auth()->user()->id;

		$comment = new Comments;
		$comment->value = $request->input('comment_value');
		$comment->specimen_id = $request->input('comment_specimen_id');
		$comment->field_id = $request->input('comment_field_id');
		$comment->project_id = $request->input('comment_project_id');
		$comment->user_id = $user_id;
		$comment->status = 1;
		$comment->save();

		$msg = preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a href="\1" target="_blank">\1</a>', $comment->value);

		if ($comment->user->username == '') {
			$comment->user->username = $comment->user->name;
		}

		$last_comment_id = $request->input('last_comment_id');

		$comments = Comments::where('specimen_id', '=', $comment->specimen_id)->where('field_id', '=', $comment->field_id)->where('status', '=', 1)->get();

		$recipients_comments = $comments->where('user_id', '!=', $user_id)->pluck('user_id')->unique();
		$recipients_collaborative = Collaborative_fields::where('specimen_id', '=', $comment->specimen_id)->where('field_id', '=', $comment->field_id)->where('user_id', '!=', $user_id)->pluck('user_id')->unique();

		$recipients = $recipients_comments->merge($recipients_collaborative);

		$recipients_users = User::where('comment_notifications', '=', 1)->wherein('id', $recipients)->get();

		foreach ($recipients_users as $recipient) {
			// the email subject ( modify it as you wish )
			$subject = 'EXPLORATOR - «' . $comment->specimen_id . ', ' . $comment->field->name . '» - novo comentário / new comment ';

			$message_body = 'Car@ ' . $recipient->name . ',<br><br>

Foi submetido um novo comentário no campo <b>' . $comment->field->name . '</b>  do exemplar <b>' . $comment->specimen_id . '</b>:<br><br>

<div style="padding:10px; border: 1px solid #EEEEEE;"><strong>' . e(display_name($comment->user, $recipient, $comment->project_id ? Project::find($comment->project_id) : null)) . '</strong><br>' . $msg . '</div><br><br>
Pode consultar o comentário <a href="' . url('projects/0/' . $comment->specimen_id . '/' . $comment->field->name) . '"><strong>aqui</strong></a><br>

';

			/*			$vars = array(
							'{$nome}' => $user->name
						);
						$message_body = strtr( $message_body, $vars );
			*/
			$emailcontent = array(
				'subject' => $subject,
				'body' => $message_body,
				'recipient_name' => $recipient->name,
				'recipient_email' => $recipient->email
			);

			Mail::send('emails.email', $emailcontent, function ($sendmessage) use ($emailcontent) {
				$sendmessage->to($emailcontent['recipient_email'], $emailcontent['recipient_name'])->subject($emailcontent['subject']);
			});
		}

		$new_comments = $comments->where('id', '>', $last_comment_id);

		$view = '';
		foreach ($new_comments as $new_comment) {
			if ($new_comment->user->username == '') {
				$new_comment->user->username = $comment->user->name;
			}

			if (auth()->user()->id == $new_comment->user_id) {
				$delete_button = '<div style="float: right"><button type="button" class="btn btn-danger btn-sm delete_comment" id="' . $new_comment->id . '"><i class="bi bi-trash3"></i></button></div>';
			} else {
				$delete_button = '';
			}

			$msg = preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a href="\1" target="_blank">\1</a>', $new_comment->value);

			$view .= '<tr><td>' . display_avatar($new_comment->user, 22, 'auto', $new_comment->project_id ? Project::find($new_comment->project_id) : null) . ' <strong>' . e(display_name($new_comment->user, 'auto', $new_comment->project_id ? Project::find($new_comment->project_id) : null)) . '</strong><div style="float: right"><small>' . $new_comment->created_at . '</small></div><br>' . $msg . $delete_button . '</td></tr>';
		}

		//send messages to users that have submited values to this field, or submitted comments to this field
		//$recipients = Comments::where('specimen_id', '=', $comment->specimen_id)->where('field_id', '=', $comment->field_id)->;		

		return response()->json(['html' => $view, 'last_comment_id' => $comment->id]);
	}

	public function delete_comment(request $request)
	{
		$comment = Comments::find($request->comment_id);


		// Check for correct user
		if (auth()->user()->id != $comment->user_id) {
			return redirect()->back()->with('error', 'Unauthorized action');
		}

		$comment->status = 0;
		$comment->save();

		$new_comments = Comments::where('specimen_id', '=', $comment->specimen_id)->where('field_id', '=', $comment->field_id)->where('status', '=', 1)->get();

		$view = '';
		foreach ($new_comments as $new_comment) {
			if ($new_comment->user->username == '') {
				$new_comment->user->username = $comment->user->name;
			}

			if (auth()->user()->id == $new_comment->user_id) {
				$delete_button = '<div style="float: right"><button type="button" class="btn btn-danger btn-sm delete_comment" id="' . $new_comment->id . '"><i class="bi bi-trash3"></i></button></div>';
			} else {
				$delete_button = '';
			}

			$msg = preg_replace('/((http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?)/', '<a href="\1" target="_blank">\1</a>', $new_comment->value);

			$view .= '<tr><td>' . display_avatar($new_comment->user, 22, 'auto', $new_comment->project_id ? Project::find($new_comment->project_id) : null) . ' <strong>' . e(display_name($new_comment->user, 'auto', $new_comment->project_id ? Project::find($new_comment->project_id) : null)) . '</strong><div style="float: right"><small>' . $new_comment->created_at . '</small></div><br>' . $msg . $delete_button . '</td></tr>';
		}

		//send messages to users that have submited values to this field, or submitted comments to this field
		//$recipients = Comments::where('specimen_id', '=', $comment->specimen_id)->where('field_id', '=', $comment->field_id)->;		

		return response()->json(['html' => $view, 'field_id' => $comment->field_id]);
	}

	public function stats()
	{
		
	}
}
