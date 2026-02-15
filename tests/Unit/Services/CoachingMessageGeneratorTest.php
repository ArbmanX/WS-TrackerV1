<?php

use App\Services\PlannerMetrics\CoachingMessageGenerator;

beforeEach(function () {
    $this->generator = new CoachingMessageGenerator;
});

test('it returns null for on-pace planner', function () {
    $result = $this->generator->generate([
        'gap_miles' => 0,
        'last_week_miles' => 7.0,
        'streak_weeks' => 1,
        'status' => 'success',
        'days_since_last_edit' => 2,
    ]);

    expect($result)->toBeNull();
});

test('it returns encouraging message for small gap', function () {
    $result = $this->generator->generate([
        'gap_miles' => 1.8,
        'last_week_miles' => 5.0,
        'streak_weeks' => 0,
        'status' => 'warning',
        'days_since_last_edit' => 2,
    ]);

    expect($result)->toContain('1.8 mi away');
});

test('it returns recovery message for large gap', function () {
    $result = $this->generator->generate([
        'gap_miles' => 4.5,
        'last_week_miles' => 3.2,
        'streak_weeks' => 0,
        'status' => 'error',
        'days_since_last_edit' => 2,
    ]);

    expect($result)->toContain('3.2 mi');
});

test('it returns nudge message when behind and stale edits', function () {
    $result = $this->generator->generate([
        'gap_miles' => 2.0,
        'last_week_miles' => 5.0,
        'streak_weeks' => 0,
        'status' => 'warning',
        'days_since_last_edit' => 5,
    ]);

    expect($result)->toContain('5 days ago');
});

test('it returns celebration message for streak milestone', function () {
    $result = $this->generator->generate([
        'gap_miles' => 0,
        'last_week_miles' => 7.5,
        'streak_weeks' => 4,
        'status' => 'success',
        'days_since_last_edit' => 1,
    ]);

    expect($result)->toContain('4 weeks on target');
});

test('it prioritizes nudge over encouraging when both apply', function () {
    $result = $this->generator->generate([
        'gap_miles' => 1.5,
        'last_week_miles' => 5.0,
        'streak_weeks' => 0,
        'status' => 'warning',
        'days_since_last_edit' => 6,
    ]);

    // Nudge wins: gap > 0 AND days >= 4
    expect($result)->toContain('6 days ago')
        ->not->toContain('mi away');
});

test('it uses actual planner data in message text', function () {
    $result = $this->generator->generate([
        'gap_miles' => 2.3,
        'last_week_miles' => 4.1,
        'streak_weeks' => 0,
        'status' => 'warning',
        'days_since_last_edit' => 1,
    ]);

    // Encouraging: gap < 3, no stale edit
    expect($result)->toContain('2.3');
});
