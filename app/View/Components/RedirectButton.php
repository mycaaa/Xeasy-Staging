<?php

namespace App\View\Components;

use Illuminate\View\Component;

class RedirectButton extends Component
{
    /**
     * Render the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        // This will use the Blade file that contains the static tab buttons
        return view('components.redirect-button');
    }
}
