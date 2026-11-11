<?php

namespace App\View\Components\action;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ApproverBtn extends Component
{
    public $prefix;
    public $status;

    public function __construct($prefix, $status)
    {
        $this->prefix = $prefix;
        $this->status = $status;
    }

    public function render()
    {
        return view('components.action.approver-btn');
    }
}
