<?php

namespace App\Http\Controllers;

use App\Models\Nvr;

class NvrController extends Controller
{
    public function index()
    {
        return Nvr::all();
    }
}