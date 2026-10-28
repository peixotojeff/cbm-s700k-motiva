<?php

namespace App\Http\Controllers;

use App\Services\Cbm\Calendario;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarioController extends Controller
{
    public function index(Request $request, Calendario $calendario): View
    {
        return view('calendario.index', $calendario->calcular($request->attributes->get('horizonte')) + [
            'pmp' => config('cbm.pmp'),
        ]);
    }
}
