<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index()
    {
        return Incident::all();
    }

    public function store(Request $request)
    {
        return Incident::create($request->all());
    }
}