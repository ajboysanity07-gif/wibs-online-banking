<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Principal/interest split for core-banking ledger rows.
 *
 * Production wlnled rows carry the payment total in `payments` but (almost)
 * always store 0 in `principal` and have no `accruedint` column at all, so
 * the split is derived from the Amortsched installment covering each
 * payment: real posted values win whenever they are non-zero, otherwise the
 * schedule's interest portion is used and principal is backed out as
 * `payments - interest`. Genuinely unknown halves stay null so the UI can
 * render a dash instead of fabricating ₱0.00.
 */
final class LoanPaymentSplit
{
    /**
     * @param  iterable<mixed>  $scheduleRows  Amortsched rows (Date_pay, Interest).
     * @return array{principal: ?float, interest: ?float}
     */
    public static function resolve(object $row, iterable $scheduleRows = []): array
    {
        $rawPrincipal = self::number($row->principal ?? null);
        $rawInterest = self::number($row->accruedint ?? null);
        $payments = self::number($row->payments ?? null);

        $interest = $rawInterest !== null && $rawInterest != 0.0
            ? $rawInterest
            : self::interestForDate($scheduleRows, $row->date_in ?? null);

        if ($rawPrincipal !== null && $rawPrincipal != 0.0) {
            $principal = $rawPrincipal;
        } elseif ($interest !== null && $payments !== null) {
            $principal = max(0.0, round($payments - $interest, 2));
        } else {
            $principal = null;
        }

        return ['principal' => $principal, 'interest' => $interest];
    }

    /**
     * Interest portion of the schedule installment covering a payment: the row
     * with the latest Date_pay on or before the payment date.
     *
     * @param  iterable<mixed>  $scheduleRows
     */
    public static function interestForDate(iterable $scheduleRows, mixed $date): ?float
    {
        $when = self::toDate($date);

        if ($when === null) {
            return null;
        }

        $best = null;
        $bestDate = null;

        foreach ($scheduleRows as $row) {
            $rowDate = self::toDate($row->Date_pay ?? null);

            if ($rowDate === null || $rowDate->greaterThan($when)) {
                continue;
            }

            if ($bestDate === null || $rowDate->greaterThan($bestDate)) {
                $bestDate = $rowDate;
                $best = self::number($row->Interest ?? null);
            }
        }

        return $best;
    }

    /**
     * Group rows by trimmed loan number. Legacy numbers are space-padded and
     * SQL Server matches them loosely, but PHP array/collection lookups need
     * the trimmed key.
     *
     * @param  iterable<mixed>  $rows
     * @return array<string, list<mixed>>
     */
    public static function indexRowsByLoan(iterable $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $key = is_array($row) ? ($row['lnnumber'] ?? '') : ($row->lnnumber ?? '');
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $grouped[$key][] = $row;
        }

        return $grouped;
    }

    /**
     * Attach `split_principal` / `split_interest` to every payment row, using
     * each row's own loan schedule for the derivation.
     *
     * @param  Collection<int, mixed>  $payments  Wlnled rows.
     * @param  Collection<int, mixed>  $schedules  Amortsched rows.
     */
    public static function attachResolved(Collection $payments, Collection $schedules): void
    {
        $byLoan = self::indexRowsByLoan($schedules);

        foreach ($payments as $payment) {
            $key = trim((string) ($payment->lnnumber ?? ''));
            $split = self::resolve($payment, $byLoan[$key] ?? []);

            if (method_exists($payment, 'setAttribute')) {
                $payment->setAttribute('split_principal', $split['principal']);
                $payment->setAttribute('split_interest', $split['interest']);
            }
        }
    }

    /**
     * Read a resolved split value: the attached `split_*` attribute wins when
     * present (even when null, so unknown halves render as a dash), otherwise
     * the raw ledger column is used.
     */
    public static function read(mixed $row, string $splitKey, string $rawKey): mixed
    {
        if ($row instanceof Model) {
            $attributes = $row->getAttributes();

            if (array_key_exists($splitKey, $attributes)) {
                return $attributes[$splitKey];
            }

            return $row->getAttribute($rawKey);
        }

        if (is_array($row)) {
            if (array_key_exists($splitKey, $row)) {
                return $row[$splitKey];
            }

            return $row[$rawKey] ?? null;
        }

        if (is_object($row)) {
            $vars = get_object_vars($row);

            if (array_key_exists($splitKey, $vars)) {
                return $vars[$splitKey];
            }

            return $row->{$rawKey} ?? null;
        }

        return null;
    }

    private static function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private static function toDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
