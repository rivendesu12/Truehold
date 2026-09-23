<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Turns a raw "Tenant enquiry from X via Zoopla" email into a lead.
 *
 * Zoopla sends multipart/alternative. The plain part has the applicant as
 * "Label: value" lines (name, email, telephone, address, the advert link and
 * our own property ref); the price and Zoopla's listing reference are only in
 * the HTML part. Both are read and the plain part wins where they overlap.
 */
class ZooplaLeadParser
{
    /** @return array<string, mixed>|null null when the email is not an enquiry or holds no contact. */
    public static function parse(string $raw): ?array
    {
        [$headers, $plain, $html] = self::split($raw);

        $subject = $headers['subject'] ?? '';
        if (! preg_match('/enquiry from (.+?) via Zoopla/i', $subject, $subjectName)) {
            return null;
        }

        $htmlText = self::htmlToText($html);

        $name = self::field($plain, $htmlText, ['Name'])
            ?? self::replyToName($headers['reply-to'] ?? '')
            ?? trim($subjectName[1]);
        $phone = self::field($plain, $htmlText, ['Telephone number', 'Telephone', 'Phone']);
        $email = self::field($plain, $htmlText, ['Email address', 'Email']);

        if (! $phone && ! $email) {
            return null;
        }

        $address = self::field($plain, $htmlText, ['Full address', 'Address']);
        [$first, $surname] = self::splitName($name);

        preg_match('#https?://(?:www\.)?zoopla\.co\.uk/[a-z-]+/details/\d+/?#i', $plain . "\n" . $html, $link);
        preg_match('/\blisting_\d+_\d+\b/', $htmlText . "\n" . $plain, $listingRef);
        // Our own ref for the advert, set when it was posted. Same line only:
        // when blank, the next line is the advert link.
        preg_match('/^[ \t]*Your property ref:[ \t]*(\S[^\n]*)$/mi', $plain, $propertyRef);

        return [
            'name' => $name,
            'first_name' => $first,
            'surname' => $surname,
            'phone' => $phone,
            'phone_intl' => self::internationalPhone($phone),
            'email' => $email ? strtolower($email) : null,
            'enquiry_type' => self::field($plain, $htmlText, ['Type of enquiry']),
            'address' => $address,
            'area' => self::area($address),
            'price' => self::monthlyPrice($htmlText . "\n" . $plain),
            'property_link' => isset($link[0]) ? str_replace('https://', 'http://', rtrim($link[0], '/') . '/') : null,
            'property_ref' => isset($propertyRef[1]) ? trim($propertyRef[1]) : null,
            'listing_ref' => $listingRef[0] ?? null,
            'received_at' => self::date($headers['date'] ?? null),
            'message_id' => trim($headers['message-id'] ?? '', '<> ') ?: null,
        ];
    }

    /** 07340 085125 → 447340085125, the form the CRM import takes. */
    public static function internationalPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return match (true) {
            $digits === '' => null,
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '44') => $digits,
            str_starts_with($digits, '0') => '44' . substr($digits, 1),
            default => $digits,
        };
    }

    /** "Smart Street, Bethnal Green E2" → "Bethnal Green". */
    public static function area(?string $address): ?string
    {
        if (! $address) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        $last = end($parts) ?: '';
        // Drop a trailing postcode or district (E2, E14 7AB, SW1A 1AA).
        $area = trim(preg_replace('/\s+[A-Z]{1,2}\d[A-Z\d]?(\s*\d[A-Z]{2})?$/i', '', $last));

        if (($area === '' || preg_match('/^[A-Z]{1,2}\d/i', $area)) && count($parts) > 1) {
            $area = $parts[count($parts) - 2];
        }

        return $area !== '' ? $area : null;
    }

    /** Headers, decoded plain part, decoded HTML part. */
    public static function split(string $raw): array
    {
        [$headers, $body] = self::headersAndBody($raw);
        $plain = '';
        $html = '';

        self::walk($headers, $body, $plain, $html);

        return [$headers, $plain, $html];
    }

    private static function walk(array $headers, string $body, string &$plain, string &$html): void
    {
        $type = strtolower($headers['content-type'] ?? 'text/plain');

        if (str_starts_with($type, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $headers['content-type'], $b)) {
            $sections = explode('--' . $b[1], $body);
            // First is the preamble, last the closing "--" epilogue.
            foreach (array_slice($sections, 1) as $section) {
                if (str_starts_with($section, '--')) {
                    break;
                }
                [$h, $partBody] = self::headersAndBody(ltrim($section, "\n"));
                self::walk($h, $partBody, $plain, $html);
            }

            return;
        }

        $decoded = self::decodeBody($body, strtolower($headers['content-transfer-encoding'] ?? '7bit'), $type);

        if (str_starts_with($type, 'text/html')) {
            $html .= $decoded;
        } elseif (str_starts_with($type, 'text/plain')) {
            $plain .= $decoded;
        }
    }

    private static function decodeBody(string $body, string $encoding, string $type): string
    {
        $text = match (true) {
            $encoding === 'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body)),
            $encoding === 'quoted-printable' => quoted_printable_decode($body),
            // Zoopla labels its plain part 7bit but writes it quoted-printable
            // ("=20", soft "=" line breaks), so decode it when it looks it.
            (bool) preg_match('/=\n|=[0-9A-F]{2}/', $body) => quoted_printable_decode($body),
            default => $body,
        };

        if (preg_match('/charset="?([\w-]+)/i', $type, $c) && ! in_array(strtolower($c[1]), ['utf-8', 'us-ascii'], true)) {
            $text = mb_convert_encoding($text, 'UTF-8', $c[1]);
        }

        return $text;
    }

    /** @return array{0: array<string, string>, 1: string} lower-cased headers (first wins), body */
    private static function headersAndBody(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$head, $body] = array_pad(explode("\n\n", $raw, 2), 2, '');
        $head = preg_replace('/\n[ \t]+/', ' ', $head);

        $headers = [];
        foreach (explode("\n", $head) as $line) {
            if (preg_match('/^([\w-]+):\s*(.*)$/', $line, $m)) {
                $key = strtolower($m[1]);
                $headers[$key] ??= self::decodeHeader($m[2]);
            }
        }

        return [$headers, $body];
    }

    private static function decodeHeader(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return trim($value);
        }

        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return trim($decoded !== false ? $decoded : $value);
    }

    private static function htmlToText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(style|script|head)\b.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>|</(p|div|td|th|tr|li|h\d|table)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return implode("\n", array_filter(array_map(
            fn ($line) => trim(preg_replace('/[ \t]+/', ' ', $line)),
            explode("\n", $text)
        ), fn ($line) => $line !== ''));
    }

    /**
     * "Label: value" from the plain part; else from the HTML text, where the
     * label and the value sit in separate cells and so on separate lines.
     */
    private static function field(string $plain, string $htmlText, array $labels): ?string
    {
        foreach ($labels as $label) {
            $l = preg_quote($label, '/');
            if (preg_match('/^[ \t]*' . $l . ':[ \t]*(\S[^\n]*)$/mi', $plain, $m)) {
                return trim($m[1]);
            }
            if (preg_match('/^' . $l . ':[ \t]*\n?([^\n:]+)$/mi', $htmlText, $m) && trim($m[1]) !== '') {
                return trim($m[1]);
            }
        }

        return null;
    }

    private static function replyToName(string $replyTo): ?string
    {
        return preg_match('/^"?([^"<]+?)"?\s*</', $replyTo, $m) ? trim($m[1]) : null;
    }

    /** @return array{0: string, 1: string} */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /** "£820 pcm" → 820; "£200 pw" → 867. */
    private static function monthlyPrice(string $text): ?int
    {
        if (! preg_match('/£\s?([\d,]+(?:\.\d+)?)\s*(pcm|pw|per month|per week|p\/w|p\/m)/i', $text, $m)) {
            return null;
        }

        $amount = (float) str_replace(',', '', $m[1]);
        $weekly = in_array(strtolower($m[2]), ['pw', 'per week', 'p/w'], true);

        return (int) round($weekly ? $amount * 52 / 12 : $amount);
    }

    private static function date(?string $header): CarbonImmutable
    {
        try {
            return $header
                ? CarbonImmutable::parse(preg_replace('/\s*\(.*\)$/', '', $header))->setTimezone('Europe/London')
                : CarbonImmutable::now('Europe/London');
        } catch (\Throwable) {
            return CarbonImmutable::now('Europe/London');
        }
    }
}
