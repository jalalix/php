# JalaliX

Jalali (Persian / Shamsi) calendar for PHP. Pure PHP 8.1+, no runtime dependencies.

[![Packagist](https://img.shields.io/packagist/v/jalalix/php.svg)](https://packagist.org/packages/jalalix/php)
[![license](https://img.shields.io/packagist/l/jalalix/php.svg)](./LICENSE)

```bash
composer require jalalix/php
```

```php
use Jalalix\JalaliX;

$date = JalaliX::create('2025-02-25');

$date->format('YYYY/MM/DD'); // 1403/12/07
$date->format('DD MMM YYYY', ['persianDigits' => true]); // ۰۷ اسفند ۱۴۰۳

JalaliX::parseJalali('1403/12/07')->toISODate(); // 2025-02-25
```

`toISODate()` uses local getters. Prefer it when you need a calendar date string.

## Install

```bash
composer require jalalix/php
```

```php
use Jalalix\JalaliX;
```

## Usage

### create / format

```php
$now = JalaliX::create(new DateTimeImmutable(), ['timezone' => 'Asia/Tehran']);

$now->format('YYYY/MM/DD');
$now->format('WN، DD MMM YYYY');
$now->format('YYYY/MM/DD', ['persianDigits' => true]);
$now->format('DD MMM YYYY', ['calendar' => 'gregorian']);
```

`format` also accepts the older positional form: `format($str, $persianDigits?, $gregorian?)`.

### parseJalali

```php
JalaliX::parseJalali('1403/12/07');
JalaliX::parseJalali('۱۴۰۳-۱۲-۰۷ 14:30');
JalaliX::parseJalali('1403.12.07');
```

Named `parseJalali` to mirror the TypeScript API.

### normalizeQueryDate

Soft-normalize loose API/query date values to Gregorian `Y-m-d H:i:s` (or `null`). Does not throw on junk input.

```php
JalaliX::normalizeQueryDate('1403/12/07', ['timezone' => 'Asia/Tehran']);
// 2025-02-25 00:00:00

JalaliX::normalizeQueryDate('undefined'); // null
JalaliX::normalizeQueryDate(1740430800000, ['timezone' => 'Asia/Tehran']);
```

Detects Jalali when the year is in `1…1899` by default (below common Gregorian years); otherwise Gregorian.
Override with `jalaliYearMin` / `jalaliYearMax`.

### toISODate

```php
$d = JalaliX::create('2025-02-25T00:30:00+03:30');

$d->toISODate();          // 2025-02-25
$d->toISODate('jalali');  // 1403-12-07
```

### add / start / end

```php
JalaliX::create('2025-02-25')->addMonths(1)->getStartOfMonth()->format('YYYY/MM/DD');
```

### Persian digits

```php
JalaliX::toPersian('1403/12/07');   // ۱۴۰۳/۱۲/۰۷
JalaliX::fromPersian('۱۴۰۳/۱۲/۰۷'); // 1403/12/07
```

## Months

This is easy to get wrong:

- `getMonth()` -> 1..12 (Farvardin = 1)
- `setMonth($m)` / `setFullYear($y, $m)` -> 0..11, same as JS `Date` (Farvardin = 0)

```php
$d = JalaliX::create(new DateTimeImmutable());
$d->setFullYear(1404, 0, 1); // 1 Farvardin 1404
$d->getMonth(); // 1
```

## Timezone

Internal storage is a **UTC Unix millisecond instant** plus an IANA timezone id. `setTimezone` is **instant-preserving**: the timestamp stays the same; wall clock / Jalali day may change.

```php
$d = JalaliX::create(1740430800000);
$tehran = $d->setTimezone('Asia/Tehran');
$tehran->format('YYYY/MM/DD'); // 1403/12/07
$tehran->format('HH:mm:ss');   // 00:30:00

$utc = $d->setTimezone('UTC');
$utc->format('YYYY/MM/DD'); // 1403/12/06
$utc->format('HH:mm:ss');   // 21:00:00
```

- **Aliases:** `utc` / `gmt` -> `UTC`; `local` / `default` / `system` -> host zone (`date_default_timezone_get()`).
- **Invalid zone** -> `InvalidArgumentException`.
- Wall getters, `format`, Jalali conversion, and `toISODate` use the instance `DateTimeZone` (PHP `ext-date`; no Composer timezone packages).

## Format tokens

| Token               |                                 |
| ------------------- | ------------------------------- |
| `YYYY` `YY`         | year                            |
| `MMM` `MM` `M`      | month name / number             |
| `DD` `D`            | day                             |
| `HH` `hh` `mm` `ss` | time                            |
| `A` `a`             | meridiem (ق.ظ. / ب.ظ. or AM/PM) |
| `WN` `WL`           | weekday name / letter           |
| `w` `DDD`           | week of year / day of year      |
| `X` `x`             | unix seconds / ms               |

```php
$date->format('YYYY/MM/DD');
$date->format('YYYY/MM/DD', ['persianDigits' => true]);
$date->format('DD MMM YYYY', ['calendar' => 'gregorian']);
```

## API

Static:

- `create($date, $options?)` — timestamp (ms), ISO string, or `DateTimeInterface`
- `parseJalali($str, $options?)`
- `normalizeQueryDate($value, $options?)` — loose query dates → Gregorian `Y-m-d H:i:s` or `null`
- `isValidDate($value)`
- `toPersian($str)` / `fromPersian($str)` — `fromPersian` also accepts Arabic-Indic digits
- `compare($a, $b)` — true if `$a` is before `$b`

`$options` is `['timezone' => string]` (e.g. `'Asia/Tehran'`).

Instance (main ones):

- `format(...)`, `toISODate($calendar?)`
- `toString()`, `toDateString()`, `toLocaleDateString()`, `toLocaleTimeString()`
- `getFullYear()`, `getMonth()`, `getDate()`, `getDay()`, …
- `setFullYear`, `setMonth`, `setDate`, `setHours`, …
- `addYears`, `addMonths`, `addWeeks`, `addDays`
- `getStartOfYear` / `Month` / `Week` / `Day` and matching `getEndOf*`
- `clone()`, `setTimezone($tz)`, `getTimezone()`

## Links

- https://packagist.org/packages/jalalix/php
- https://jalalix.com
- https://github.com/jalalix/php
- [CHANGELOG.md](./CHANGELOG.md)

## License

MIT
