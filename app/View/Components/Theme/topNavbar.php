<?php

namespace App\View\Components\Theme;

use App\Helpers\DatabaseSwitcher;
use App\Models\FinancialYear;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\Support\Facades\DB;

class topNavbar extends Component
{
    public $vnrFinancialYears;
    public $vvnrFinancialYears;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        // Get financial years for both companies using direct DB queries to avoid connection issues
        try {
            // Get VNR financial years using direct connection
            $this->vnrFinancialYears = DB::connection(session('expense_connection'))
                ->table('financialyear')
                ->orderBy('YearId', 'desc')
                ->get(['YearId', 'Year']);
            
            // Get VVNR financial years using direct connection
            $this->vvnrFinancialYears = DB::connection('expense_vvnr')
                ->table('financialyear')
                ->orderBy('YearId', 'desc')
                ->get(['YearId', 'Year']);
                
            // RET-01: mark archived (read-only) years in the year picker
            foreach (['vnrFinancialYears' => (string) session('expense_connection'), 'vvnrFinancialYears' => 'expense_vvnr'] as $prop => $c) {
                $this->{$prop} = $this->{$prop}->map(function ($y) use ($c) {
                    if (\App\Services\Retention\RecordRetention::isArchived($c, (int) $y->YearId)) {
                        $y->Year .= ' (archived, read-only)';
                    }

                    return $y;
                });
            }
        } catch (\Exception $e) {
            // Fallback to empty collections if database queries fail
            $this->vnrFinancialYears = collect();
            $this->vvnrFinancialYears = collect();
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.theme.topNavbar');
    }
}
