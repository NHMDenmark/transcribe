<?php

namespace App\Imports;

use Illuminate\Http\Request;

use App\Models\Specimen;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SpecimenImport implements ToModel,WithHeadingRow ,WithValidation
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $request = request()->all();
        $project_id = $request['project_id'];
        $min_user_level = $request['min_user_level'];

        return new Specimen([
            //
            'specimen_id'     => $row['specimen_id'],
            'images'    => $row['images'],
            'project_id' => $project_id,
            'min_user_level' => $min_user_level
        ]);
    }

    /**
     * Write code on Method
     *
     * @return response()
     */
    public function rules(): array
    {
        return [
            'specimen_id' => 'required',
            'images' => 'required'
        ];
    }    
}
