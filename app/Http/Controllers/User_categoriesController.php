<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User_categories;
use App\Models\Dialogs;
use App\Models\Language;

class User_categoriesController extends Controller
{
	public

	function index()
	{


		$user_categories = User_categories::orderBy('points')->get();

		$user_categor_dialogs = Dialogs::where('identifier', 'LIKE', 'user_category_%')->get();

		return view('user_categories.show', compact('user_categories', 'user_categor_dialogs'));
	}

	function show()
	{

	}



	public

	function store(Request $request)
	{

		//		echo $request->user_category_id;

		if ($request->user_category_id != '') {
			$user_category = User_categories::find($request->user_category_id);
		} else {
			$user_category = new User_categories;
		}

		$user_category->category = $request->category;
		//			$user_category->question = $request->question;
		//			$user_category->text = $request->text;
		$user_category->validations_required = $request->validations_required;
		$user_category->points = $request->points;
		$user_category->save();

		//save the dialogs for each language
		$dialogs = array('name', 'description');
		$languages = Language::get();

		foreach ($dialogs as $dialog) {
			foreach ($languages as $language) {
				$dialog_identifier = 'user_categories_' . $dialog . '_' . $user_category->id;
				$dialog_user_category_form_name = 'user_categories_' . $dialog . '_' . $language->iso2;
				$dialog_user_category = Dialogs::where('identifier', '=', $dialog_identifier)->where('language', '=', $language->iso2)->first();
				if (!isset($dialog_user_category)) {
					$dialog_user_category = new Dialogs;
					$dialog_user_category->identifier = $dialog_identifier;
					$dialog_user_category->language = $language->iso2;
				}
				$dialog_user_category->text = $request->$dialog_user_category_form_name;
				$dialog_user_category->save();
			}
		}

		return redirect('/user_categories')->with('success', 'Form was submitted');
	}

	public function destroy(Request $request)
	{
		$user_category = User_categories::find($request->user_category_id)->delete();

		return response()->json(['message' => ' User_category was deleted']);
	}
}
