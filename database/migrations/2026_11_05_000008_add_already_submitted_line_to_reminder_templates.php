<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tracker #20 (MAIL-01): every reminder mail carries "If already submitted, please ignore this mail."
 * VNR templates 1-6 already say it in other words ("kindly disregard this message"); the
 * resigned-employee reminder and all VVNR templates did not. Templates that already carry
 * an ignore/disregard line are left as they are. Summary mails (hierarchy summary) are skipped.
 *
 *   php artisan migrate --path=database/migrations/2026_11_05_000008_add_already_submitted_line_to_reminder_templates.php
 */
return new class extends Migration
{
    private array $connections = ['mysql', 'expense', 'expense_vvnr', 'vnressus_expense', 'vnressus_expense_new', 'expense_nursery'];

    private const LINE_TEXT = 'If already submitted, please ignore this mail.';
    private const LINE_HTML = '<p style="font-size:12px;color:#6b7280;"><em>If already submitted, please ignore this mail.</em></p>';

    public function up(): void
    {
        foreach ($this->connections() as $conn) {
            $rows = DB::connection($conn)->table('eml_email_templates')->get();
            foreach ($rows as $t) {
                $name = strtolower((string) ($t->name ?? ''));
                if (str_contains($name, 'summary')) {
                    continue;
                }
                $html = (string) ($t->body_html ?? '');
                $hay = strtolower($html.' '.($t->body_text ?? ''));
                if (str_contains($hay, 'ignore this') || str_contains($hay, 'disregard')) {
                    continue;
                }
                $newHtml = stripos($html, '</body>') !== false
                    ? preg_replace('~</body>~i', self::LINE_HTML."\n</body>", $html, 1)
                    : rtrim($html)."\n".self::LINE_HTML;
                $update = ['body_html' => $newHtml];
                if (Schema::connection($conn)->hasColumn('eml_email_templates', 'body_text') && trim((string) ($t->body_text ?? '')) !== '') {
                    $update['body_text'] = rtrim((string) $t->body_text)."\n\n".self::LINE_TEXT;
                }
                DB::connection($conn)->table('eml_email_templates')->where('id', $t->id)->update($update);
                Log::info("MAIL-01: added the 'already submitted' line to template {$t->id} ({$t->name}) on {$conn}");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->connections() as $conn) {
            foreach (DB::connection($conn)->table('eml_email_templates')->get() as $t) {
                $update = ['body_html' => str_replace(["\n".self::LINE_HTML, self::LINE_HTML], '', (string) $t->body_html)];
                if (isset($t->body_text)) {
                    $update['body_text'] = str_replace("\n\n".self::LINE_TEXT, '', (string) $t->body_text);
                }
                DB::connection($conn)->table('eml_email_templates')->where('id', $t->id)->update($update);
            }
        }
    }

    private function connections(): array
    {
        $seen = [];
        $out = [];
        foreach ($this->connections as $conn) {
            $cfg = config("database.connections.{$conn}");
            if (! is_array($cfg) || ($cfg['driver'] ?? '') !== 'mysql') {
                continue;
            }
            $key = ($cfg['host'] ?? '').'|'.($cfg['port'] ?? '').'|'.($cfg['database'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            try {
                if (! Schema::connection($conn)->hasTable('eml_email_templates')) {
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $conn;
        }

        return $out;
    }
};
