<?php

declare(strict_types=1);

namespace Jalalix\Tests;

use Jalalix\JalaliX;
use PHPUnit\Framework\TestCase;

// Shared golden parity tests (fixtures/golden.json -> testdata/golden.json).
final class GoldenTest extends TestCase
{
    /** @var array<string, mixed> */
    private static array $golden;

    public static function setUpBeforeClass(): void
    {
        $path = dirname(__DIR__) . '/testdata/golden.json';
        $raw = file_get_contents($path);
        self::assertNotFalse($raw, 'failed to read testdata/golden.json');
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['version'] ?? null);
        self::$golden = $data;
    }

    /** @param array<string, mixed> $spec */
    private function createFrom(array $spec): JalaliX
    {
        if (array_key_exists('unixMs', $spec)) {
            $d = JalaliX::create($spec['unixMs']);

            if (!empty($spec['timezone'])) {
                $d = $d->setTimezone($spec['timezone']);
            }

            return $d;
        }

        return JalaliX::create($spec['isoDate']);
    }

    public function testCalendar(): void
    {
        foreach (self::$golden['calendar'] as $case) {
            $d = $this->createFrom($case['create']);
            $expect = $case['expect'];
            $this->assertSame($expect['formatYYYYMMDD'], $d->format('YYYY/MM/DD'), $case['id']);
            $this->assertSame($expect['toISODateGregorian'], $d->toISODate(), $case['id']);
            $this->assertSame($expect['toISODateJalali'], $d->toISODate('jalali'), $case['id']);
        }
    }

    public function testParse(): void
    {
        foreach (self::$golden['parse'] as $case) {
            $d = JalaliX::parseJalali($case['input']);
            $expect = $case['expect'];
            $this->assertSame($expect['toISODateGregorian'], $d->toISODate(), $case['id']);

            if (isset($expect['formatYYYYMMDD'])) {
                $this->assertSame($expect['formatYYYYMMDD'], $d->format('YYYY/MM/DD'), $case['id']);
            }
        }
    }

    public function testFormat(): void
    {
        foreach (self::$golden['format'] as $case) {
            if (isset($case['toPersian'])) {
                $this->assertSame(
                    $case['toPersian']['expect'],
                    JalaliX::toPersian($case['toPersian']['input'])
                );

                $this->assertSame(
                    $case['fromPersian']['expect'],
                    JalaliX::fromPersian($case['fromPersian']['input'])
                );

                continue;
            }

            $d = $this->createFrom($case['create']);

            $this->assertSame(
                $case['expect'],
                $d->format($case['pattern'], $case['options'] ?? null),
                $case['id']
            );
        }
    }

    public function testTimezone(): void
    {
        foreach (self::$golden['timezone'] as $case) {
            if (isset($case['setTimezoneAlias'])) {
                $d = JalaliX::create($case['unixMs'])->setTimezone($case['setTimezoneAlias']);
                $this->assertSame($case['expectTimezone'], $d->getTimezone(), $case['id']);
                continue;
            }

            $d = JalaliX::create($case['unixMs'])->setTimezone($case['withTimezone']);
            $expect = $case['expect'];
            $this->assertSame($expect['jalaliSlash'], $d->format('YYYY/MM/DD'), $case['id']);

            if (isset($expect['jalaliIso'])) {
                $this->assertSame($expect['jalaliIso'], $d->toISODate('jalali'), $case['id']);
            }

            if (isset($expect['wallGregorian'])) {
                $this->assertSame($expect['wallGregorian'], $d->toISODate(), $case['id']);
            }

            if (isset($expect['wallTime'])) {
                $this->assertSame($expect['wallTime'], $d->format('HH:mm:ss'), $case['id']);
            }

            $this->assertSame($expect['hours'], $d->getHours(), $case['id']);
            $this->assertSame($expect['minutes'], $d->getMinutes(), $case['id']);

            if (isset($expect['getTime'])) {
                $this->assertSame($expect['getTime'], $d->getTime(), $case['id']);
            }
        }
    }

    public function testMutations(): void
    {
        foreach (self::$golden['mutations'] as $case) {
            $d = $this->createFrom($case['create']);

            if (isset($case['setFullYear'])) {
                $sfy = $case['setFullYear'];
                $d->setFullYear($sfy['year'], $sfy['month0'], $sfy['day']);
                $this->assertSame($case['expect']['getMonth'], $d->getMonth(), $case['id']);

                $this->assertSame(
                    $case['expect']['formatYYYYMMDD'],
                    $d->format('YYYY/MM/DD'),
                    $case['id']
                );

                continue;
            }

            foreach ($case['ops'] as $op) {
                if (str_starts_with($op, 'addMonths:')) {
                    $d = $d->addMonths((int) explode(':', $op, 2)[1]);
                } elseif ($op === 'getStartOfMonth') {
                    $d = $d->getStartOfMonth();
                }
            }

            $this->assertSame(
                $case['expect']['formatYYYYMMDD'],
                $d->format('YYYY/MM/DD'),
                $case['id']
            );
        }
    }
}
