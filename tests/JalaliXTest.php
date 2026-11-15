<?php

declare(strict_types=1);

namespace Jalalix\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use Jalalix\JalaliX;
use PHPUnit\Framework\TestCase;

final class JalaliXTest extends TestCase
{
    public function testGoldenCreateFormat(): void
    {
        $this->assertSame(
            '1403/12/07',
            JalaliX::create('2025-02-25')->format('YYYY/MM/DD')
        );
    }

    public function testFormatPersianDigits(): void
    {
        $this->assertSame(
            '۰۷ اسفند ۱۴۰۳',
            JalaliX::create('2025-02-25')->format('DD MMM YYYY', ['persianDigits' => true])
        );
    }

    public function testParseJalaliToIsoDate(): void
    {
        $this->assertSame(
            '2025-02-25',
            JalaliX::parseJalali('1403/12/07')->toISODate()
        );
    }

    public function testParseJalaliPersianDigitsAndTime(): void
    {
        $d = JalaliX::parseJalali('۱۴۰۳-۱۲-۰۷ 14:30');
        $this->assertSame('1403/12/07', $d->format('YYYY/MM/DD'));
        $this->assertSame(14, $d->getHours());
        $this->assertSame(30, $d->getMinutes());
    }

    public function testToIsoDateJalali(): void
    {
        $this->assertSame(
            '1403-12-07',
            JalaliX::create('2025-02-25')->toISODate('jalali')
        );
    }

    public function testToPersianFromPersian(): void
    {
        $this->assertSame('۱۴۰۳/۱۲/۰۷', JalaliX::toPersian('1403/12/07'));
        $this->assertSame('1403/12/07', JalaliX::fromPersian('۱۴۰۳/۱۲/۰۷'));
        $this->assertSame('1403/12/07', JalaliX::fromPersian('١٤٠٣/١٢/٠٧'));
    }

    public function testParseJalaliDotSeparator(): void
    {
        $this->assertSame(
            '2025-02-25',
            JalaliX::parseJalali('1403.12.07')->toISODate()
        );
    }

    public function testNormalizeQueryDateSentinelsAndTimestamps(): void
    {
        $this->assertNull(JalaliX::normalizeQueryDate(null));
        $this->assertNull(JalaliX::normalizeQueryDate('null'));
        $this->assertNull(JalaliX::normalizeQueryDate(''));
        $this->assertNull(JalaliX::normalizeQueryDate('undefined'));
        $this->assertNull(JalaliX::normalizeQueryDate('Invalid Date'));
        $this->assertNull(JalaliX::normalizeQueryDate('NaN'));
        $this->assertNull(JalaliX::normalizeQueryDate(0));
        $this->assertNull(JalaliX::normalizeQueryDate(-1));

        $seconds = 1740430800; // 2025-02-24 21:00:00 UTC / 2025-02-25 00:30:00 Tehran
        $this->assertSame(
            '2025-02-24 21:00:00',
            JalaliX::normalizeQueryDate($seconds, ['timezone' => 'UTC'])
        );
        $this->assertSame(
            '2025-02-25 00:30:00',
            JalaliX::normalizeQueryDate($seconds * 1000, ['timezone' => 'Asia/Tehran'])
        );
    }

    public function testNormalizeQueryDateJalaliAndGregorian(): void
    {
        $this->assertSame(
            '2025-02-25 00:00:00',
            JalaliX::normalizeQueryDate('1403/12/07', ['timezone' => 'UTC'])
        );
        $this->assertSame(
            '2025-02-25 14:30:00',
            JalaliX::normalizeQueryDate('۱۴۰۳-۱۲-۰۷ 14:30:00', ['timezone' => 'UTC'])
        );
        $this->assertSame(
            '2025-02-25 00:00:00',
            JalaliX::normalizeQueryDate('2025.02.25')
        );
        $this->assertSame(
            '2025-02-25 12:30:45',
            JalaliX::normalizeQueryDate('2025-02-25 12:30:45.123Z')
        );
        $this->assertSame(
            'not-a-date',
            JalaliX::normalizeQueryDate('not-a-date')
        );

        // Wider default band than the old 1278–1600 heuristic.
        $this->assertSame(
            '2025-02-25 00:00:00',
            JalaliX::normalizeQueryDate('1403/12/07', ['timezone' => 'UTC'])
        );
        $this->assertNotNull(JalaliX::normalizeQueryDate('1200/01/01', ['timezone' => 'UTC']));
        $this->assertNotNull(JalaliX::normalizeQueryDate('1800/01/01', ['timezone' => 'UTC']));

        // Gregorian years stay Gregorian; override bounds when needed.
        $this->assertSame('2025-01-01 00:00:00', JalaliX::normalizeQueryDate('2025-01-01'));
        $this->assertNotSame(
            '2025-01-01 00:00:00',
            JalaliX::normalizeQueryDate('2025-01-01', [
                'timezone' => 'UTC',
                'jalaliYearMin' => 1,
                'jalaliYearMax' => 3000,
            ])
        );
    }

    public function testCompareAndClone(): void
    {
        $a = JalaliX::create('2025-02-25');
        $b = JalaliX::create('2025-02-26');
        $this->assertTrue(JalaliX::compare($a, $b));
        $this->assertFalse(JalaliX::compare($b, $a));

        $c = $a->clone();
        $this->assertSame($a->getTime(), $c->getTime());
        $c->addDays(1);
        $this->assertNotSame($a->getTime(), $c->getTime());
    }

    public function testIsValidDate(): void
    {
        $this->assertTrue(JalaliX::isValidDate(new DateTimeImmutable()));
        $this->assertTrue(JalaliX::isValidDate(JalaliX::create('2025-02-25')));
        $this->assertFalse(JalaliX::isValidDate('2025-02-25'));
        $this->assertFalse(JalaliX::isValidDate(null));
    }

    public function testGettersMonthConvention(): void
    {
        $d = JalaliX::create('2025-02-25');
        $d->setFullYear(1404, 0, 1);
        $this->assertSame(1404, $d->getFullYear());
        $this->assertSame(1, $d->getMonth());
        $this->assertSame(1, $d->getDate());
    }

    public function testAddAndStartOfMonth(): void
    {
        $formatted = JalaliX::create('2025-02-25')
            ->addMonths(1)
            ->getStartOfMonth()
            ->format('YYYY/MM/DD');

        $this->assertSame('1404/01/01', $formatted);
    }

    public function testFormatGregorianCalendar(): void
    {
        $this->assertSame(
            '25 February 2025',
            JalaliX::create('2025-02-25')->format('DD MMM YYYY', ['calendar' => 'gregorian'])
        );
    }

    public function testFormatPositionalApi(): void
    {
        $d = JalaliX::create('2025-02-25');
        $this->assertSame('۱۴۰۳/۱۲/۰۷', $d->format('YYYY/MM/DD', true));
        $this->assertSame('2025/02/25', $d->format('YYYY/MM/DD', false, true));
    }

    public function testLocaleDateString(): void
    {
        $this->assertSame(
            '1403/12/07',
            JalaliX::create('2025-02-25')->toLocaleDateString()
        );
    }

    public function testParseJalaliInvalidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JalaliX::parseJalali('not-a-date');
    }

    public function testSetTimezoneReturnsNewInstance(): void
    {
        $a = JalaliX::create('2025-02-25T12:00:00+00:00');
        $b = $a->setTimezone('UTC');
        $this->assertNotSame($a, $b);
        $this->assertSame('UTC', $b->getTimezone());
    }

    public function testGetDayOfYear(): void
    {
        // 1403/01/01
        $d = JalaliX::parseJalali('1403/01/01');
        $this->assertSame(1, $d->getDayOfYear());
    }

    // Golden cases from testdata/timezone.json (instant-preserving contract).
    public function testTimezoneTehranOffsetInstant(): void
    {
        $ms = 1740430800000;
        $d = JalaliX::create($ms)->setTimezone('Asia/Tehran');
        $this->assertSame($ms, $d->getTime());
        $this->assertSame('Asia/Tehran', $d->getTimezone());
        $this->assertSame('1403/12/07', $d->format('YYYY/MM/DD'));
        $this->assertSame('00:30:00', $d->format('HH:mm:ss'));
        $this->assertSame('2025-02-25', $d->toISODate());
        $this->assertSame('1403-12-07', $d->toISODate('jalali'));
    }

    public function testTimezoneSameInstantUtc(): void
    {
        $ms = 1740430800000;
        $d = JalaliX::create($ms)->setTimezone('UTC');
        $this->assertSame($ms, $d->getTime());
        $this->assertSame('1403/12/06', $d->format('YYYY/MM/DD'));
        $this->assertSame('21:00:00', $d->format('HH:mm:ss'));
        $this->assertSame('2025-02-24', $d->toISODate());
    }

    public function testTimezoneUtcNoonTehran(): void
    {
        $d = JalaliX::create(1740484800000)->setTimezone('Asia/Tehran');
        $this->assertSame('1403/12/07', $d->format('YYYY/MM/DD'));
        $this->assertSame('15:30:00', $d->format('HH:mm:ss'));
    }

    public function testTimezoneAliasUtc(): void
    {
        $d = JalaliX::create(1740484800000)->setTimezone('utc');
        $this->assertSame('UTC', $d->getTimezone());
    }

    public function testTimezoneInstantPreservingAndInvalid(): void
    {
        $ms = 1740430800000;
        $d = JalaliX::create($ms);
        $tehran = $d->setTimezone('Asia/Tehran');
        $utc = $d->setTimezone('UTC');
        $this->assertSame($ms, $tehran->getTime());
        $this->assertSame($ms, $utc->getTime());
        $this->assertNotSame($tehran->format('YYYY/MM/DD'), $utc->format('YYYY/MM/DD'));
        $this->expectException(InvalidArgumentException::class);
        $d->setTimezone('Mars/Olympus');
    }

    public function testCloneKeepsTimezone(): void
    {
        $a = JalaliX::create(1740430800000)->setTimezone('Asia/Tehran');
        $b = $a->clone();
        $this->assertSame('Asia/Tehran', $b->getTimezone());
        $this->assertSame('00:30:00', $b->format('HH:mm:ss'));
    }
}
