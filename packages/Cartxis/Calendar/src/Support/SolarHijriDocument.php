<?php

namespace Cartxis\Calendar\Support;

use DateTimeInterface;

/**
 * Dates for a PDF or an email, where there is no browser to do the work.
 *
 * ------------------------------------------------------------------------
 * WHY THIS EXISTS SEPARATELY
 * ------------------------------------------------------------------------
 * The Vue admin gets its dates formatted in the browser, from the JavaScript
 * twin of this engine. A PDF does not. mPDF and Dompdf render plain HTML with
 * no JavaScript at all, so an invoice has to be given its dates as finished
 * text by PHP -- and if that text is produced by a different code path from the
 * one the admin screen uses, an invoice and the screen behind it will
 * eventually disagree by a day. They cannot, if both call here.
 *
 * So every method returns a string ready to drop into HTML. Nothing here
 * changes a stored value: the invoice still holds a Gregorian date, and
 * issue_date / due_date columns are still Gregorian. This only decides what
 * the PDF says about them.
 *
 * ------------------------------------------------------------------------
 * THE FONT PROBLEM, WHICH IS NOT OPTIONAL
 * ------------------------------------------------------------------------
 * mPDF cannot draw Persian-Arabic script unless a font carrying it is
 * registered. Without one it prints a row of empty boxes -- silently, and it
 * still produces a valid PDF, so this is only noticed by a customer.
 *
 * This class does not choose a font, because that is a document decision and
 * fonts are packaged per deployment. Use fonts() to tell mPDF which one to use
 * and wire it into the PDF service; see the package README for the exact call.
 */
final class SolarHijriDocument
{
    /**
     * A date for a line in a document: '۱۵ شهریور ۱۴۰۴ (6 September 2025)'.
     *
     * The default pairing, identical to what the admin screen shows for the
     * same date.
     */
    public static function date(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
    ): string {
        return SolarHijriFormatter::format($date, $locale);
    }

    /**
     * A date in a sentence, with the weekday:
     * 'شنبه، ۱۵ شهریور ۱۴۰۴ (Saturday, 6 September 2025)'.
     *
     * For the prose around a date -- 'Issued on ...', 'Payment due by ...'.
     */
    public static function dateInSentence(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
    ): string {
        return SolarHijriFormatter::long($date, $locale);
    }

    /**
     * A date for a table column: '۱۴۰۴/۰۶/۱۵ (2025/09/06)'.
     *
     * Narrow, so a row of order lines still fits on an A4 page, and still both
     * calendars.
     */
    public static function tableDate(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
    ): string {
        return SolarHijriFormatter::short($date, $locale);
    }

    /**
     * A date range as '۱ تا ۳۱ سنبله ۱۴۰۴ (1 to 31 Shahrivar 1404)'.
     *
     * Both ends are converted independently, so a range that crosses a Solar
     * New Year prints two different years rather than pretending both ends are
     * in the same one -- which is the case a hand-rolled conversion gets wrong,
     * and the case a report filter quietly drops days from.
     */
    public static function dateRange(
        DateTimeInterface|string|int $from,
        DateTimeInterface|string|int $to,
        ?string $locale = null,
    ): string {
        $start = SolarHijri::fromGregorian($from);
        $end = SolarHijri::fromGregorian($to);

        return self::solarRange($start, $end, $locale)
            .' ('.self::gregorianRange($from, $to).')';
    }

    /**
     * The Solar Hijri half of a range.
     *
     * Shares the month and the year when the two ends are in the same one, so
     * the common case reads '۱ تا ۳۱ سنبله ۱۴۰۴' rather than repeating them.
     */
    private static function solarRange(SolarHijriDate $start, SolarHijriDate $end, ?string $locale): string
    {
        $separated = static fn (string $text): string => PersianDigits::to($text);

        if ($start->equals($end)) {
            return $separated($start->day).' '
                .SolarHijriLocale::monthName($start->month, $locale).' '
                .$separated((string) $start->year);
        }

        if ($start->year === $end->year && $start->month === $end->month) {
            return $separated($start->day).' '.$separated('تا').' '.$separated($end->day).' '
                .SolarHijriLocale::monthName($start->month, $locale).' '
                .$separated((string) $start->year);
        }

        if ($start->year === $end->year) {
            return $separated($start->day).' '.SolarHijriLocale::monthName($start->month, $locale).' '
                .$separated($start->year).' – '
                .$separated($end->day).' '.SolarHijriLocale::monthName($end->month, $locale).' '
                .$separated($end->year);
        }

        return $separated($start->day).' '.SolarHijriLocale::monthName($start->month, $locale).' '
            .$separated($start->year).' – '
            .$separated($end->day).' '.SolarHijriLocale::monthName($end->month, $locale).' '
            .$separated($end->year);
    }

    /**
     * The Gregorian half of a range, always in full so it stays unambiguous.
     */
    private static function gregorianRange(
        DateTimeInterface|string|int $from,
        DateTimeInterface|string|int $to,
    ): string {
        return SolarHijri::fromGregorian($from)->toGregorian()->format('j F Y')
            .' – '
            .SolarHijri::fromGregorian($to)->toGregorian()->format('j F Y');
    }

    /**
     * 'Issue date', 'due date' and every other printed date on an invoice, in
     * one array -- so a PDF cannot end up formatting some of its dates one way
     * and the rest another.
     *
     * @param  array<string, DateTimeInterface|string|int|null>  $dates  label => date
     * @return array<string, string>                            label => printed date
     */
    public static function dates(array $dates, ?string $locale = null): array
    {
        $printed = [];

        foreach ($dates as $label => $value) {
            $printed[$label] = $value === null ? '' : self::date($value, $locale);
        }

        return $printed;
    }

    /**
     * Tell mPDF which font to use, given one that carries Persian-Arabic
     * script. Pass the result to mPDF's AddFont() as well as its SetFont().
     *
     * mPDF needs the font file on disk, so this is deliberately a plain array
     * of paths rather than something clever: the caller knows where its fonts
     * live, and an engine that guessed would break the first time the store
     * changed theme.
     *
     * @return array{0: string, 1: array} the font family name and mPDF's
     *                                    font definition array
     */
    public static function fonts(string $fontFile, string $family = 'notosansarabic'): array
    {
        return [$family, [
            'R' => $fontFile,   // Regular
            'B' => $fontFile,   // Bold -- point it at the bold face if you have one
            'I' => $fontFile,   // Italic
            'BI' => $fontFile,
        ]];
    }

    /**
     * The stylesheet a PDF needs for Persian-Arabic text to come out right.
     *
     * Two things break Persian-Arabic in a PDF and neither announces itself:
     * the text comes out unshaped and unjoined, or it comes out right-to-left
     * and then the surrounding LTR layout runs off the page. The first is the
     * font's job; the second is a `dir` attribute, which mPDF honours.
     *
     * Included so a PDF gets the direction right without every one of them
     * rediscovering it.
     */
    public static function stylesheet(): string
    {
        return <<<'CSS'
            .solar-date { direction: rtl; unicode-bidi: embed; font-family: 'NotoSansArabic', 'DejaVu Sans', sans-serif; }
            .solar-date--gregorian { direction: ltr; unicode-bidi: embed; }
            .solar-date__secondary { font-size: 0.85em; opacity: 0.75; }
            table { border-collapse: collapse; }
            table td, table th { padding: 4px 8px; text-align: right; }
            CSS;
    }

    /**
     * Wrap a printed date in the markup that keeps it readable in a PDF.
     *
     * The Solar Hijri part is marked RTL and the bracketed Gregorian reference
     * LTR, so a document containing both reads correctly without the reference
     * dragging the rest of the line sideways.
     */
    public static function html(
        DateTimeInterface|string|int|null $date = null,
        ?string $locale = null,
    ): string {
        $parts = solar_hijri_parts($date, $locale);

        $html = sprintf(
            '<span class="solar-date" dir="rtl">%s</span>',
            e($parts['primary']),
        );

        if ($parts['secondary'] !== null) {
            $html .= sprintf(
                ' <span class="solar-date solar-date--gregorian solar-date__secondary" dir="ltr">(%s)</span>',
                e($parts['secondary']),
            );
        }

        return $html;
    }
}
