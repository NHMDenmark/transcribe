<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Collaborative_fields;
use App\Models\Fields;
use App\Models\Dialogs;
use App\Models\Week_report;
use DB;


class UsersController extends Controller
{
	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public
	function __construct()
	{
		//$this->middleware( 'auth' );
	}

	public
	function index()
	{

		// Check for correct user
		if (!\Illuminate\Support\Facades\Gate::allows('manage-users')) {
			return redirect('/')->with('error', 'Unauthorized Page');
		}

		// deleted accounts last: they are all the same "Deleted user"
		$users = User::orderByRaw('anonymized_at is not null')->orderBy('name', 'asc')->get();

		return view('users.index')->with('users', $users)->with('roles', config('roles.roles'));
	}


	public function update_user_counts($user_id = null)
	{
		app(\App\Services\UserCounts::class)->recount($user_id);
	}
	/**
	 * Show the application dashboard.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public
	function show_collaborative_fields()
	{
		$user_id = auth()->user()->id;
		$user = User::find($user_id);
		$user = User::find($user_id);

		$fields = DB::table('fields')->orderBy('position', 'ASC')->get();

		$specimens_id = Collaborative_fields::where('user_id', '=', $user_id)->where('status', '!=', 0)->distinct('specimen_id')->pluck('specimen_id')->toarray();
		$collaborative_fields = array();
		foreach ($specimens_id as $specimen_id) {
			$fields_submited = Collaborative_fields::where('user_id', '=', $user_id)->where('status', '!=', 0)->where('specimen_id', '=', $specimen_id)->get();
			foreach ($fields_submited as $field_submited) {
				$collaborative_fields[$specimen_id][$field_submited->field_id] = $field_submited->status;
			}
		}

		return view('dashboard')->with('collaborative_fields', $collaborative_fields)->with('fields', $fields);
	}


	public function weekly_report()
	{
		app(\App\Services\WeeklyReport::class)->generate();
	}


	public

	function save(Request $request)
	{

		$rules = [
			'name' => 'required',
			'email' => 'required|email|max:255|unique:users,email,' . $request->user_id,
		];
		if ($request->hasFile('avatar')) {
			$rules['avatar'] = ['image', 'mimes:' . implode(',', \App\Services\AvatarStore::formats() ?: ['jpg']), 'max:2048'];
		}
		$validated = $request->validate($rules);

		if ($request->user_id != '') {
			$user = User::find($request->user_id);
		} else {
			$user = new User;
			$user->email_verified_at = date("Y-m-d H:i:s");
		}

		$user->name = $request->name;
		$user->email = $request->email;
		$user->category = $request->category;
		// the level is the admins' to set; the role only root's (and only to a known role)
		if (\Illuminate\Support\Facades\Gate::allows('manage-roles') && array_key_exists((string) $request->role, config('roles.roles'))) {
			$user->role = $request->role;
		}
		// root/admin may change anybody's profile: the public-name choice and the photo come with the same form
		if ($request->has('profile_fields')) {
			$user->public_name = $request->boolean('public_name');
		}
		$user->save();

		$avatars = app(\App\Services\AvatarStore::class);
		if ($request->boolean('remove_avatar')) {
			$avatars->remove($user);
		}
		if ($request->hasFile('avatar')) {
			try {
				$avatars->store($request->file('avatar'), $user);
			} catch (\RuntimeException $e) {
				if ($e->getCode() === \App\Services\AvatarStore::SERVER_PROBLEM) {
					$avatars->logFailure($e);

					return redirect('/users')->with('error', 'The user was saved, but the server could not save the photo (' . $e->getMessage() . ')');
				}

				return redirect('/users')->with('error', 'The user was saved, but the photo could not be used');
			}
		}

		return redirect('/users')->with('success', 'form was submitted');
	}

	/**
	 * Deletes an account the way the account's owner can: the person's data goes, their work stays as
	 * "Deleted user" (see UserAnonymizer). For root and admin (capability manage-users, set on the route).
	 */
	public function anonymize(Request $request, \App\Services\UserAnonymizer $anonymizer)
	{
		$user = User::findOrFail($request->input('user_id'));

		if ($user->id === auth()->id()) {
			return redirect('/users')->with('error', 'To delete your own account use your profile page.');
		}
		if ($reason = $anonymizer->refusal(auth()->user(), $user)) {
			return redirect('/users')->with('error', $reason);
		}

		$anonymizer->anonymize($user);

		return redirect('/users')->with('success', 'The account was deleted; its contributions stay as "Deleted user".');
	}

	public function setFieldView(Request $request)
	{
		$request->validate(['field_view' => 'nullable|in:single,all']);

		$user = auth()->user();
		$user->field_view = $request->field_view ?: null; // empty = inherit project/global default
		$user->save();

		return $this->redirectBackLocal($request);
	}

	/**
	 * Add or remove a field id from the current user's banned_fields list
	 * (pipe-delimited). Banning is global: the field is hidden in every project
	 * that uses it, in both form workflows.
	 */
	public function toggleFieldBan(Request $request)
	{
		$request->validate(['field_id' => 'required|integer']);

		$user = auth()->user();
		$banned = array_values(array_filter(explode('|', $user->banned_fields ?? '')));
		$field_id = (string) $request->field_id;

		if (in_array($field_id, $banned, true)) {
			$banned = array_diff($banned, [$field_id]);   // unban
		} else {
			$banned[] = $field_id;                         // ban
		}

		$user->banned_fields = implode('|', $banned) ?: null;
		$user->save();

		return $this->redirectBackLocal($request);
	}

	//return to the page an action was triggered from (local paths only)
	private function redirectBackLocal(Request $request)
	{
		$return = $request->input('return');
		if (is_string($return) && str_starts_with($return, '/')) {
			return redirect($return);
		}

		return redirect()->back();
	}

	public function dashboard(Request $request)
	{
		$user_id = auth()->user()->id;

		$collaborative_fields = $this->filter_submissions($request, Collaborative_fields::where('user_id', '=', $user_id));
		$fields = Fields::orderBy('name', 'asc')->get();
		// only the projects this person has worked on
		$projects = \App\Models\Project::whereIn('id', Collaborative_fields::where('user_id', '=', $user_id)->whereNotNull('project_id')->distinct()->pluck('project_id'))->orderBy('name')->get(['id', 'name']);

		return view('users.dashboard', [
			'collaborative_fields' => $collaborative_fields,
			'fields' => $fields,
			'projects' => $projects,
			'scope' => 'mine',
		]);
	}
	/** Root/admin see every submission; a project manager only those of the projects they manage. */
	private function limit_to_visible_projects($query)
	{
		if (!\Illuminate\Support\Facades\Gate::allows('view-all-submissions')) {
			$query->whereIn('project_id', auth()->user()->managedProjectIds());
		}

		return $query;
	}

	public function all_submissions(Request $request)
	{
		$query = $this->limit_to_visible_projects(Collaborative_fields::query());

		$query = $this->filter_by_user($request, $query);

		$collaborative_fields = $this->filter_submissions($request, $query);
		$fields = Fields::orderBy('name', 'asc')->get();
		$projects = \App\Models\Project::orderBy('name')
			->when(!\Illuminate\Support\Facades\Gate::allows('view-all-submissions'), fn($q) => $q->whereIn('id', auth()->user()->managedProjectIds()))
			->get(['id', 'name']);

		return view('users.dashboard', [
			'collaborative_fields' => $collaborative_fields,
			'fields' => $fields,
			'projects' => $projects,
			'scope' => 'all',
		]);
	}

	/**
	 * Applies the shared filter/sort parameters to a Collaborative_fields query
	 * and returns a paginator. Mirrors the /validated dashboard behaviour.
	 */
	private function apply_submission_filters(Request $request, $query)
	{
		// 'specimen' was only ever loaded to (mis)guess project_id — the column
		// itself is used directly now, so it's no longer needed here.
		$query->with(['field', 'user']);

		if ($request->filled('id')) {
			$query->where('id', $request->id);
		}
		if ($request->filled('project_id')) {
			if ($request->project_id === 'none') {
				$query->where(function ($q) {
					$q->whereNull('project_id')->orWhere('project_id', 0);
				});
			} else {
				$query->where('project_id', (int) $request->project_id);
			}
		}
		if ($request->filled('specimen_id')) {
			$query->where('specimen_id', 'like', '%' . $request->specimen_id . '%');
		}
		if ($request->filled('field_id')) {
			$query->where('field_id', $request->field_id);
		}
		if ($request->filled('value')) {
			$query->where('value', 'like', '%' . $request->value . '%');
		}
		if ($request->filled('status')) {
			$query->where('status', $request->status);
		}
		if ($request->filled('doubt')) {
			$query->where('doubt', $request->doubt);
		}
		if ($request->filled('deducted')) {
			$query->where('deducted', $request->deducted);
		}

		return $query;
	}

	/** The "User" filter (name or email): only on the all-submissions listing and its exports. */
	private function filter_by_user(Request $request, $query)
	{
		if ($request->filled('user')) {
			$user_search = $request->input('user');
			$query->whereHas('user', function ($q) use ($user_search) {
				$q->where('name', 'like', '%' . $user_search . '%')
					->orWhere('email', 'like', '%' . $user_search . '%');
			});
		}

		return $query;
	}

	private function filter_submissions(Request $request, $query)
	{
		$query = $this->apply_submission_filters($request, $query);

		$allowed_sorts = ['id', 'specimen_id', 'field_id', 'position', 'value', 'status', 'doubt', 'deducted', 'points', 'created_at', 'updated_at', 'displayed_at', 'time_spent', 'user_id', 'project_id'];
		$sort = in_array($request->get('sort'), $allowed_sorts) ? $request->get('sort') : 'id';
		$direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';
		if ($sort === 'time_spent') {
			$query->orderByRaw('TIMESTAMPDIFF(SECOND, displayed_at, created_at) ' . $direction);
		} else {
			$query->orderBy($sort, $direction);
		}

		return $query->paginate(20)->withQueryString();
	}
	public function stats(Request $request)
	{
		$user_id = auth()->user()->id;
		$start_date = date('Y-m-d', strtotime('today - 7 days'));
		$end_date = date('Y-m-d', strtotime('today + 1 days'));

		if (isset($request->date_range)) {
			$parts = explode(" - ", $request->date_range);
			$start_date = $parts[0] .' 00:00:00';
			$end_date = $parts[1].' 23:59:59';
		}
		$collaborative_fields = Collaborative_fields::where('user_id', '=', $user_id)->where('created_at', '>=', $start_date)->where('created_at', '<=', $end_date)->orderBy('id', 'DESC')->get();

		return view('users.stats')->with('collaborative_fields', $collaborative_fields)->with('start_date', $start_date)->with('end_date', $end_date);
	}

	public function all_stats(Request $request)
	{

		$start_date = date('Y-m-d', strtotime('today - 7 days'));
		$end_date = date('Y-m-d', strtotime('today + 1 days'));

		if (isset($request->date_range)) {
			$parts = explode(" - ", $request->date_range);
			$start_date = $parts[0] .' 00:00:00';
			$end_date = $parts[1].' 23:59:59';
		}
		$collaborative_fields = Collaborative_fields::where('created_at', '>=', $start_date)->where('created_at', '<=', $end_date)->orderBy('id', 'DESC')->get();



		return view('users.stats')->with('collaborative_fields', $collaborative_fields)->with('start_date', $start_date)->with('end_date', $end_date);
	}


	/** The columns of an exported submission. */
	private function submission_export_row($c, array $projects): array
	{
		$seconds = $c->displayed_at ? max(0, \Carbon\Carbon::parse($c->displayed_at)->diffInSeconds($c->created_at, false)) : null;

		return [
			'id' => $c->id,
			'specimen_id' => $c->specimen_id,
			'project_id' => $c->project_id,
			'project_name' => $projects[$c->project_id] ?? null,
			'field_id' => $c->field_id,
			'field_name' => $c->field->name ?? null,
			'position' => $c->position,
			'value' => $c->value,
			'status' => $c->status,
			'doubt' => $c->doubt,
			'deducted' => $c->deducted,
			'points' => $c->points,
			'user_id' => $c->user_id,
			'user_name' => $c->user->name ?? null,
			'displayed_at' => $c->displayed_at,
			'seconds_spent' => $seconds,
			'created_at' => (string) $c->created_at,
			'updated_at' => (string) $c->updated_at,
		];
	}

	/**
	 * The submissions the list is showing (same filters, no paging), from record start_id on: every
	 * one, or, for "My submissions", only the signed-in user's own.
	 */
	private function submissions_for_export(Request $request, bool $mine = false)
	{
		$query = $mine
			? Collaborative_fields::where('user_id', '=', auth()->id())
			: $this->filter_by_user($request, $this->limit_to_visible_projects(Collaborative_fields::query()));
		$query = $this->apply_submission_filters($request, $query);

		return $query->where('id', '>', (int) $request->query('start_id', 0))->orderBy('id');
	}

	public function all_submissions_csv(Request $request)
	{
		return $this->submissions_csv($this->submissions_for_export($request), 'user_submissions.csv');
	}

	public function my_submissions_csv(Request $request)
	{
		return $this->submissions_csv($this->submissions_for_export($request, true), 'my_submissions.csv');
	}

	private function submissions_csv($query, string $filename)
	{
		$projects = \App\Models\Project::pluck('name', 'id')->all();

		return response()->streamDownload(function () use ($query, $projects) {
			$out = fopen('php://output', 'w');
			fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));   // so a spreadsheet reads the UTF-8 right
			$headerWritten = false;
			$query->chunkById(500, function ($rows) use ($out, $projects, &$headerWritten) {
				foreach ($rows as $c) {
					$row = $this->submission_export_row($c, $projects);
					if (!$headerWritten) {
						fputcsv($out, array_keys($row));
						$headerWritten = true;
					}
					fputcsv($out, $row);
				}
			});
			if (!$headerWritten) {
				fputcsv($out, array_keys($this->submission_export_row(new Collaborative_fields(), [])));
			}
			fclose($out);
		}, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
	}

	public function all_submissions_json(Request $request)
	{
		return $this->submissions_json($request, $this->submissions_for_export($request));
	}

	public function my_submissions_json(Request $request)
	{
		return $this->submissions_json($request, $this->submissions_for_export($request, true));
	}

	private function submissions_json(Request $request, $query)
	{
		$per_page = max(1, min(1000, (int) $request->query('per_page', 15)));
		$projects = \App\Models\Project::pluck('name', 'id')->all();

		$page = $query->simplePaginate($per_page)->appends($request->query());
		$page->getCollection()->transform(fn($c) => $this->submission_export_row($c, $projects));

		return response()->json($page);
	}
}
