<?php

namespace App\Console\Commands;

use App\Support\PrivateExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * FILES-01 (UAT-03): moves files written before the fix out of the public folders:
 *   public/uploads/pax_documents/**   -> storage/app/private/pax_documents/**
 *   storage/app/public/temp/*.xlsx|csv -> private_exports disk temp/
 * Old links keep working through the authorised routes. --back moves them back (undo).
 */
class MakeFilesPrivate extends Command
{
    protected $signature = 'files:make-private {--dry-run : list what would move} {--back : undo, move the files back to the public folders}';

    protected $description = 'Move Pax documents and claim-stage exports from public to private storage (FILES-01)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $back = (bool) $this->option('back');
        $moved = 0;

        $pairs = [
            [public_path('uploads/pax_documents'), storage_path('app/private/pax_documents'), '/.+/'],
            [Storage::disk('public')->path('temp'), Storage::disk(PrivateExport::DISK)->path('temp'), '/\.(xlsx|xls|csv)$/i'],
        ];

        foreach ($pairs as [$public, $private, $pattern]) {
            [$from, $to] = $back ? [$private, $public] : [$public, $private];
            if (! is_dir($from)) {
                $this->line("skip (no folder): {$from}");
                continue;
            }
            foreach (File::allFiles($from) as $file) {
                if (! preg_match($pattern, $file->getFilename())) {
                    continue;
                }
                $target = $to.'/'.$file->getRelativePathname();
                $this->line(($dry ? '[dry] ' : '').$file->getPathname().' -> '.$target);
                if (! $dry) {
                    File::ensureDirectoryExists(dirname($target), 0750);
                    File::move($file->getPathname(), $target);
                }
                $moved++;
            }
        }
        $this->info(($dry ? 'would move ' : 'moved ').$moved.' file(s)');

        return self::SUCCESS;
    }
}
