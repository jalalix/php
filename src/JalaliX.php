<?php

declare(strict_types=1);

namespace Jalalix;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Jalali (Persian / Shamsi) calendar on top of a millisecond timestamp.
 * Pure PHP port of @jalalix/typescript — same algorithm and camelCase API.
 */
class JalaliX
{
    /** @var array{year: int, month: int, day: int, hours: int, min: int, sec: int, ms: int, isLeapYear: bool} */
    private array $jalali;

    private bool $isConverted = false;
    private float $leapYearDivision = 0.24219858156;
    private int $firstDayOfWeek = 6;
    private int $dateLength = 86400000;

    /** @var list<string> */
    private array $weekDaysNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه شنبه', 'چهار شنبه', 'پنج شنبه', 'جمعه'];

    /** @var list<string> */
    private array $weekDaysLetters = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    /** @var list<string> */
    private array $weekDaysGregorianNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** @var list<string> */
    private array $weekDaysGregorianLetters = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    /** @var list<string> */
    private array $monthsNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** @var list<string> */
    private array $monthsGregorianNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /** @var list<string> */
    public static array $persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** @var list<string> */
    public static array $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private string $am = 'ق.ظ.';
    private string $pm = 'ب.ظ.';
    private string $amGregorian = 'AM';
    private string $pmGregorian = 'PM';

    /** UTC milliseconds since Unix epoch (like Date#getTime()). */
    private int $timestamp;

    /** Active IANA zone; default UTC for portable civil dates. */
    private DateTimeZone $timezone;

    /**
     * Creates a JalaliX instance from a date value (defaults to now).
     * @param int|float|string|\DateTimeInterface|self|null $date The date value (timestamp, ISO string, DateTime, or JalaliX).
     * @return void
     */
    public function __construct(int|float|string|DateTimeInterface|self|null $date = null)
    {
        $this->timezone = new DateTimeZone('UTC');
        $this->timestamp = $this->resolveTimestamp($date);

        $dt = $this->dateTime();

        $this->jalali = [
            'year' => 1348,
            'month' => 10,
            'day' => 11,
            'hours' => (int) $dt->format('G'),
            'min' => (int) $dt->format('i'),
            'sec' => (int) $dt->format('s'),
            'ms' => (int) $dt->format('v'),
            'isLeapYear' => false,
        ];
    }

    /**
     * Converts the current Date to Jalali (Persian) calendar date.
     * @return array{year: int, month: int, day: int, hours: int, min: int, sec: int, ms: int, isLeapYear: bool} The Jalali date object with year, month, day, hours, minutes, seconds, milliseconds, and isLeapYear.
     */
    private function toJalali(): array
    {
        if (!$this->isConverted) {
            // Civil day walk uses wall time in the active timezone (instant-preserving).
            $wall = $this->wallMs();
            $diffDays = (int) floor($wall / $this->dateLength);

            $this->jalali['year'] = 1348;
            $this->jalali['month'] = 10;
            $this->jalali['day'] = 11;

            if ($diffDays > 0) {
                for ($i = 0; $i < $diffDays; $i++) {
                    $this->jalali['day']++;

                    if ($this->jalali['month'] < 7 && $this->jalali['day'] > 31) {
                        $this->jalali['month']++;
                        $this->jalali['day'] = 1;
                    } elseif ($this->jalali['month'] > 6 && $this->jalali['month'] < 12 && $this->jalali['day'] > 30) {
                        $this->jalali['month']++;
                        $this->jalali['day'] = 1;
                    } elseif ($this->jalali['month'] === 12) {
                        $isLeapYear = $this->checkLeapYear($this->jalali['year']);

                        if ($isLeapYear && $this->jalali['day'] > 30) {
                            $this->jalali['year']++;
                            $this->jalali['month'] = 1;
                            $this->jalali['day'] = 1;
                        } elseif (!$isLeapYear && $this->jalali['day'] > 29) {
                            $this->jalali['year']++;
                            $this->jalali['month'] = 1;
                            $this->jalali['day'] = 1;
                        }
                    }
                }
            } elseif ($diffDays < 0) {
                for ($i = 0; $i < -1 * $diffDays; $i++) {
                    $this->jalali['day']--;

                    if ($this->jalali['day'] < 1) {
                        if ($this->jalali['month'] === 1) {
                            $this->jalali['year']--;
                            $this->jalali['month'] = 12;

                            $isLeapYear = $this->checkLeapYear($this->jalali['year']);
                            $this->jalali['day'] = $isLeapYear ? 30 : 29;
                        } elseif ($this->jalali['month'] < 8) {
                            $this->jalali['month']--;
                            $this->jalali['day'] = 31;
                        } elseif ($this->jalali['month'] < 13) {
                            $this->jalali['month']--;
                            $this->jalali['day'] = 30;
                        }
                    }
                }
            }

            $dt = $this->dateTime();
            $this->jalali['hours'] = (int) $dt->format('G');
            $this->jalali['min'] = (int) $dt->format('i');
            $this->jalali['sec'] = (int) $dt->format('s');
            $this->jalali['ms'] = (int) $dt->format('v');
            $this->jalali['isLeapYear'] = $this->checkLeapYear($this->jalali['year']);
            $this->isConverted = true;
        }

        return $this->jalali;
    }

    /**
     * Converts a Jalali date to Gregorian timestamp.
     * @param array{year: int, month: int, day: int, hours?: int, min?: int, sec?: int, ms?: int, isLeapYear?: bool} $jDate The Jalali date object to convert.
     * @return int The timestamp in milliseconds.
     */
    private function toGregorian(array $jDate): int
    {
        $jalali = [
            'year' => 1348,
            'month' => 10,
            'day' => 11,
            'hours' => $this->getHours(),
            'min' => $this->getMinutes(),
            'sec' => $this->getSeconds(),
            'ms' => $this->getMilliseconds(),
            'isLeapYear' => false,
        ];

        $diffDays = 0;

        if (
            $jDate['year'] > 1348
            || ($jDate['year'] === 1348 && $jDate['month'] >= 10)
            || ($jDate['year'] === 1348 && $jDate['month'] === 10 && $jDate['day'] >= 11)
        ) {
            for ($i = 0; ; $i++) {
                if ($jalali['year'] === $jDate['year'] && $jalali['month'] === $jDate['month'] && $jalali['day'] === $jDate['day']) {
                    break;
                }

                $diffDays++;
                $jalali['day']++;

                if ($jalali['month'] < 7 && $jalali['day'] > 31) {
                    $jalali['month']++;
                    $jalali['day'] = 1;
                } elseif ($jalali['month'] > 6 && $jalali['month'] < 12 && $jalali['day'] > 30) {
                    $jalali['month']++;
                    $jalali['day'] = 1;
                } elseif ($jalali['month'] === 12) {
                    $isLeapYear = $this->checkLeapYear($jalali['year']);

                    if ($isLeapYear && $jalali['day'] > 30) {
                        $jalali['year']++;
                        $jalali['month'] = 1;
                        $jalali['day'] = 1;
                    } elseif (!$isLeapYear && $jalali['day'] > 29) {
                        $jalali['year']++;
                        $jalali['month'] = 1;
                        $jalali['day'] = 1;
                    }
                }
            }
        } elseif (
            $jDate['year'] < 1348
            || ($jDate['year'] === 1348 && $jDate['month'] < 10)
            || ($jDate['year'] === 1348 && $jDate['month'] === 10 && $jDate['day'] < 11)
        ) {
            for ($i = 0; ; $i++) {
                if ($jalali['year'] === $jDate['year'] && $jalali['month'] === $jDate['month'] && $jalali['day'] === $jDate['day']) {
                    break;
                }

                $diffDays--;
                $jalali['day']--;

                if ($jalali['day'] < 1) {
                    if ($jalali['month'] === 1) {
                        $jalali['year']--;
                        $jalali['month'] = 12;

                        $isLeapYear = $this->checkLeapYear($jalali['year']);
                        $jalali['day'] = $isLeapYear ? 30 : 29;
                    } elseif ($jalali['month'] < 8) {
                        $jalali['month']--;
                        $jalali['day'] = 31;
                    } elseif ($jalali['month'] < 13) {
                        $jalali['month']--;
                        $jalali['day'] = 30;
                    }
                }
            }
        }

        $outputTime =
            $diffDays * $this->dateLength
            + ($this->getHours() * 3600 + ($this->getMinutes() + $this->getTimezoneOffset()) * 60 + $this->getSeconds()) * 1000
            + $this->getMilliseconds();

        return $outputTime;
    }

    /**
     * Checks if the given Jalali year is a leap year.
     * @param int $year The Jalali year to check.
     * @return bool True if the year is a leap year, false otherwise.
     */
    private function checkLeapYear(int $year): bool
    {
        if ($year > 0) {
            $checkYear = ($year + 2346) * $this->leapYearDivision;

            return $checkYear - (int) $checkYear < $this->leapYearDivision;
        }

        return false;
    }

    /**
     * Resets the Jalali conversion state and recalculates from the base date.
     * @return array{year: int, month: int, day: int, hours: int, min: int, sec: int, ms: int, isLeapYear: bool} The recalculated Jalali date.
     */
    private function reset(): array
    {
        $this->isConverted = false;
        $this->jalali['year'] = 1348;
        $this->jalali['month'] = 10;
        $this->jalali['day'] = 11;

        return $this->toJalali();
    }

    /**
     * Pads a number with leading zero if it's a single digit.
     * @param int $number The number to pad.
     * @return string The padded string (e.g., "05" for 5).
     */
    private function addZero(int $number): string
    {
        return ($number > -1 && $number < 10 ? '0' : '') . $number;
    }

    /**
     * Returns the Persian name of a Jalali month.
     * @param int $number The month number (1-12).
     * @return string The Persian month name (e.g., "فروردین").
     */
    private function getJalaliMonth(int $number): string
    {
        return $this->monthsNames[$number - 1];
    }

    /**
     * Returns the English name of a Gregorian month.
     * @param int $number The month number (1-12).
     * @return string The English month name (e.g., "January").
     */
    private function getGregorianMonth(int $number): string
    {
        return $this->monthsGregorianNames[$number - 1];
    }

    /**
     * Returns the day of the year (1-366) in Gregorian calendar.
     * @return int The day of the year.
     */
    private function getGregorianDayOfYear(): int
    {
        $dt = $this->dateTime();
        $year = (int) $dt->format('Y');

        // Match JS `new Date(year, 0, 0)` — Dec 31 of previous year, local midnight
        $start = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00.000', $year), $dt->getTimezone());
        $start = $start->modify('-1 day');

        return (int) ceil(($this->getTime() - $this->dateTimeToTimestamp($start)) / $this->dateLength);
    }

    /**
     * Returns the ISO week number of the year in Gregorian calendar.
     * @return int The week number (1-53).
     */
    private function getGregorianWeekOfYear(): int
    {
        $dt = $this->dateTime();
        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $day = (int) $dt->format('j');

        $d = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
        $dow = (int) $d->format('N'); // 1=Mon .. 7=Sun
        $d = $d->modify('+' . (4 - $dow) . ' days');
        $yearStart = new DateTimeImmutable($d->format('Y') . '-01-01', new DateTimeZone('UTC'));

        return (int) ceil((((int) $d->format('U') - (int) $yearStart->format('U')) / 86400 + 1) / 7);
    }

    /**
     * Returns the English name of the day of the week.
     * @return string The day name (e.g., "Monday").
     */
    private function getGregorianWeekDayName(): string
    {
        return $this->weekDaysGregorianNames[$this->getJsDay()];
    }

    /**
     * Returns the first letter of the day of the week in English.
     * @return string The day letter (e.g., "M" for Monday).
     */
    private function getGregorianWeekDayLetter(): string
    {
        return $this->weekDaysGregorianLetters[$this->getJsDay()];
    }

    /**
     * Returns the Persian name of the day of the week.
     * @return string The Persian day name (e.g., "شنبه").
     */
    private function getWeekDayName(): string
    {
        return $this->weekDaysNames[($this->getDay() + $this->firstDayOfWeek) % 7];
    }

    /**
     * Returns the first letter of the day of the week in Persian.
     * @return string The Persian day letter (e.g., "ش" for شنبه).
     */
    private function getWeekDayLetter(): string
    {
        return $this->weekDaysLetters[($this->getDay() + $this->firstDayOfWeek) % 7];
    }

    /**
     * Extracts and returns the UTC timezone string from the Date.
     * @return string The timezone string (e.g., " GMT+0330").
     */
    private function getUTCTimezone(): string
    {
        $offset = $this->dateTime()->getOffset();
        $sign = $offset >= 0 ? '+' : '-';
        $abs = abs($offset);
        $hours = intdiv($abs, 3600);
        $mins = intdiv($abs % 3600, 60);
        $tzName = $this->getTimezone();

        return sprintf(' GMT%s%02d%02d (%s)', $sign, $hours, $mins, $tzName);
    }

    /**
     * Adjusts the date by adding the specified number of days.
     * @param int $date The number of days to add (can be negative).
     * @return int The result of setDate.
     */
    private function normalizeDate(int $date): int
    {
        $dt = $this->dateTime()->modify(($date >= 0 ? '+' : '') . $date . ' days');
        $this->timestamp = $this->dateTimeToTimestamp($dt);

        return $this->getTime();
    }

    /**
     * Normalizes Jalali date components and sets the internal Date accordingly.
     * @param array{year: int, month: int, day: int, hours?: int, min?: int, sec?: int, ms?: int, isLeapYear?: bool} $jDate The current Jalali date object.
     * @param int $year The target year.
     * @param int $month The target month (1-12).
     * @param int $day The target day of month.
     * @return int The result of setTime.
     */
    private function normalizeJalali(array $jDate, int $year, int $month, int $day): int
    {
        if ($month < 1) {
            $yearDiff = (int) ceil((1 - $month) / 12);
            $year -= $yearDiff;
            $month = 12 * $yearDiff + $month;
        } elseif ($month > 12) {
            $yearDiff = (int) floor(($month - 1) / 12);
            $year += $yearDiff;
            $month = $month - 12 * $yearDiff;
        }

        if ($month > 6 && $month < 12 && $day > 30) {
            $day = 30;
        } elseif ($month === 12) {
            $isLeapYear = $this->checkLeapYear($year);

            if ($isLeapYear && $day > 30) {
                $day = 30;
            } elseif (!$isLeapYear && $day > 29) {
                $day = 29;
            }
        }

        return $this->setTime($this->toGregorian(array_merge($jDate, [
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ])));
    }

    /**
     * Normalizes timezone string to a standard format.
     * @param string|null $timezone The timezone string (e.g., "UTC", "local", "Asia/Tehran").
     * @return string The normalized timezone string.
     */
    private function normalizeTimezone(?string $timezone): string
    {
        if ($timezone === null) {
            return $this->getTimezone();
        }

        $trimmed = trim($timezone);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Invalid timezone: empty');
        }

        $lowered = strtolower($trimmed);

        if ($lowered === 'default' || $lowered === 'local' || $lowered === 'system') {
            return date_default_timezone_get();
        }

        if ($lowered === 'utc' || $lowered === 'gmt') {
            return 'UTC';
        }

        // Preserve IANA casing (e.g. Asia/Tehran).
        return $trimmed;
    }

    /**
     * Magic string cast; delegates to toString().
     * @return string Full date string with weekday, day, month, year, and time.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns a string representation of a date in Jalali format.
     * @return string Full date string with weekday, day, month, year, and time.
     */
    public function toString(): string
    {
        $jDate = $this->toJalali();

        return $this->getWeekDayName() . ' ' . $this->addZero($jDate['day']) . ' ' . $this->getJalaliMonth($jDate['month']) . ' ' . $this->addZero($jDate['year']) . ' ' . $jDate['hours'] . ':' . $this->addZero($jDate['min']) . ':' . $this->addZero($jDate['sec']) . $this->getUTCTimezone();
    }

    /**
     * Returns a date as a string value (without time).
     * @return string Date string with weekday, day, month, and year.
     */
    public function toDateString(): string
    {
        $jDate = $this->toJalali();

        return $this->getWeekDayName() . ' ' . $this->addZero($jDate['day']) . ' ' . $this->getJalaliMonth($jDate['month']) . ' ' . $this->addZero($jDate['year']);
    }

    /**
     * Returns a date as a string value in locale format (YYYY/MM/DD).
     * @return string Locale-formatted date string.
     */
    public function toLocaleDateString(): string
    {
        $jDate = $this->toJalali();

        return $this->addZero($jDate['year']) . '/' . $this->addZero($jDate['month']) . '/' . $this->addZero($jDate['day']);
    }

    /**
     * Returns a time as a string value with meridiem.
     * @return string Time string (e.g., "10:30:45 ق.ظ.").
     */
    public function toLocaleTimeString(): string
    {
        $jDate = $this->toJalali();

        return ($jDate['hours'] % 12) . ':' . $this->addZero($jDate['min']) . ':' . $this->addZero($jDate['sec']) . ' ' . $this->getMeridiem();
    }

    /**
     * Gets the Jalali year.
     * @return int The year (e.g., 1403).
     */
    public function getFullYear(): int
    {
        return $this->toJalali()['year'];
    }

    /**
     * Gets the Jalali month (1-12).
     * @return int The month number.
     */
    public function getMonth(): int
    {
        return $this->toJalali()['month'];
    }

    /**
     * Gets the day of the month in Jalali calendar (1-31).
     * @return int The day of the month.
     */
    public function getDate(): int
    {
        return $this->toJalali()['day'];
    }

    /**
     * Gets the day of the week (1=Saturday, 7=Friday in Jalali).
     * @return int The day number (1-7).
     */
    public function getDay(): int
    {
        return ($this->getJsDay() - $this->firstDayOfWeek + 7 + 1) % 7;
    }

    /**
     * Gets the timestamp in milliseconds since Unix epoch.
     * @return int Elapsed milliseconds since midnight, January 1, 1970 GMT.
     */
    public function getTime(): int
    {
        return $this->timestamp;
    }

    /**
     * Gets the hours value using local time (0-23).
     * @return int The hours value.
     */
    public function getHours(): int
    {
        return (int) $this->dateTime()->format('G');
    }

    /**
     * Gets the minutes value using local time (0-59).
     * @return int The minutes value.
     */
    public function getMinutes(): int
    {
        return (int) $this->dateTime()->format('i');
    }

    /**
     * Gets the seconds value using local time (0-59).
     * @return int The seconds value.
     */
    public function getSeconds(): int
    {
        return (int) $this->dateTime()->format('s');
    }

    /**
     * Gets the milliseconds value using local time (0-999).
     * @return int The milliseconds value.
     */
    public function getMilliseconds(): int
    {
        return (int) $this->dateTime()->format('v');
    }

    /**
     * Sets the date and time value in the Date object.
     * @param int $time A numeric value representing the number of elapsed milliseconds since midnight, January 1, 1970 GMT.
     * @return int The timestamp after update.
     */
    public function setTime(int $time): int
    {
        $this->timestamp = $time;
        $this->reset();

        return $this->getTime();
    }

    /**
     * Sets the milliseconds value in the Date object using local time.
     * @param int $ms A numeric value equal to the millisecond value.
     * @return int The timestamp after update.
     */
    public function setMilliseconds(int $ms): int
    {
        $dt = $this->dateTime();

        $this->timestamp = $this->buildTimestamp(
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j'),
            (int) $dt->format('G'),
            (int) $dt->format('i'),
            (int) $dt->format('s'),
            $ms
        );

        $this->jalali['ms'] = $ms;

        return $this->getTime();
    }

    /**
     * Sets the seconds value in the Date object using local time.
     * @param int $sec A numeric value equal to the seconds value.
     * @param int|null $ms A numeric value equal to the milliseconds value.
     * @return int The timestamp after update.
     */
    public function setSeconds(int $sec, ?int $ms = null): int
    {
        $dt = $this->dateTime();
        $msVal = $ms ?? (int) $dt->format('v');

        $this->timestamp = $this->buildTimestamp(
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j'),
            (int) $dt->format('G'),
            (int) $dt->format('i'),
            $sec,
            $msVal
        );

        $this->jalali['sec'] = $sec;

        if ($ms) {
            $this->jalali['ms'] = $ms;
        }

        return $this->getTime();
    }

    /**
     * Sets the minutes value in the Date object using local time.
     * @param int $min A numeric value equal to the minutes value.
     * @param int|null $sec A numeric value equal to the seconds value.
     * @param int|null $ms A numeric value equal to the milliseconds value.
     * @return int The timestamp after update.
     */
    public function setMinutes(int $min, ?int $sec = null, ?int $ms = null): int
    {
        $dt = $this->dateTime();
        $secVal = $sec ?? (int) $dt->format('s');
        $msVal = $ms ?? (int) $dt->format('v');

        $this->timestamp = $this->buildTimestamp(
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j'),
            (int) $dt->format('G'),
            $min,
            $secVal,
            $msVal
        );

        $this->jalali['min'] = $min;

        if ($sec) {
            $this->jalali['sec'] = $sec;
        }

        if ($ms) {
            $this->jalali['ms'] = $ms;
        }

        return $this->getTime();
    }

    /**
     * Sets the hour value in the Date object using local time.
     * @param int $hours A numeric value equal to the hours value.
     * @param int|null $min A numeric value equal to the minutes value.
     * @param int|null $sec A numeric value equal to the seconds value.
     * @param int|null $ms A numeric value equal to the milliseconds value.
     * @return int The timestamp after update.
     */
    public function setHours(int $hours, ?int $min = null, ?int $sec = null, ?int $ms = null): int
    {
        $dt = $this->dateTime();
        $minVal = $min ?? (int) $dt->format('i');
        $secVal = $sec ?? (int) $dt->format('s');
        $msVal = $ms ?? (int) $dt->format('v');

        $this->timestamp = $this->buildTimestamp(
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j'),
            $hours,
            $minVal,
            $secVal,
            $msVal
        );

        $this->jalali['hours'] = $hours;

        if ($min) {
            $this->jalali['min'] = $min;
        }

        if ($sec) {
            $this->jalali['sec'] = $sec;
        }

        if ($ms) {
            $this->jalali['ms'] = $ms;
        }

        return $this->getTime();
    }

    /**
     * Sets the numeric day-of-the-month value of the Date object using local time.
     * @param int $date A numeric value equal to the day of the month.
     * @return int The timestamp after update.
     */
    public function setDate(int $date): int
    {
        $jDate = $this->toJalali();
        $this->normalizeDate($date - $jDate['day']);

        return $this->getTime();
    }

    /**
     * Sets the month value in the Date object using local time.
     * @param int $month A numeric value equal to the month. The value for Farvardin is 0, and other month values follow consecutively.
     * @param int|null $date A numeric value representing the day of the month. If this value is not supplied, the value from a call to the getDate method is used.
     * @return int The timestamp after update.
     */
    public function setMonth(int $month, ?int $date = null): int
    {
        $jDate = $this->toJalali();
        $this->normalizeJalali($jDate, $jDate['year'], $month + 1, $jDate['day']);
        $this->reset();

        if ($date !== null) {
            $jDate = $this->toJalali();
            $this->normalizeDate($date - $jDate['day']);
            $this->reset();
        }

        return $this->getTime();
    }

    /**
     * Sets the year of the Date object using local time.
     * @param int $year A numeric value for the year.
     * @param int|null $month A zero-based numeric value for the month (0 for Farvardin, 11 for Esfand). Must be specified if numDate is specified.
     * @param int|null $date A numeric value equal for the day of the month.
     * @return int The timestamp after update.
     */
    public function setFullYear(int $year, ?int $month = null, ?int $date = null): int
    {
        $jDate = $this->toJalali();
        $this->normalizeJalali($jDate, $year, $month !== null ? $month + 1 : $jDate['month'], $jDate['day']);
        $this->reset();

        if ($date !== null) {
            $jDate = $this->toJalali();
            $this->normalizeDate($date - $jDate['day']);
            $this->reset();
        }

        return $this->getTime();
    }

    /**
     * Creates a JalaliX instance from a Gregorian date value.
     * @param int|float|string|\DateTimeInterface|self $date The date value (timestamp, ISO string, or DateTime).
     * @param array{timezone?: string}|null $options Optional configuration including timezone.
     * @return self A new JalaliX instance.
     */
    public static function create(int|float|string|DateTimeInterface|self $date, ?array $options = null): self
    {
        $output = new self($date);

        if (isset($options['timezone'])) {
            $output = $output->setTimezone($options['timezone']);
        }

        return $output;
    }

    /**
     * Parses a Jalali date string into a JalaliX instance.
     * Accepts `YYYY/MM/DD`, `YYYY-MM-DD`, or `YYYY.MM.DD`, optional time (`HH:mm` / `HH:mm:ss`),
     * and Persian / Arabic-Indic digits.
     * Named `parseJalali` because `Date.parse` already exists on the base class.
     * @param string $value Jalali date string (e.g. "1403/12/07", "۱۴۰۳-۱۲-۰۷ 14:30").
     * @param array{timezone?: string}|null $options Optional configuration including timezone.
     * @return self A new JalaliX instance.
     */
    public static function parseJalali(string $value, ?array $options = null): self
    {
        $normalized = self::fromPersian((string) $value);
        $normalized = trim($normalized);

        if (!preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?:[ T](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $normalized, $match)) {
            throw new InvalidArgumentException('JalaliX.parseJalali: invalid Jalali date "' . $value . '"');
        }

        $year = (int) $match[1];
        $month = (int) $match[2];
        $day = (int) $match[3];
        $hours = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : 0;
        $minutes = isset($match[5]) && $match[5] !== '' ? (int) $match[5] : 0;
        $seconds = isset($match[6]) && $match[6] !== '' ? (int) $match[6] : 0;

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            throw new InvalidArgumentException('JalaliX.parseJalali: invalid Jalali date "' . $value . '"');
        }

        $output = self::create(new DateTimeImmutable('now'), $options);
        $output->setFullYear($year, $month - 1, $day);
        $output->setHours($hours, $minutes, $seconds, 0);

        return $output;
    }

    /**
     * Soft-normalizes loose query / form date inputs into a Gregorian `Y-m-d H:i:s` string.
     *
     * Handles empty/sentinel values (`null`, `undefined`, `Invalid Date`, `NaN`), unix timestamps
     * (seconds or milliseconds), Persian/Arabic digits, `/` `-` `.` separators, fractional seconds,
     * optional `Z` / offset suffixes, and auto-detects Jalali vs Gregorian:
     * Jalali years start at 1; when both calendars are valid, the interpretation closer to now wins.
     *
     * Invalid or unrecognized values are returned cleaned (digits normalized) rather than throwing;
     * sentinels and non-positive timestamps return `null`. Non-string / non-numeric values pass through.
     *
     * @param mixed $value Raw query value.
     * @param array{timezone?: string}|null $options Optional timezone for timestamp / Jalali conversion.
     * @return mixed `null`, Gregorian `Y-m-d H:i:s`, cleaned string, or the original non-scalar value.
     */
    public static function normalizeQueryDate(mixed $value, ?array $options = null): mixed
    {
        $isInvalidDate = is_string($value) && (
            strcasecmp($value, 'Invalid Date') === 0
            || preg_match('/NaN/i', $value) === 1
        );

        if (
            $value === null
            || $value === 'null'
            || $value === ''
            || $value === 'undefined'
            || $isInvalidDate
        ) {
            return null;
        }

        if (is_numeric($value) && !is_string($value)) {
            return self::normalizeQueryTimestamp((int) $value, $options);
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return self::normalizeQueryTimestamp((int) trim($value), $options);
        }

        if (!is_string($value)) {
            return $value;
        }

        $digits = self::fromPersian($value);
        $digits = preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/', '$1', $digits) ?? $digits;

        if (
            preg_match(
                '/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?(?:Z|[+-]\d{2}:?\d{2})?)?/',
                $digits,
                $matches
            ) !== 1
        ) {
            return $digits;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $hour = isset($matches[4]) ? (int) $matches[4] : 0;
        $minute = isset($matches[5]) ? (int) $matches[5] : 0;
        $second = isset($matches[6]) ? (int) $matches[6] : 0;

        /** @var list<array{ms: int, formatted: string}> $candidates */
        $candidates = [];

        if ($year >= 1 && self::isValidJalaliCivilDate($year, $month, $day)) {
            try {
                $jalali = self::parseJalali(
                    sprintf('%04d/%02d/%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
                    $options
                );
                $candidates[] = [
                    'ms' => $jalali->getTime(),
                    'formatted' => $jalali->format('YYYY-MM-DD HH:mm:ss', ['calendar' => 'gregorian']),
                ];
            } catch (InvalidArgumentException) {
                // ignore invalid Jalali interpretation
            }
        }

        if (checkdate($month, $day, $year)) {
            $gregorian = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);

            try {
                $ms = self::create($gregorian, $options)->getTime();
                $candidates[] = ['ms' => $ms, 'formatted' => $gregorian];
            } catch (InvalidArgumentException) {
                $candidates[] = ['ms' => 0, 'formatted' => $gregorian];
            }
        }

        if ($candidates === []) {
            return $digits;
        }

        if (count($candidates) === 1) {
            return $candidates[0]['formatted'];
        }

        $now = self::create((int) floor(microtime(true) * 1000), $options)->getTime();

        usort(
            $candidates,
            static fn (array $a, array $b): int => abs($a['ms'] - $now) <=> abs($b['ms'] - $now)
        );

        return $candidates[0]['formatted'];
    }

    /**
     * Whether Y/M/D is a valid Jalali civil date (year >= 1).
     */
    private static function isValidJalaliCivilDate(int $year, int $month, int $day): bool
    {
        if ($year < 1 || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        if ($month <= 6) {
            return $day <= 31;
        }

        if ($month <= 11) {
            return $day <= 30;
        }

        $probe = self::create(0);
        $isLeap = (static function () use ($probe, $year): bool {
            $method = new \ReflectionMethod(self::class, 'checkLeapYear');
            $method->setAccessible(true);

            return (bool) $method->invoke($probe, $year);
        })();

        return $day <= ($isLeap ? 30 : 29);
    }

    /**
     * @param array{timezone?: string}|null $options
     */
    private static function normalizeQueryTimestamp(int $timestamp, ?array $options = null): ?string
    {
        if ($timestamp > 9999999999) {
            $timestamp = (int) floor($timestamp / 1000);
        }

        if ($timestamp <= 0) {
            return null;
        }

        return self::create($timestamp * 1000, $options)
            ->format('YYYY-MM-DD HH:mm:ss', ['calendar' => 'gregorian']);
    }

    /**
     * Checks if the given value is a valid Date.
     * @param mixed $date The value to check.
     * @return bool True if valid Date, false otherwise.
     */
    public static function isValidDate(mixed $date): bool
    {
        if ($date instanceof self) {
            return true;
        }

        if ($date instanceof DateTimeInterface) {
            return true;
        }

        return false;
    }

    /**
     * Converts Western numerals (0-9) to Persian numerals (۰-۹) in a string.
     * @param string $str The string containing numerals to convert.
     * @return string The string with Persian numerals.
     */
    public static function toPersian(string $str): string
    {
        for ($i = 0; $i <= 9; $i++) {
            $str = str_replace((string) $i, self::$persianNumbers[$i], $str);
        }

        return $str;
    }

    /**
     * Converts Persian (۰-۹) and Arabic-Indic (٠-٩) numerals to Western numerals (0-9).
     * @param string $str The string containing numerals.
     * @return string The string with Western numerals.
     */
    public static function fromPersian(string $str): string
    {
        $output = $str;

        for ($i = 0; $i <= 9; $i++) {
            $output = str_replace(self::$persianNumbers[$i], (string) $i, $output);
            $output = str_replace(self::$arabicNumbers[$i], (string) $i, $output);
        }

        return $output;
    }

    /**
     * Compares two JalaliX dates.
     * @param self $date The first date to compare.
     * @param self $comparing The second date to compare against.
     * @return bool True if date is before comparing, false otherwise.
     */
    public static function compare(self $date, self $comparing): bool
    {
        return $date->getTime() < $comparing->getTime();
    }

    /**
     * Creates a copy of this JalaliX instance.
     * @return self A new JalaliX instance with the same timestamp.
     */
    public function clone(): self
    {
        $copy = new self($this->timestamp);
        $copy->timezone = clone $this->timezone;

        return $copy;
    }

    /**
     * Gets the IANA timezone id of this instance.
     * @return string The IANA timezone string (e.g., "Asia/Tehran").
     */
    public function getTimezone(): string
    {
        return $this->timezone->getName();
    }

    /**
     * Gets the week number of the year in Jalali calendar.
     * @return int The week number (1-53).
     */
    public function getWeekOfYear(): int
    {
        $dayOfYear = $this->getDayOfYear();
        $dayOfWeek = (new self($this->timestamp))->getStartOfYear()->getDayISO();

        return (int) ceil(($dayOfYear + $dayOfWeek - 1) / 7);
    }

    /**
     * Gets the day of the year in Jalali calendar (1-366).
     * @return int The day of the year.
     */
    public function getDayOfYear(): int
    {
        $jDate = $this->toJalali();
        $output = $jDate['day'];

        if ($jDate['month'] < 7) {
            $output += ($jDate['month'] - 1) * 31;
        } elseif ($jDate['month'] < 12) {
            $output += 186 + ($jDate['month'] - 7) * 30;
        } else {
            $output += 336;
        }

        return $output;
    }

    /**
     * Gets the ISO day of the week (1=Monday, 7=Sunday).
     * @return int The ISO day number (1-7).
     */
    public function getDayISO(): int
    {
        return (($this->getDay() + 6) % 7) + 1;
    }

    /**
     * Gets the hour in 12-hour format (1-12).
     * @return int The hour in 12-hour format.
     */
    public function getHoursISO(): int
    {
        $hours = $this->getHours();

        return $hours > 12 ? $hours - 12 : $hours;
    }

    /**
     * Gets the meridiem (AM/PM) in Persian.
     * @return string "ق.ظ." (before noon) or "ب.ظ." (after noon).
     */
    public function getMeridiem(): string
    {
        $jDate = $this->toJalali();

        return $jDate['hours'] > 12 ? $this->pm : $this->am;
    }

    /**
     * Returns AM or PM based on the hour in 12-hour format.
     * @return string "AM" or "PM".
     */
    private function getGregorianMeridiem(): string
    {
        $hours = $this->getHours();

        return $hours >= 12 ? $this->pmGregorian : $this->amGregorian;
    }

    /**
     * Returns a new JalaliX set to the start of the Jalali year.
     * @return self This instance modified to the first moment of the year.
     */
    public function getStartOfYear(): self
    {
        return $this->addDays(-1 * $this->getDayOfYear() + 1)->getStartOfDay();
    }

    /**
     * Returns a new JalaliX set to the start of the current month.
     * @return self This instance modified to the first moment of the month.
     */
    public function getStartOfMonth(): self
    {
        return $this->addDays(-1 * $this->getDate() + 1)->getStartOfDay();
    }

    /**
     * Returns a new JalaliX set to the start of the current week (Monday).
     * @return self This instance modified to the first moment of the week.
     */
    public function getStartOfWeek(): self
    {
        return $this->addDays(-1 * $this->getDayISO() + 1)->getStartOfDay();
    }

    /**
     * Returns a new JalaliX set to the start of the current day (00:00:00.000).
     * @return self This instance modified to midnight.
     */
    public function getStartOfDay(): self
    {
        $this->setHours(0, 0, 0, 0);

        return $this;
    }

    /**
     * Returns a new JalaliX set to the end of the Jalali year (23:59:59.999).
     * @return self This instance modified to the last moment of the year.
     */
    public function getEndOfYear(): self
    {
        $jDate = $this->toJalali();
        $lastDay = $jDate['isLeapYear'] ? 366 : 365;

        return $this->addDays($lastDay - $this->getDayOfYear())->getEndOfDay();
    }

    /**
     * Returns a new JalaliX set to the end of the current month.
     * @return self This instance modified to the last moment of the month.
     */
    public function getEndOfMonth(): self
    {
        $jDate = $this->toJalali();
        $lastDay = 31;

        if ($jDate['month'] > 6 && $jDate['month'] < 12) {
            $lastDay = 30;
        } elseif ($jDate['month'] === 12) {
            $lastDay = $jDate['isLeapYear'] ? 30 : 29;
        }

        return $this->addDays($lastDay - $this->getDate())->getEndOfDay();
    }

    /**
     * Returns a new JalaliX set to the end of the current week (Sunday).
     * @return self This instance modified to the last moment of the week.
     */
    public function getEndOfWeek(): self
    {
        return $this->addDays(7 - $this->getDayISO())->getEndOfDay();
    }

    /**
     * Returns a new JalaliX set to the end of the current day (23:59:59.999).
     * @return self This instance modified to the last moment of the day.
     */
    public function getEndOfDay(): self
    {
        $this->setHours(23, 59, 59, 999);

        return $this;
    }

    /**
     * Creates a new JalaliX with the same UTC instant in a different timezone.
     * Instant-preserving: getTime() is unchanged; wall clock / Jalali day may change.
     * @param string|null $timezone IANA id (e.g. "Asia/Tehran"), or alias utc/gmt/local/default/system. null keeps current.
     * @return self A new JalaliX instance in the specified timezone.
     * @throws InvalidArgumentException If the zone cannot be resolved.
     */
    public function setTimezone(?string $timezone): self
    {
        $tzName = $this->normalizeTimezone($timezone);

        try {
            $tz = new DateTimeZone($tzName);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid timezone: ' . $tzName, 0, $e);
        }

        $output = new self($this->timestamp);
        $output->timezone = $tz;

        return $output;
    }

    /**
     * Adds the specified number of years to the date.
     * @param int $year The number of years to add (can be negative).
     * @return self This instance modified.
     */
    public function addYears(int $year): self
    {
        $jDate = $this->toJalali();
        $this->normalizeJalali($jDate, $jDate['year'] + $year, $jDate['month'], $jDate['day']);
        $this->reset();

        return $this;
    }

    /**
     * Adds the specified number of months to the date.
     * @param int $month The number of months to add (can be negative).
     * @return self This instance modified.
     */
    public function addMonths(int $month): self
    {
        $jDate = $this->toJalali();
        $this->normalizeJalali($jDate, $jDate['year'], $jDate['month'] + $month, $jDate['day']);
        $this->reset();

        return $this;
    }

    /**
     * Adds the specified number of weeks to the date.
     * @param int $date The number of weeks to add (can be negative).
     * @return self This instance modified.
     */
    public function addWeeks(int $date): self
    {
        $this->normalizeDate($date * 7);
        $this->reset();

        return $this;
    }

    /**
     * Adds the specified number of days to the date.
     * @param int $date The number of days to add (can be negative).
     * @return self This instance modified.
     */
    public function addDays(int $date): self
    {
        $this->normalizeDate($date);
        $this->reset();

        return $this;
    }

    /**
     * Returns a calendar date string using local getters (no UTC shift from `toISOString()`).
     * @param string $calendar `"gregorian"` (default) -> `YYYY-MM-DD` Gregorian; `"jalali"` -> Jalali `YYYY-MM-DD`.
     * @return string The ISO-style date string.
     */
    public function toISODate(string $calendar = 'gregorian'): string
    {
        if ($calendar === 'jalali') {
            return $this->format('YYYY-MM-DD');
        }

        $dt = $this->dateTime();
        $year = (int) $dt->format('Y');
        $month = (int) $dt->format('n');
        $date = (int) $dt->format('j');

        return $year . '-' . $this->addZero($month) . '-' . $this->addZero($date);
    }

    /**
     * Formats the date according to the specified format string.
     * Prefer `format(str, { calendar, persianDigits })`. Positional booleans remain supported:
     * `format(str, persianDigits?, gregorian?)`.
     * @param string $str The format string (e.g., "YYYY/MM/DD", "DD MMM YYYY").
     * @param bool|array{calendar?: string, persianDigits?: bool}|null $persianOrOptions Persian digits flag, or options object.
     * @param bool|null $gregorian If true, uses Gregorian calendar (positional API only).
     * @return string The formatted date string.
     */
    public function format(string $str, bool|array|null $persianOrOptions = null, ?bool $gregorian = null): string
    {
        $persian = false;
        $useGregorian = false;

        if (is_array($persianOrOptions)) {
            $persian = (bool) ($persianOrOptions['persianDigits'] ?? false);
            $useGregorian = ($persianOrOptions['calendar'] ?? null) === 'gregorian';
        } else {
            $persian = (bool) $persianOrOptions;
            $useGregorian = (bool) $gregorian;
        }

        $placeholders = [];
        $ph = static function (string $val) use (&$placeholders): string {
            $i = count($placeholders);
            $placeholders[] = $val;

            return "\u{E000}{$i}\u{E001}";
        };

        $output = $str;
        $dt = $this->dateTime();

        $year = $useGregorian ? (int) $dt->format('Y') : $this->getFullYear();
        $month = $useGregorian ? (int) $dt->format('n') : $this->getMonth();
        $weekOfYear = $useGregorian ? $this->getGregorianWeekOfYear() : $this->getWeekOfYear();
        $dayOfYear = $useGregorian ? $this->getGregorianDayOfYear() : $this->getDayOfYear();
        $date = $useGregorian ? (int) $dt->format('j') : $this->getDate();
        $monthName = $useGregorian ? $this->getGregorianMonth($month) : $this->getJalaliMonth($month);
        $weekDayName = $useGregorian ? $this->getGregorianWeekDayName() : $this->getWeekDayName();
        $weekDayLetter = $useGregorian ? $this->getGregorianWeekDayLetter() : $this->getWeekDayLetter();
        $meridiem = $useGregorian ? $this->getGregorianMeridiem() : $this->getMeridiem();
        $yearStr = (string) $year;

        if (preg_match('/YYYY/', $output)) {
            $output = preg_replace_callback('/YYYY/', static fn () => $ph($yearStr), $output) ?? $output;
        }

        if (preg_match('/YYY/', $output)) {
            $output = preg_replace_callback('/YYY/', static fn () => $ph(substr($yearStr, -3)), $output) ?? $output;
        }

        if (preg_match('/YY/', $output)) {
            $output = preg_replace_callback('/YY/', static fn () => $ph(substr($yearStr, -2)), $output) ?? $output;
        }

        if (preg_match('/Y/', $output)) {
            $output = preg_replace_callback('/Y/', static fn () => $ph(substr($yearStr, -1)), $output) ?? $output;
        }

        if (preg_match('/MMM/', $output)) {
            $output = preg_replace_callback('/MMM/', static fn () => $ph($monthName), $output) ?? $output;
        }

        if (preg_match('/MM/', $output)) {
            $output = preg_replace_callback('/MM/', fn () => $ph($this->addZero($month)), $output) ?? $output;
        }

        if (preg_match('/M/', $output)) {
            $output = preg_replace_callback('/M/', static fn () => $ph((string) $month), $output) ?? $output;
        }

        if (preg_match('/W/', $output)) {
            $output = preg_replace_callback('/W/', fn () => $ph($this->addZero($weekOfYear)), $output) ?? $output;
        }

        if (preg_match('/w/', $output)) {
            $output = preg_replace_callback('/w/', static fn () => $ph((string) $weekOfYear), $output) ?? $output;
        }

        if (preg_match('/DW/', $output)) {
            $output = preg_replace_callback('/DW/', fn () => $ph((string) $this->getDayISO()), $output) ?? $output;
        }

        if (preg_match('/dw/', $output)) {
            $output = preg_replace_callback('/dw/', fn () => $ph((string) ($useGregorian ? ($this->getJsDay() ?: 7) : $this->getDay())), $output) ?? $output;
        }

        if (preg_match('/WN/', $output)) {
            $output = preg_replace_callback('/WN/', static fn () => $ph($weekDayName), $output) ?? $output;
        }

        if (preg_match('/wn/', $output)) {
            $output = preg_replace_callback('/wn/', static fn () => $ph($weekDayName), $output) ?? $output;
        }

        if (preg_match('/WL/', $output)) {
            $output = preg_replace_callback('/WL/', static fn () => $ph($weekDayLetter), $output) ?? $output;
        }

        if (preg_match('/wl/', $output)) {
            $output = preg_replace_callback('/wl/', static fn () => $ph($weekDayLetter), $output) ?? $output;
        }

        if (preg_match('/DDDD/', $output)) {
            $output = preg_replace_callback('/DDDD/', fn () => $ph($this->addZero($dayOfYear)), $output) ?? $output;
        }

        if (preg_match('/DDD/', $output)) {
            $output = preg_replace_callback('/DDD/', static fn () => $ph((string) $dayOfYear), $output) ?? $output;
        }

        if (preg_match('/DD/', $output)) {
            $output = preg_replace_callback('/DD/', fn () => $ph($this->addZero($date)), $output) ?? $output;
        }

        if (preg_match('/D/', $output)) {
            $output = preg_replace_callback('/D/', static fn () => $ph((string) $date), $output) ?? $output;
        }

        $hours = $this->getHours();

        if (preg_match('/HH/', $output)) {
            $output = preg_replace_callback('/HH/', fn () => $ph($this->addZero($hours)), $output) ?? $output;
        }

        if (preg_match('/H/', $output)) {
            $output = preg_replace_callback('/H/', static fn () => $ph((string) $hours), $output) ?? $output;
        }

        $hoursISO = $hours > 12 ? $hours - 12 : ($hours === 0 ? 12 : $hours);

        if (preg_match('/hh/', $output)) {
            $output = preg_replace_callback('/hh/', fn () => $ph($this->addZero($hoursISO)), $output) ?? $output;
        }

        if (preg_match('/h/', $output)) {
            $output = preg_replace_callback('/h/', static fn () => $ph((string) $hoursISO), $output) ?? $output;
        }

        if (preg_match('/mm/', $output)) {
            $output = preg_replace_callback('/mm/', fn () => $ph($this->addZero($this->getMinutes())), $output) ?? $output;
        }

        if (preg_match('/m/', $output)) {
            $output = preg_replace_callback('/m/', fn () => $ph((string) $this->getMinutes()), $output) ?? $output;
        }

        if (preg_match('/ss/', $output)) {
            $output = preg_replace_callback('/ss/', fn () => $ph($this->addZero($this->getSeconds())), $output) ?? $output;
        }

        if (preg_match('/s/', $output)) {
            $output = preg_replace_callback('/s/', fn () => $ph((string) $this->getSeconds()), $output) ?? $output;
        }

        if (preg_match('/sss/', $output)) {
            $output = preg_replace_callback('/sss/', fn () => $ph((string) $this->getMilliseconds()), $output) ?? $output;
        }

        if (preg_match('/SSS/', $output)) {
            $output = preg_replace_callback('/SSS/', fn () => $ph((string) $this->getMilliseconds()), $output) ?? $output;
        }

        if (preg_match('/A/', $output)) {
            $output = preg_replace_callback('/A/', static fn () => $ph($meridiem), $output) ?? $output;
        }

        if (preg_match('/a/', $output)) {
            $output = preg_replace_callback('/a/', static fn () => $ph($meridiem), $output) ?? $output;
        }

        if (preg_match('/X/', $output)) {
            $output = preg_replace_callback('/X/', fn () => $ph((string) (int) floor($this->getTime() / 1000)), $output) ?? $output;
        }

        if (preg_match('/x/', $output)) {
            $output = preg_replace_callback('/x/', fn () => $ph((string) $this->getTime()), $output) ?? $output;
        }

        foreach ($placeholders as $i => $val) {
            $output = str_replace("\u{E000}{$i}\u{E001}", $val, $output);
        }

        return $persian ? self::toPersian($output) : $output;
    }

    /**
     * JS Date#getDay(): 0=Sunday .. 6=Saturday.
     * @return int The weekday index (0-6).
     */
    private function getJsDay(): int
    {
        return (int) $this->dateTime()->format('w');
    }

    /**
     * JS Date#getTimezoneOffset() in minutes.
     * @return int The timezone offset in minutes.
     */
    private function getTimezoneOffset(): int
    {
        return -intdiv($this->dateTime()->getOffset(), 60);
    }

    /**
     * UTC instant shifted by zone offset -> civil "as-if-UTC" milliseconds.
     * @return int Wall milliseconds for day-walk / HMS.
     */
    private function wallMs(): int
    {
        return $this->timestamp + $this->dateTime()->getOffset() * 1000;
    }

    /**
     * Returns the current instant as a DateTimeImmutable in the instance timezone.
     * @return \DateTimeImmutable The local wall-clock DateTime.
     */
    private function dateTime(): DateTimeImmutable
    {
        return $this->dateTimeUtc()->setTimezone($this->timezone);
    }

    /**
     * Returns the current instant as a DateTimeImmutable in UTC.
     * @return \DateTimeImmutable The UTC DateTime.
     */
    private function dateTimeUtc(): DateTimeImmutable
    {
        $ms = $this->timestamp;
        $sec = intdiv($ms, 1000);
        $milli = $ms - $sec * 1000;

        if ($milli < 0) {
            $sec--;
            $milli += 1000;
        }

        $dt = DateTimeImmutable::createFromFormat('U.v', sprintf('%d.%03d', $sec, $milli));

        if ($dt === false) {
            $dt = (new DateTimeImmutable('@' . $sec))->setTimezone(new DateTimeZone('UTC'));
        }

        return $dt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Converts a DateTimeInterface to a millisecond timestamp.
     * @param \DateTimeInterface $dt The DateTime to convert.
     * @return int Milliseconds since Unix epoch.
     */
    private function dateTimeToTimestamp(DateTimeInterface $dt): int
    {
        return ((int) $dt->format('U')) * 1000 + (int) $dt->format('v');
    }

    /**
     * Builds a millisecond timestamp from Gregorian local date-time components.
     * @param int $year Gregorian year.
     * @param int $month Gregorian month (1-12).
     * @param int $day Day of month.
     * @param int $hours Hours (0-23).
     * @param int $min Minutes.
     * @param int $sec Seconds.
     * @param int $ms Milliseconds.
     * @return int Milliseconds since Unix epoch.
     */
    private function buildTimestamp(int $year, int $month, int $day, int $hours, int $min, int $sec, int $ms): int
    {
        $tz = $this->timezone;

        $dt = DateTimeImmutable::createFromFormat(
            'Y-n-j G:i:s.v',
            sprintf('%d-%d-%d %d:%02d:%02d.%03d', $year, $month, $day, $hours, $min, $sec, $ms),
            $tz
        );

        if ($dt === false) {
            $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hours, $min, $sec), $tz);
            $dt = $dt->modify('+' . $ms . ' milliseconds');
        }

        return $this->dateTimeToTimestamp($dt);
    }

    /**
     * Resolves a mixed date input into a millisecond timestamp.
     * @param int|float|string|\DateTimeInterface|self|null $date The date value to resolve.
     * @return int Milliseconds since Unix epoch.
     */
    private function resolveTimestamp(int|float|string|DateTimeInterface|self|null $date): int
    {
        if ($date === null) {
            return (int) floor(microtime(true) * 1000);
        }

        if ($date instanceof self) {
            return $date->getTime();
        }

        if ($date instanceof DateTimeInterface) {
            return $this->dateTimeToTimestamp($date);
        }

        if (is_int($date) || is_float($date)) {
            return (int) $date;
        }

        return $this->parseToTimestamp($date);
    }

    /**
     * Parses a date string into a millisecond timestamp.
     * @param string $date The date string (ISO date-only uses UTC midnight).
     * @return int Milliseconds since Unix epoch.
     */
    private function parseToTimestamp(string $date): int
    {
        // Date-only ISO -> UTC midnight (same as ES5 Date.parse / new Date('YYYY-MM-DD'))
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $dt = new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));

            return $this->dateTimeToTimestamp($dt);
        }

        try {
            $dt = new DateTimeImmutable($date);

            return $this->dateTimeToTimestamp($dt);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('JalaliX: invalid date "' . $date . '"', 0, $e);
        }
    }
}
