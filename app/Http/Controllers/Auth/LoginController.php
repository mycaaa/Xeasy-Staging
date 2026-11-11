<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use App\Models\FinancialYear;
use App\Models\CoreCompany; 

class LoginController extends Controller
{
    use AuthenticatesUsers;
    protected $redirectTo = '/home';
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }
    public function showLoginForm()
    {
        return view('auth.login');
    }
}
