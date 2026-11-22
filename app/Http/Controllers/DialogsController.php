<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Models\Language;
use App\Models\Dialogs;
use Auth;

class DialogsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //        $this->middleware('auth', ['except' => ['index', 'show']]);
        $this->middleware('auth');
    }

    /**
     * Values for one dialog identifier across every language, keyed by iso2.
     * Fetched on demand (via AJAX) to populate the inline edit modal, so a
     * page with many dialogs doesn't have to preload every language's text
     * for every one of them.
     *
     * @param  string  $identifier
     * @return \Illuminate\Http\JsonResponse
     */
    public function values($identifier)
    {
        if (!Auth::check() || !\Illuminate\Support\Facades\Gate::allows('edit-dialogs')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $values = [];
        foreach (Language::all() as $language) {
            $entry = Dialogs::where('identifier', '=', $identifier)->where('language', '=', $language->iso2)->first();
            $values[$language->iso2] = $entry ? $entry->text : '';
        }

        return response()->json($values);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $languages = Language::all();
        foreach ($languages as $language) {
            $dialog = Dialogs::where('identifier', '=', $request->identifier)->where('language', '=', $language->iso2)->first();

            if (!isset($dialog)) {
                // Create dialog
                $dialog = new Dialogs;
                $dialog->identifier = $request->identifier;
                $dialog->language = $language->iso2;
            }

            $dialog->text = $request->input($language->iso2);
            $dialog->user_id = auth()->user()->id;
            $dialog->save();
        }


        return redirect()->back()->with('success', 'Dialog submitted');
    }
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($language_id)
    {
        if (!\Illuminate\Support\Facades\Gate::allows('edit-dialogs')) {
            return redirect('/')->with('error', 'Unauthorized Page');
        }

        $language = Language::find($language_id);
        $dialogs = Dialogs::where('language', '=', $language->iso2)->get();
        $masterlanguage = Language::where('master', '=', '1')->first();
        $masterdialogs = Dialogs::where('language', '=', $masterlanguage->iso2)->get()->toarray();

        return view('languages.dialogsedit')->with('dialogstoedit', $dialogs)->with('masterdialogs', $masterdialogs);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // Handle File Upload
        // Create dialog
        $dialog = Dialogs::find($request->id);

        // Check for correct user
        /*
        if(auth()->user()->permissions > 0){
            return redirect('/dialogs')->with('error', 'Unauthorized Page');
        }
		*/

        $dialog->text = $request->editval;

        $dialog->save();

        return redirect()->back()->with('success', 'Dialog updated');
    }

    function build_all_missing_dialogs(){
        $routeCollection = Route::getRoutes();

        foreach ($routeCollection as $value) {
            echo $value->getPath();

        }        
    }
}
