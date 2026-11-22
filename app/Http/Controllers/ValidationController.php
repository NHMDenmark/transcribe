<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use App\Models\Fields;
use App\Models\User;
use App\Models\User_categories;
use App\Models\Collaborative_fields;
use App\Models\Validate_fields;
use App\Models\Specify_fields;
use DB;
use Auth;

use Illuminate\Support\Facades\Response;

class ValidationController extends Controller
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
		$this->middleware('auth', ['except' => ['validated_json', 'autovalidate']]);
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
public function index(Request $request)
{
    // Usamos 'with' para evitar o problema N+1 ao carregar os nomes dos fields.
    // 'specimen' was only ever loaded to (mis)guess project_id — the column
    // itself is used directly now, so it's no longer needed here.
    // 'field.mapping' lets the view grey out the Sync button for fields with
    // no API mapping configured, instead of linking to a sync attempt that's
    // guaranteed to fail with "no API mapping".
    $query = Validate_fields::with(['field.mapping']);

    // Root/admin see everything; a project manager only reaches this list to resolve
    // conflicts, and only for the projects they manage.
    if (!Gate::allows('run-sync')) {
        abort_unless(Gate::allows('resolve-conflicts'), 403);
        $query->whereIn('project_id', auth()->user()->managedProjectIds());
    }

    // Filter by ID
    if ($request->filled('id')) {
        $query->where('id', $request->id);
    }

    // Filter by project ('none' = rows without one)
    if ($request->filled('project_id')) {
        if ($request->project_id === 'none') {
            $query->where(function ($q) {
                $q->whereNull('project_id')->orWhere('project_id', 0);
            });
        } else {
            $query->where('project_id', (int) $request->project_id);
        }
    }

    // Filter by Specimen ID
    if ($request->filled('specimen_id')) {
        $query->where('specimen_id', 'like', '%' . $request->specimen_id . '%');
    }

    // Filter by Value
    if ($request->filled('value')) {
        $query->where('value', 'like', '%' . $request->value . '%');
    }

    // Filter by Field (Dropdown)
    if ($request->filled('field_id')) {
        $query->where('field_id', $request->field_id);
    }

    // Filter by Status
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    // Sorting
    $sort = $request->get('sort', 'id');
    $direction = $request->get('direction', 'desc');
    $query->orderBy($sort, $direction);

    $collaborative_fields = $query->paginate(20)->withQueryString();
    $fields = Fields::orderBy('name', 'asc')->get();

    // the projects to choose from: all for root/admin, only their own for a project manager
    $projects = \App\Models\Project::orderBy('name')
        ->when(!Gate::allows('run-sync'), fn($q) => $q->whereIn('id', auth()->user()->managedProjectIds()))
        ->get(['id', 'name']);

    return view('validation.dashboard', compact('collaborative_fields', 'fields', 'projects'));
}
	/**
	 * Store a newly created resource in storage.
	 *
	 * @param\Illuminate\Http\Request$request
	 * @return \Illuminate\Http\Response
	 */
	public

	function store($valid_record, $agent = null)
	{

		if ($agent == null) {
			$agent = auth()->user()->id;
		}

		$specimen_id = $valid_record['specimen_id'];
		$field_id = $valid_record['field_id'];
		$position = $valid_record['position'];
		$value = $valid_record['value'];
		$project_id = $valid_record['project_id'] ?? null;

		//altera o status das respostas anteriores para 0 (não válido)
		/*
            Validate_fields::where([
                ['status', '=', 1],
                ['specimen_id', '=', $specimen_id],
                ['field_id', '=', $field_id],
                ['position', '=', $position],
				['value', '!=', $value]
            ])->update(['status' => 0]);
	*/

		//altera o status das validações anteriores para 0
		Validate_fields::where([
			['status', '=', 1],
			['specimen_id', '=', $specimen_id],
			['field_id', '=', $field_id],
			['position', '=', $position],
		])->update(
			[
				'status' => 0
			]
		);

		//insere um novo registo na tabela dos campos validados
		$valid_field = new Validate_fields;
		$valid_field->value = $value;
		$valid_field->position = $position;
		$valid_field->specimen_id = $specimen_id;
		$valid_field->field_id = $field_id;
		$valid_field->user_id = $agent;
		$valid_field->status = 1;
		$valid_field->project_id = $project_id;
		$valid_field->save();
		$inserted_id = $valid_field->id;


		//altera o status das respostas coincidentes para aceite
		Collaborative_fields::where([
			['status', '>', 0],
			['specimen_id', '=', $specimen_id],
			['field_id', '=', $field_id],
			['position', '=', $position],
			//			[ DB::raw('TRIM(TRAILING "." FROM value)'), '=', $value ]
			['value', '=', $value]
		])->update(
			[
				'status' => 2,
				'validation_id' => $inserted_id
			]
		);

		//altera o status das respostas divergentes para rejeitado
		Collaborative_fields::where([
			['status', '>', 0],
			['specimen_id', '=', $specimen_id],
			['field_id', '=', $field_id],
			['position', '=', $position],
			//			[ DB::raw('TRIM(TRAILING "." FROM value)'), '!=', $value ]
			['value', '!=', $value]
		])->update(
			[
				'status' => 3,
				'validation_id' => $inserted_id
			]
		);

		$this->promote_users($inserted_id);
	}

	public function autovalidate_this($specimen_id)
	{

		echo $specimen_id . '<br>';
		//$data = Collaborative_fields::where( 'status', '=', 1 )->where('specimen_id', '=', $specimen_id)->groupBy( 'specimen_id', 'field_id', 'position', 'value' )->get()->toarray();

		$data = Collaborative_fields::groupBy('specimen_id', 'field_id', 'position', 'value', 'project_id')->selectRaw('specimen_id, field_id, position, value, project_id, sum(points) as sumpoints')->where('status', '=', 1)->where('specimen_id', '=', $specimen_id)->having('sumpoints', '>=', 60)->get()->toarray();

		echo '<pre>';
		print_r($data);
		echo '</pre>';

		foreach ($data as $record) {
			$this->store($record, 'system');
		}
	}

	public

	function autovalidate()

	{
		ini_set('max_execution_time', 0); //0=NOLIMIT		

		/*
		$data = Collaborative_fields::where( 'status', '=', 1 )->groupBy( 'specimen_id', 'field_id', 'position', 'value' )->orderBy('specimen_id', 'field_id', 'position', 'value' )->get()->toarray();
		echo '<pre>' ;
				print_r($data);
		echo '</pre>' ;

		foreach ( $data as $record ) {

			echo '<pre>' ;
				print_r($record);
				echo '</pre>' ;
				
				echo $record['value'].'<br>';
			
			$sumpoints = Collaborative_fields::where( 'specimen_id', '=', $record[ 'specimen_id' ] )->where( 'field_id', '=', $record[ 'field_id' ] )->where( 'position', '=', $record[ 'position' ] )->where( 'value', 'like', $record[ 'value' ] )->where( 'status', '>=', 1 )->sum( 'points' );

			//echo $sumpoints;

			if ( $sumpoints >= 60 ) {
			
				$this->store( $record, 'system' );
			}		
*/


		//		$data = Collaborative_fields::groupBy( 'specimen_id', 'field_id', 'position', DB::raw('TRIM(TRAILING "." FROM value)') )->selectRaw('specimen_id, field_id, position, TRIM(TRAILING "." FROM value) as value, sum(points) as sumpoints')->where('status', '=', 1)->having('sumpoints', '>=', 60)->get()->toarray();

		$data = Collaborative_fields::groupBy('specimen_id', 'field_id', 'position', 'value', 'project_id')->selectRaw('specimen_id, field_id, position, value, project_id, sum(points) as sumpoints')->where('status', '=', 1)->having('sumpoints', '>=', 60)->get()->toarray();

		/*
		echo '<pre>' ;
				print_r($data);
		echo '</pre>' ;
*/
		foreach ($data as $record) {

			/*
			echo '<pre>' ;
				print_r($record);
				echo '</pre>' ;
				
				echo $record['value'].'<br>';
			*/

			$this->store($record, 'system');
		}
		//		return response()->json($result);
	}

	public

	function promote_users($validation_id = null)
	{
		//			echo $validation_id;

		if($validation_id == null)
		{
			$user_ids = User::pluck('id');
		}
		else
		{
			$user_ids = Collaborative_fields::where('status', '=', 2)->where('validation_id', '=', $validation_id)->pluck('user_id');
		}



		/*			echo '<pre>';
					print_r($user_ids);
					echo '</pre>';
		*/

		foreach ($user_ids as $user_id) {

			if (is_numeric($user_id)) {

				$user = User::find($user_id);

				$count_valid = Collaborative_fields::where('status', '=', 2)->groupBy('specimen_id', 'field_id')->where('user_id', '=', "$user->id")->get()->count();

				$count_rejected = Collaborative_fields::where('status', '=', 3)->groupBy('specimen_id', 'field_id')->where('user_id', '=', "$user->id")->get()->count();

				$count_submitted = Collaborative_fields::where('status', '=', 1)->groupBy('specimen_id', 'field_id')->where('user_id', '=', "$user->id")->get()->count();

				$user->validated = $count_valid;
				$user->rejected = $count_rejected;
				$user->submitted = $count_submitted;
				$user->save();




				$levels = User_categories::where('id', '!=', 0)->where('points', '>', $user->user_categories->points)->get();

				/*
				echo '<pre>';
				print_r($levels);
				echo '</pre>';
				*/


				foreach ($levels as $level) {
					//$level->validations_required.'<br>';
					if ($count_valid >= $level->validations_required) {
						$user->category = $level->id;
					}
				}
				$user->save();
			}
		}
		//check how many valid answers an user has to promote to following level.


	}



	//there were some incorrect procedures for validations along the way.
	//this function is to make new validations based on what was considered validated before 
	public

	function reevaulate_validation()
	{
		ini_set('max_execution_time', 0); //0=NOLIMIT		

		$validated_fileds = Validate_fields::whereRaw('id in (select max(id) from validated_fields group by specimen_id, field_id,position)')->get()->toarray();


		foreach ($validated_fileds as $validated_field) {
			echo '<pre>';
			print_r($validated_field);
			echo '</pre>';

			$this->store($validated_field);
		}
	}


	function validated_json(Request $request)
	{
		$start_id = 0;
		if (isset($request->start_id)) {
			$start_id = $request->start_id;
		}
		$per_page = null;
		if (isset($request->per_page)) {
			$per_page = $request->per_page;
		}

		$validated = Validate_fields::where('id', '>', $start_id)->where('status', '=', 1)->simplePaginate($per_page)->appends(request()->query());
		foreach($validated as $line)
		{
			$line->field_name = $line->field->name;
			unset($line->field);
		}

		return response()->json($validated);
	}


	function validated_csv(Request $request)
	{
		$start_id = 0;
		if (isset($request->start_id)) {
			$start_id = $request->start_id;
		}

		$validated = Validate_fields::where('id', '>', $start_id)->where('status', '=', 1)->get(['id', 'specimen_id', 'field_id', 'position', 'value', 'status', 'user_id', 'created_at', 'updated_at']);
		foreach($validated as $line)
		{
			$line->field_name = $line->field->name;
			unset($line->field);

			//ksort($line);
			//->only(['id', 'specimen_id', 'field_id', 'field_name', 'position', 'value', 'status', 'user_id', 'created_at', 'updated_at']);
		}

		$key_order = ['id', 'specimen_id', 'field_id', 'field_name', 'position', 'value', 'status', 'user_id', 'created_at', 'updated_at'];
		$validated = $validated->sortKeysUsing('strnatcasecmp');


		$validated = $validated->toArray();


		// Write data to CSV
		$csvFileName = 'validated_values.csv';
		$csvFile = fopen($csvFileName, 'w');
		$headers = array_keys((array) $validated[0]); // Get the column headers from the first row
		fputcsv($csvFile, $headers);

		foreach ($validated as $row) {
			fputcsv($csvFile, (array) $row);
		}

		fclose($csvFile);

		// Download the CSV file
		return Response::download(public_path($csvFileName))->deleteFileAfterSend(true);
	}


	/**
	 * Sets a validated value aside (status 7): one that is waiting to be sent, or whose last attempt
	 * failed, will not be sent to Specify. It can still be sent by hand ("Force"). Root/admin only
	 * (route middleware run-sync).
	 */
	public function ignore($id)
	{
		$record = Validate_fields::findOrFail($id);
		abort_unless(in_array((int) $record->status, [1, 3], true), 409);

		$record->status = 7;
		$record->save();

		return redirect()->back()->with('success', 'Set aside: it will not be sent to Specify');
	}
}
