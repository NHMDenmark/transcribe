<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Language;
use App\Models\Fields;
use App\Models\Dialogs;
use DB;

class LanguagesController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['index', 'show']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$languages = Language::all();
        //return Language::where('title', 'Language Two')->get();
        //$languages = DB::select('SELECT * FROM languages');
        //$languages = Language::orderBy('title','desc')->take(1)->get();
        //$languages = Language::orderBy('title','desc')->get();

        $languages = Language::orderBy('created_at', 'desc')->paginate(10);

        return view('languages.index')->with('languages', $languages);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('languages.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'iso2' => 'required',
            'text' => 'required'
        ]);

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
            $path = $request->file('cover_image')->storeAs('public/cover_images', $fileNameToStore);
        } else {
            $fileNameToStore = 'noimage.jpg';
        }

        // Create Language
        if ($request->language_id != '') {
            $language = Language::find($request->language_id);
        } else {
            $language = new Language;
        }

        $language->iso2 = $request->input('iso2');
        $language->text = $request->input('text');
        $language->active = $request->active;
        $language->user_id = auth()->user()->id;
        $language->cover_image = $fileNameToStore;
        $language->save();

        if ($request->language_id == '') {
            $master_language = Language::where('master', '=', 1)->pluck('iso2');

            $dialogs = Dialogs::where('language', '=', $master_language)->get();

            foreach ($dialogs as $dialog) {
                $new_dialog = new Dialogs;
                $new_dialog->identifier = $dialog->identifier;
                $new_dialog->language = $language->iso2;

                $new_dialog->save();
            }
        }

        if(isset($request->master))
        {
            $languages = Language::where('id', '!=', $language->id)->get();
            foreach($languages as $language){
                $language->master = 0;
                $language->save();
            }

            $language = Language::find($request->language_id);
            $language->master = 1;
            $language->save();

        }

        /*
		$field_parameters = array ('name', 'text', 'question');
		foreach ($Fields as $field)
		{
			foreach($field_parameters as $parameter)
			{
			$dialog = new Dialogs;
			$dialog->identifier = 'fields_'.$parameter.'_'.$field->id;
			$dialog->language = $language->iso2;
			
			$dialog->save();
			}
		}
*/
        return redirect('/languages')->with('success', 'Language submitted');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $language = Language::find($id);
        return view('languages.show')->with('language', $language);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $language = Language::find($id);

        // Check for correct user
        if (!\Illuminate\Support\Facades\Gate::allows('manage-languages')) {
            return redirect('/languages')->with('error', 'Unauthorized Page');
        }

        return view('languages.edit')->with('language', $language);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'iso2' => 'required',
            'text' => 'required'
        ]);

        // Create Language
        $language = Language::find($id);
        $language->iso2 = $request->input('iso2');
        $language->text = $request->input('text');
        $language->save();

        return redirect('/languages')->with('success', 'Language Updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $language = Language::find($id);

        // Check for correct user
        if (!\Illuminate\Support\Facades\Gate::allows('manage-languages')) {
            return redirect('/languages')->with('error', 'Unauthorized Page');
        }

        if ($language->cover_image != 'noimage.jpg') {
            // Delete Image
            Storage::delete('public/cover_images/' . $language->cover_image);
        }


        $dialogs = Dialogs::where('language', '=', $language->iso2)->get();

        foreach ($dialogs as $dialog) {
            $dialog->delete();
        }

        $language->delete();

        return redirect('/languages')->with('success', 'Language Removed');
    }
}
