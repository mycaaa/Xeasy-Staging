<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * G-08: claim and payment mail only to company addresses. With config('mail.allowed_domains') set, To, Cc
 * and Bcc are cut down to those domains; dropped addresses are logged (domain only), and a mail with no
 * recipient left is cancelled (false stops the send). Empty list: nothing changes.
 */
class MailDomainGuard
{
    public static function allowed(string $address): bool
    {
        $domains = (array) config('mail.allowed_domains', []);
        if (! $domains) {
            return true;
        }
        $d = strtolower(substr(strrchr($address, '@') ?: '', 1));

        return $d !== '' && in_array($d, $domains, true);
    }

    /** @return bool|null false cancels the mail */
    public static function filter($message): ?bool
    {
        if (! $message instanceof Email || ! config('mail.allowed_domains')) {
            return null;
        }
        $dropped = [];
        $keep = function (array $list) use (&$dropped) {
            return array_values(array_filter($list, function (Address $a) use (&$dropped) {
                if (self::allowed($a->getAddress())) {
                    return true;
                }
                $dropped[] = substr(strrchr($a->getAddress(), '@') ?: '@?', 1);

                return false;
            }));
        };
        $to = $keep($message->getTo());
        $cc = $keep($message->getCc());
        $bcc = $keep($message->getBcc());
        if (! $dropped) {
            return null;
        }
        $message->to(...$to);
        $message->cc(...$cc);
        $message->bcc(...$bcc);
        Log::info('G-08: mail recipients outside the company domains dropped', ['subject' => mb_substr((string) $message->getSubject(), 0, 80), 'domains' => array_count_values($dropped)]);
        if (! $to && ! $cc && ! $bcc) {
            return false;
        }
        if (! $to) {
            // keep a valid mail: move the first Cc (or Bcc) to To
            $first = $cc ? array_shift($cc) : array_shift($bcc);
            $message->to($first);
            $message->cc(...$cc);
            $message->bcc(...$bcc);
        }

        return null;
    }
}
