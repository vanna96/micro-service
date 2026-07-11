<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function categories(): View
    {
        return view('admin.master-data.categories');
    }

    public function items(): View
    {
        return view('admin.master-data.items');
    }
}
