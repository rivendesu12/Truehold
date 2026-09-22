<?php

namespace App\Services;

use Carbon\Carbon;
use setasign\Fpdi\Fpdi;

/**
 * The office's sourcing agreement, filled in.
 *
 * Writes onto the real template (resources/agreements) rather than
 * redrawing it, so the logo, layout and legal text are exactly the document
 * the office already uses. Positions were measured from the template's own
 * text and match the agreements the previous tool produced: Helvetica for
 * the details, Times Italic for the Sourcer's signature. The client signs by
 * hand, so their line stays blank.
 */
class SourcingAgreement
{
    public const REFERRAL_BONUS = 50;

    /** Cash is cheaper than a transfer; the office quotes both. */
    public const FEE_CASH = 220;
    public const FEE_TRANSFER = 250;

    public static function templatePath(): string
    {
        return resource_path('agreements/sourcing-agreement.pdf');
    }

    /**
     * @return string the PDF bytes
     */
    public function make(string $client, float $fee, Carbon $date, string $sourcer, int $referral = self::REFERRAL_BONUS): string
    {
        $pdf = new Fpdi('P', 'pt');
        $pages = $pdf->setSourceFile(self::templatePath());
        $when = $date->format('d/m/Y');

        for ($n = 1; $n <= $pages; $n++) {
            $pdf->AddPage('P', [612, 792]);
            $pdf->useTemplate($pdf->importPage($n), 0, 0, 612, 792);
            $pdf->SetTextColor(0, 0, 0);

            if ($n === 1) {
                $pdf->SetFont('Helvetica', '', 11.25);
                $pdf->Text(124.8, 117.6, $when);

                // Centred under "Client (Individually and collectively...)".
                $pdf->SetFont('Helvetica', '', 12);
                $name = $this->text($client);
                $pdf->Text(306 - $pdf->GetStringWidth($name) / 2, 256.5, $name);
            }

            if ($n === 2) {
                $pdf->SetFont('Helvetica', '', 9.4);
                $pdf->Text(344.4, 440.5, $this->money($fee));
                $pdf->Text(229.0, 463.3, (string) $referral);
            }

            if ($n === $pages) {
                $pdf->SetFont('Helvetica', '', 9.4);
                $pdf->Text(119.0, 600.0, $when);

                $pdf->SetFont('Times', 'I', 13);
                $pdf->Text(163.0, 714.0, $this->text($sourcer));
            }
        }

        return $pdf->Output('S');
    }

    /** The template as it is, for anyone who only wants the blank. */
    public function blank(): string
    {
        return file_get_contents(self::templatePath());
    }

    public static function filename(?string $client = null): string
    {
        // Accents to plain letters first: "María José" becomes "Maria Jose", not "Mar a Jos".
        $who = $client ? preg_replace('/[^A-Za-z0-9]+/', ' ', \Illuminate\Support\Str::ascii($client)) : 'blank';

        return 'Sourcing Agreement - ' . trim($who) . '.pdf';
    }

    /** The PDF core fonts are Windows-1252: keep accents, drop what cannot print. */
    private function text(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));

        return (string) (iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value) ?: $value);
    }

    private function money(float $fee): string
    {
        return floor($fee) == $fee ? number_format($fee, 0, '.', ',') : number_format($fee, 2, '.', ',');
    }
}
