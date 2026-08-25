<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Positions and designations on one page, as two tabs.
 *
 * They share a page because an administrator thinks of both as "job titles you
 * assign to people". They stay separate models because they mean different
 * things: a position is the single plantilla post and the source of CORE
 * functions; a designation is an extra assignment an employee may hold several
 * of, and is the source of STRATEGIC and SUPPORT functions.
 */
class JobTitleController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'designations' ? 'designations' : 'positions';

        $positions = Position::query()->orderBy('title')->get();
        $designations = Designation::query()->orderBy('title')->get();

        return view('admin.job-titles.index', compact('tab', 'positions', 'designations'));
    }
}
