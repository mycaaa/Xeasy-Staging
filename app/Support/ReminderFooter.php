<?php

namespace App\Support;

/**
 * MAIL-01b (Finance 5 Nov: "check & standardise"): every reminder mail, VNR and VVNR, ends with
 * the same "already done" line. Older wordings ("kindly disregard this message", the MAIL-01 line)
 * are taken out first so a mail never carries two. Summary mails are left alone.
 * The wording lives in config('mail_reminders.already_done_line').
 */
class ReminderFooter
{
    private const OLD_WORDINGS = [
        '~<p[^>]*>\s*(?:<em>)?\s*If already submitted, please ignore this mail\.\s*(?:</em>)?\s*</p>~i',
        '~If the required action has already been completed, kindly disregard this message\.~i',
        '~If already submitted, please ignore this mail\.~i',
    ];

    public static function applies(?string $subject): bool
    {
        return (bool) config('mail_reminders.standard_line_enabled', true)
            && stripos((string) $subject, 'summary') === false;
    }

    public static function html(string $html): string
    {
        $line = (string) config('mail_reminders.already_done_line');
        if ($line === '' || stripos($html, $line) !== false) {
            return $html;
        }
        foreach (self::OLD_WORDINGS as $re) {
            $html = preg_replace($re, '', $html);
        }
        $p = '<p style="font-size:12px;color:#6b7280;margin-top:14px;"><em>'.e($line).'</em></p>';

        return stripos($html, '</body>') !== false
            ? preg_replace('~</body>~i', $p."\n</body>", $html, 1)
            : rtrim($html)."\n".$p;
    }

    public static function text(string $text): string
    {
        $line = (string) config('mail_reminders.already_done_line');
        if ($line === '' || stripos($text, $line) !== false) {
            return $text;
        }
        foreach (array_slice(self::OLD_WORDINGS, 1) as $re) {
            $text = preg_replace($re, '', $text);
        }

        return rtrim($text)."\n\n".$line;
    }
}
