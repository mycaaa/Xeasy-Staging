<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use App\Models\BtcCaseFile;
use App\Models\BtcCaseLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BOARDING-04 (17 Nov 2026): many boarding passes at once (a whole folder or many files). Each file is matched to an
 * open air booking by its file name: the booking's PNR, or its BTC number (e.g. 00013 or BTC-26-00013). A file that
 * matches no booking, or more than one, is not saved and is listed for the user to upload one by one.
 */
class BtcBoardingBulk
{
    /**
     * @param  Collection<BtcCase>  $cases  open air bookings the uploader may fill
     * @param  UploadedFile[]  $files
     * @return array<int, array{file:string, case:?string, note:string, ok:bool}>
     */
    public static function upload(Collection $cases, array $files, string $by, ?int $userId, string $via): array
    {
        $out = [];
        foreach ($files as $f) {
            if (! $f instanceof UploadedFile || ! $f->isValid()) {
                continue;
            }
            $name = $f->getClientOriginalName();
            $ext = strtolower($f->getClientOriginalExtension());
            if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) || $f->getSize() > 5 * 1024 * 1024) {
                $out[] = ['file' => $name, 'case' => null, 'note' => 'Skipped: only PDF / JPG / PNG up to 5 MB', 'ok' => false];
                continue;
            }
            $stem = strtoupper(preg_replace('/[^A-Za-z0-9]/', ' ', pathinfo($name, PATHINFO_FILENAME)));
            $flat = str_replace(' ', '', $stem);
            $hits = $cases->filter(function ($c) use ($stem, $flat) {
                $pnr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c->pnr));
                if (strlen($pnr) >= 5 && str_contains($flat, $pnr)) {
                    return true;
                }
                $no = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c->case_no));   // BTC2600013
                $tail = substr($no, -5);                                                       // 00013

                return str_contains($flat, $no) || in_array($tail, preg_split('/\s+/', trim($stem)), true);
            });
            if ($hits->count() !== 1) {
                $out[] = ['file' => $name, 'case' => null, 'note' => $hits->isEmpty() ? 'No booking found: name the file with the PNR or the BTC number' : 'Matches more than one booking ('.$hits->pluck('case_no')->implode(', ').')', 'ok' => false];
                continue;
            }
            $c = $hits->first();
            $path = $f->storeAs("btc/{$c->company_id}/{$c->id}", 'boarding_pass_bulk_'.now()->format('YmdHis').'_'.substr(md5($name), 0, 6).'.'.$ext, 'local');
            DB::transaction(function () use ($c, $f, $path, $name, $by, $userId, $via) {
                BtcCaseFile::create(['btc_case_id' => $c->id, 'kind' => 'boarding_pass', 'file_path' => $path, 'file_name' => mb_substr($name, 0, 255),
                    'mime' => $f->getMimeType(), 'uploaded_by' => $userId]);
                $first = ! $c->boarding_pass_received_on;
                if ($first) {
                    $c->boarding_pass_received_on = now()->toDateString();
                    $c->save();
                }
                BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'boarding_pass', 'from_status' => $c->status, 'to_status' => $c->status,
                    'remark' => 'Boarding pass '.($first ? '' : '(another file) ').'from a bulk upload ('.$via.'): '.mb_substr($name, 0, 120), 'user_id' => $userId, 'user_name' => mb_substr($by, 0, 150)]);
            });
            $out[] = ['file' => $name, 'case' => $c->case_no, 'note' => 'Saved for '.$c->case_no.' ('.$c->emp_name.', PNR '.($c->pnr ?: '-').')', 'ok' => true];
        }

        return $out;
    }
}
