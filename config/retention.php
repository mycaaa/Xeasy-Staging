<?php

/*
| RET-01 (Finance decision 7 Nov 2026): claim records and bills are kept financial-year-wise for at
| least 8 years after the year ends (legal and compliance). Years that ended more than 4 years ago
| are archived: read-only, still visible and searchable, and copied to an archive file set
| (records:archive). Nothing is deleted by the system; a year may be removed only after its
| keep-until date, by a person, outside Xeasy.
*/
return [
    'keep_years' => (int) env('RETENTION_KEEP_YEARS', 8),
    'archive_after_years' => (int) env('RETENTION_ARCHIVE_AFTER_YEARS', 4),
    'archive_disk' => env('RETENTION_ARCHIVE_DISK', 'local'),
    'archive_path' => env('RETENTION_ARCHIVE_PATH', 'archive'),
];
