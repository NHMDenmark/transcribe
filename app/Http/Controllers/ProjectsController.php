<?php

namespace App\Http\Controllers;

ini_set('max_execution_time', 180); //3 minutes


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use App\Models\Validate_fields;
use App\Models\Specify_fields;
use App\Models\Specimen;
use App\Models\Fields;
use App\Models\Language;
use App\Models\Dialogs;

use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\SpecimenRequest;
use App\Imports\SpecimenImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

use Auth;
use DB;

class ProjectsController extends Controller
{
	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public
	function __construct()
	{
		//$this->middleware('auth', ['except' => ['start', 'index', 'show']]);
	}

	/** 403 unless the user may manage this project (root/admin: any; project manager: their own or given ones). */
	private function authorizeProject(?Project $project): void
	{
		abort_unless($project && Gate::allows('manage-projects', $project), 403);
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public
	function index()
	{
		//$projects = Project::all();
		//return Project::where('title', 'Project Two')->get();
		//$projects = DB::select('SELECT * FROM projects');
		//$projects = Project::orderBy('title','desc')->take(1)->get();
		//$projects = Project::orderBy('title','desc')->get();

		$projects = Project::visibleTo(Auth::user())->orderBy('created_at', 'desc')->paginate(10);

		foreach ($projects as $key => $project) {
			$project->specimens_count;
			$project->fields_completed;

			$project->possible_fields;

			$all_possible_fields = $project->specimens_count * $project->possible_fields;
			//echo $all_possible_fields.'<br>';	
			//echo $completed_fields.'<br>';
			if ($all_possible_fields > 0) {

				$completed_percent = $project->fields_completed / $all_possible_fields * 100;
			} else
				$completed_percent = 0;
			//echo $completed_percent.'<br>';

			$projects[$key]->specimens = $project->specimens_count;
			$projects[$key]->fields = $all_possible_fields;
			$projects[$key]->fields_completed = $project->fields_completed;
			$projects[$key]->completed_percent = $completed_percent;
		}
		return view('projects.index')->with('projects', $projects);
	}

	public
	function start($project_id)
	{
		//		$project_id = $request->project;

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

		//pick the next specimen; show() lands the user on the right field for their workflow
		$pick = app(SpecimensController::class)->next_pick($project_id, $user_id);
		$specimen_id = $pick['specimen_id'] ?? '';

		if ($specimen_id != '') {
			return redirect('/projects/' . $project_id . '/' . $specimen_id);
		} else {
			return redirect('/projects')->with('warning', 'no_specimen');
		}
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public
	function create()
	{
		abort_unless(Gate::allows('manage-projects'), 403);

		$project = new Project;
		//
		return view('projects.create', [
			'project' => $project,
			'api_schema' => API_get_schema(),
		]); //returns the view with posts
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public
	function store(Request $request)
	{

		$this->validate($request, [
			'name' => 'required',
			'cover_image' => 'image|nullable|max:1999'
		]);

		if ($request->project_id != '') {
			$project = Project::find($request->project_id);
			$this->authorizeProject($project);
		} else {
			// Create Project
			abort_unless(Gate::allows('manage-projects'), 403);
			$project = new Project;
		}

		// Handle File Upload
		if ($request->hasFile('cover_image')) {
			// Get filename with the extension
			$filenameWithExt = $request->file('cover_image')->getClientOriginalName();
			// Get just filename
			$filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
			// Get just ext
			$extension = $request->file('cover_image')->getClientOriginalExtension();
			// Filename to store
			$fileNameToStore = $filename . '_' . time() . '.' . $extension;
			// Upload Image
			$path = $request->file('cover_image')->storeAs('cover_images', $fileNameToStore);
		} else {
			// keep the existing cover image when editing without picking a new file
			$fileNameToStore = $project->cover_image ?? 'noimage.jpg';
		}

		if (isset($request->active)) {
			$active = 1;
		} else {
			$active = 0;
		}

		$project->active = $active;
		$project->name = $request->input('name');
		if (!$project->exists) {
			$project->user_id = auth()->user()->id; // the creator stays the owner on later edits
		}
		$project->cover_image = $fileNameToStore;
		$project->field_view_default = in_array($request->input('field_view_default'), ['single', 'all'], true)
			? $request->input('field_view_default')
			: null;
		$project->sync_root_table = $request->input('sync_root_table') ?: 'collectionobject';
		$project->sync_root_field = $request->input('sync_root_field') ?: 'catalognumber';
		$project->specify_collection = $request->filled('specify_collection') ? (string) (int) $request->input('specify_collection') : null;
		if ($request->has('others_panel_audience')) {
			$project->others_panel_audience = in_array($request->input('others_panel_audience'), Project::OTHERS_PANEL_AUDIENCES, true)
				? $request->input('others_panel_audience')
				: 'project_manager';
		}
		$project->sync_overwrite = $request->input('sync_overwrite') === 'keep_existing' ? 'keep_existing' : 'overwrite';
		$project->sync_compare_case_insensitive = $request->boolean('sync_compare_case_insensitive');
		if (Gate::allows('run-sync') && $request->has('auto_sync_present')) {
			$this->saveAutoSync($request, $project);
		}
		$decimals = $request->input('sync_compare_round_decimals');
		$project->sync_compare_round_decimals = is_numeric($decimals) ? max(0, min(10, (int) $decimals)) : null;
		$project->save();

		// who else may manage it: root/admin and the project's own managers (its creator included) decide,
		// and only users that hold the project manager role can be given it; the creator is not in this list
		if (Gate::allows('manage-projects', $project) && $request->has('managers_present')) {
			$project->managers()->sync(
				\App\Models\User::where('role', 'project_manager')->whereIn('id', (array) $request->input('managers', []))->pluck('id')->all()
			);
		}

		//save the dialogs for each language
		$dialogs = array('title', 'abstract', 'body');
		$languages = Language::get();

		foreach ($dialogs as $dialog) {
			foreach ($languages as $language) {
				$dialog_identifier = 'project_' . $dialog . '_' . $project->id;
				$dialog_project_form_name = 'project_' . $dialog . '_' . $language->iso2;
				$dialog_project = Dialogs::where('identifier', '=', $dialog_identifier)->where('language', '=', $language->iso2)->first();
				if (!isset($dialog_project)) {
					$dialog_project = new Dialogs;
					$dialog_project->identifier = $dialog_identifier;
					$dialog_project->language = $language->iso2;
				}
				$dialog_project->text = $request->$dialog_project_form_name;
				$dialog_project->save();
			}
		}


		return redirect('/projects')->with('success', 'Project Created');
	}

	public function data_upload_form($project_id)
	{
		$project = Project::where('id', '=', $project_id)->first(); //fetch item from DB

		$this->authorizeProject($project);

		return view('projects.upload', [
			'project' => $project
		]); //returns the view with posts

	}

	public function import(SpecimenRequest $request)
	{
		$this->authorizeProject(Project::find($request->project_id));

		if ($request->replace_update == 0) {
			$records = Specimen::where('project_id', '=', $request->project_id)->delete();
		}

		try {

			Excel::import(new SpecimenImport, $request->file('file'));
			//new/replaced specimens change specimens_count and possible_fields x specimens_count
			$this->update_project_stats();
			//return response()->json(['data'=>'Specimens imported successfully.',201]);
			return redirect('projects/' . $request->project_id . '/specimens')->with('success', 'file was imported');
		} catch (\Exception $ex) {
			Log::info($ex);
			echo $ex;
			//return response()->json(['data'=>'Some error has occur.',400]);

		}
	}

	public function specimens($project_id)
	{
		$project = Project::where('id', '=', $project_id)->first(); //fetch item from DB
		$this->authorizeProject($project);

		return view('projects.specimens', [
			'project' => $project
		]); //returns the view with posts

	}


	public
	function fields($project_id)
	{

		// Check for correct user
		$project = Project::find($project_id);
		if (!$project || !Gate::allows('manage-projects', $project)) {
			return redirect('/projects')->with('error', 'Unauthorized Page');
		}
		$fields = Fields::orderBy('parent')->orderBy('position')->get();
		$to_remove = array();
		foreach ($fields as $field) {
			$subfields = $fields->where('parent', '=', $field->id);
			$field->subfields = $subfields;
			foreach ($subfields as $subfield) {
				$to_remove[] = $subfield->id;
			}
		}
		$fields = $fields->whereNotIn('id', $to_remove);


		return view('projects.fields')->with('project', $project)->with('fields', $fields);
	}

	public function update_fields(Request $request)
	{
		$project     = Project::find($request->project_id);
		$this->authorizeProject($project);
		$newFieldIds = array_map('intval', $request->input('field', []));

		// Detect removed fields: currently attached but not in the new selection
		$currentFieldIds = $project->fields->pluck('id')->map(fn($id) => intval($id))->toArray();
		$removedFieldIds = array_values(array_diff($currentFieldIds, $newFieldIds));

		$deleteDecisions = $request->input('delete_validated', []);
		$softDeletedSummary = [];

		foreach ($removedFieldIds as $fieldId) {
			if (($deleteDecisions[$fieldId] ?? '0') === '1') {
				$specimenIds = \App\Models\Specimen::where('project_id', $project->id)
					->pluck('specimen_id')
					->toArray();

				$affected = \App\Models\Validate_fields::whereIn('specimen_id', $specimenIds)
					->where('field_id', $fieldId)
					->where('status', '>', 0)
					->update(['status' => 0]);

				$fieldName = \App\Models\Fields::find($fieldId)?->name ?? "field #$fieldId";
				$softDeletedSummary[] = "$fieldName ($affected records)";

				\Illuminate\Support\Facades\Log::info(
					"Soft-deleted $affected validated_fields for field_id=$fieldId, project_id={$project->id}"
				);
			}
		}

		// Update field associations
		$project->fields()->detach();
		foreach ($newFieldIds as $fieldId) {
			$project->fields()->attach($fieldId);
		}

		$project->save();

		//activating/deactivating fields changes possible_fields and, via the
		//soft-deletes above, potentially fields_completed too
		$this->update_project_stats();

		$message = 'Project Updated';
		if (!empty($softDeletedSummary)) {
			$message .= ' — Validated values deactivated for: ' . implode(', ', $softDeletedSummary);
		}

		return redirect('projects/' . $project->id . '/fields')->with('success', $message);
	}



	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public
	function show($id)
	{

		$project = Project::find($id);

		if ((isset($project) and ($project->id != 0))) {

			$project_specimens = DB::table('specimens')->where('project_id', '=', $project->id)->get();

			$project->specimens_count;
			$project->fields_completed;

			$project->possible_fields;

			$all_possible_fields = $project->specimens_count * $project->possible_fields;
			//echo $all_possible_fields.'<br>';	
			//echo $completed_fields.'<br>';
			if ($all_possible_fields > 0) {

				$completed_percent = $project->fields_completed / $all_possible_fields * 100;
			} else
				$completed_percent = 0;
			//echo $completed_percent.'<br>';

			$project->fields = $all_possible_fields;
			$project->completed_percent = $completed_percent;

			return view('projects.show')->with('project', $project)->with('project_specimens', $project_specimens);
		} else {
			return redirect('/projects');
		}
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public
	function edit($id)
	{

		// Check for correct user
		$project = Project::find($id);
		if (!$project || !Gate::allows('manage-projects', $project)) {
			return redirect('/projects')->with('error', 'Unauthorized Page');
		}

		return view('projects.edit')->with('project', $project)->with('api_schema', API_get_schema());
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public
	function update(Request $request, $id)
	{

		// Update Project

		$project = Project::find($id);
		$this->authorizeProject($project);
		$project->fields()->detach();

		foreach ($request->input('field') as $field_id) {
			$project->fields()->attach($field_id);
		}

		$project->save();

		return redirect('/project_edit/' . $id)->with('success', 'Project Updated');
	}



	public
	function update_specimens($id)
	{
		$project = Project::find($id);

		// Check for correct user
		if (!$project || !Gate::allows('manage-projects', $project)) {
			return redirect('/projects')->with('error', 'Unauthorized Page');
		}

		$project_specimens = DB::table('specimens')->where('project_id', '=', $project->id)->get();

		return view('projects.edit_specimens')->with('project', $project)->with('project_specimens', $project_specimens);

		//return redirect( '/projects' )->with( 'success', 'Specimens updated' );
	}

	public
	function update_specimens_submit(Request $request)
	{
		// Check for correct user
		$project_id = $request->project_id;
		$project = Project::find($project_id);

		if (!$project || !Gate::allows('manage-projects', $project)) {
			return redirect('/projects')->with('error', 'Unauthorized Page');
		}

		DB::table('specimens')->where('project_id', '=', $project_id)->delete();

		$specimens = explode(PHP_EOL, $request->specimens);

		foreach ($specimens as $specimen) {
			//echo $specimen;
			$specimen_image = DB::table('images')->where('CatalogNumber', '=', $specimen)->value('image_name');
			//echo ' - '.$specimen_image; 
			$specimens_per_sheet = substr_count($specimen_image, 'COI');
			//echo ' - '.$specimens_per_sheet;
			//echo '<br>';
			$min_user_level = 6;
			if ($specimens_per_sheet > 1) {
				$min_user_level = 2;
			}
			if ($specimens_per_sheet == 0) {
				$min_user_level = 0;
			}

			DB::table('specimens')->insert(['project_id' => $project_id, 'specimen_id' => $specimen, 'min_user_level' => $min_user_level]);
		}

		return redirect('/projects')->with('success', 'Specimens updated');
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public
	function destroy($id)
	{
		$project = Project::find($id);

		// Check for correct user
		if (!$project || !Gate::allows('manage-projects', $project)) {
			return redirect('/projects')->with('error', 'Unauthorized Page');
		}

		// Soft-delete validated values if the admin chose to do so.
		// Affects all validated_fields whose specimen belongs to this project
		// and whose field is active for this project.
		if (request()->input('delete_validated') == 1) {
			$specimenIds = \App\Models\Specimen::where('project_id', $project->id)
				->pluck('specimen_id')
				->toArray();

			$fieldIds = $project->fields->pluck('id')->toArray();

			$affected = \App\Models\Validate_fields::whereIn('specimen_id', $specimenIds)
				->whereIn('field_id', $fieldIds)
				->where('status', '>', 0)
				->update(['status' => 0]);

			\Illuminate\Support\Facades\Log::info(
				"Soft-deleted $affected validated_fields for project_id={$project->id}"
			);
		}

		if ($project->cover_image != 'noimage.jpg') {
			Storage::delete('public/cover_images/' . $project->cover_image);
		}

		$project->delete();
		return redirect('/projects')->with('success', 'Project Removed');
	}

	/** Recalculates the progress figures of the projects (all of them, or the ones given); see ProjectStats. */
	/**
	 * The schedule of the project's automatic sync. When it changes (or is switched on) the moments already past are
	 * marked as handled, so the project waits for the next one instead of running at once.
	 */
	private function saveAutoSync(Request $request, Project $project): void
	{
		$data = $request->validate([
			'auto_sync_frequency' => ['nullable', 'in:' . implode(',', \App\Services\AutoSyncSchedule::FREQUENCIES)],
			'auto_sync_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
			'auto_sync_weekday' => ['nullable', 'integer', 'between:0,6'],
			'auto_sync_every' => ['nullable', 'in:' . implode(',', \App\Services\AutoSyncSchedule::EVERY)],
		]);

		$before = [$project->auto_sync, $project->auto_sync_frequency, $project->auto_sync_time, $project->auto_sync_weekday, $project->auto_sync_every];

		$project->auto_sync = $request->boolean('auto_sync');
		$project->auto_sync_frequency = $data['auto_sync_frequency'] ?? $project->auto_sync_frequency ?: 'daily';
		$project->auto_sync_time = $data['auto_sync_time'] ?? $project->auto_sync_time ?: '02:00';
		$project->auto_sync_weekday = $data['auto_sync_weekday'] ?? $project->auto_sync_weekday ?? 1;
		$project->auto_sync_every = $data['auto_sync_every'] ?? $project->auto_sync_every ?: 6;

		if ($project->auto_sync && $before !== [$project->auto_sync, $project->auto_sync_frequency, $project->auto_sync_time, $project->auto_sync_weekday, $project->auto_sync_every]) {
			$project->auto_sync_last_slot = app(\App\Services\AutoSyncSchedule::class)->latestSlot($project, now(), \App\Models\Setting::current()->schedule_timezone);
		}
	}

	public function update_project_stats(?array $projectIds = null)
	{
		app(\App\Services\ProjectStats::class)->update($projectIds);
	}
}
