<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function customValidation(array $input, array $request)
    {
        $validator = Validator::make($input, $request);
        $message = "";
        $status = null;
        $success = true;

        if ($validator->fails()) {
            $status = 422;
            $success = false;
            $message = formatValidationErrors($validator->errors()->toArray());
            $message = translate($message);
        }

        return [
            $success,
            $message,
            $validator->validated(),
            $status 
        ];
    }
}
