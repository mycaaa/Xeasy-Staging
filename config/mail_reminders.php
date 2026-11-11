<?php

return [
    // MAIL-01b: one standard closing line on every VNR and VVNR reminder mail (not on summaries).
    'standard_line_enabled' => env('MAIL_REMINDER_STANDARD_LINE', true),
    'already_done_line' => env('MAIL_REMINDER_ALREADY_DONE_LINE', 'If you have already completed this (submitted, sent the hard copy or approved), please ignore this mail.'),
];
