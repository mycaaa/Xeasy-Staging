<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function airportList()
    {
        $airport = Airport::all();
        return $this->jsonSuccess($airport, 'Airport fetched successfully.');
    }
}
