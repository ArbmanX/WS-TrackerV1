<?php

use App\Services\WorkStudio\Shared\Helpers\WSSQLCaster;

test('cast ole_datetime field returns SQL with default format', function () {
    $sql = WSSQLCaster::cast('SS.EDITDATE');

    expect($sql)
        ->toContain('SS.EDITDATE')
        ->toContain("AT TIME ZONE 'UTC'")
        ->toContain("AT TIME ZONE 'Eastern Standard Time'")
        ->toContain('yyyy-MM-dd HH:mm:ss')
        ->not->toContain('DATEADD');
});

test('cast ole_datetime field returns SQL with custom format', function () {
    $sql = WSSQLCaster::cast('SS.EDITDATE', 'MM/dd/yyyy h:mm tt');

    expect($sql)
        ->toContain('SS.EDITDATE')
        ->toContain('MM/dd/yyyy h:mm tt')
        ->not->toContain('yyyy-MM-dd HH:mm:ss');
});

test('cast strips table prefix for field lookup', function () {
    $withPrefix = WSSQLCaster::cast('SS.EDITDATE');
    $withoutPrefix = WSSQLCaster::cast('EDITDATE');

    // Both should produce FORMAT(...) SQL, not pass through
    expect($withPrefix)->toContain('FORMAT(')
        ->and($withoutPrefix)->toContain('FORMAT(');
});

test('cast unknown field returns column unchanged', function () {
    expect(WSSQLCaster::cast('UNKNOWN_FIELD'))->toBe('UNKNOWN_FIELD');
    expect(WSSQLCaster::cast('SS.UNKNOWN_FIELD'))->toBe('SS.UNKNOWN_FIELD');
});

test('cast date field returns column unchanged', function () {
    expect(WSSQLCaster::cast('STAKEDDATE'))->toBe('STAKEDDATE');
    expect(WSSQLCaster::cast('V.CREATEDATE'))->toBe('V.CREATEDATE');
});

test('cast does not include -2 day offset', function () {
    $sql = WSSQLCaster::cast('SS.EDITDATE');

    expect($sql)
        ->not->toContain('DATEADD')
        ->not->toContain('-2');
});

test('cast handles all registered ole_datetime fields', function (string $field) {
    $sql = WSSQLCaster::cast($field);
    expect($sql)->toContain('FORMAT(');
})->with(['EDITDATE', 'TAKENDATE', 'HISTORYDATE', 'ASSDDATE']);

test('cast handles all registered date fields', function (string $field) {
    expect(WSSQLCaster::cast($field))->toBe($field);
})->with(['STAKEDDATE', 'CREATEDATE', 'COMPLETEDDATE']);

test('oleDateToCarbon converts known OLE value correctly', function () {
    // 46056.7975671181 = 2026-02-03 19:08:30 UTC (0.7975671181 * 86400 ≈ 68910s)
    $carbon = WSSQLCaster::oleDateToCarbon(46056.7975671181);

    expect($carbon->format('Y-m-d H:i:s'))->toBe('2026-02-03 19:08:30')
        ->and($carbon->timezone->getName())->toBe('UTC');
});

test('oleDateToCarbon handles zero', function () {
    $carbon = WSSQLCaster::oleDateToCarbon(0.0);

    expect($carbon->format('Y-m-d'))->toBe('1899-12-30');
});

test('oleDateToCarbon handles integer-only dates', function () {
    // Day 1 = Dec 31, 1899
    $carbon = WSSQLCaster::oleDateToCarbon(1.0);
    expect($carbon->format('Y-m-d'))->toBe('1899-12-31');

    // Day 2 = Jan 1, 1900
    $carbon = WSSQLCaster::oleDateToCarbon(2.0);
    expect($carbon->format('Y-m-d'))->toBe('1900-01-01');
});
