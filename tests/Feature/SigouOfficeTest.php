<?php

use App\Services\AgentSearchAssistant;
use App\Support\SigouOffice;

afterEach(fn () => SigouOffice::forget());

it('knows nothing about the office when the private file is missing', function () {
    config(['truehold.sigou_office' => '/nonexistent/sigou-office.json']);
    SigouOffice::forget();

    expect(SigouOffice::persona())->toBe('')
        ->and(SigouOffice::jokes())->toBe([])
        ->and(SigouOffice::lines('pokes'))->toBe([]);
});

it('hooks a crew joke from the private file on its own words only', function () {
    $file = tempnam(sys_get_temp_dir(), 'sigou');
    file_put_contents($file, json_encode([
        'persona' => 'Bob runs the kettle.',
        'jokes' => ['bob' => ['Bob and the kettle', '/\\bbob\\b/']],
        'pokes' => ['Where is Bob'],
    ]));
    config(['truehold.sigou_office' => $file]);
    SigouOffice::forget();

    $pick = new ReflectionMethod(AgentSearchAssistant::class, 'jokesForThisOne');
    $assistant = app(AgentSearchAssistant::class);

    expect(SigouOffice::persona())->toContain('Bob runs the kettle.')
        ->and(SigouOffice::lines('pokes'))->toBe(['Where is Bob'])
        ->and($pick->invoke($assistant, 'where is bob'))->toContain('Bob and the kettle')
        ->and($pick->invoke($assistant, 'double room stratford 900'))->not->toContain('Bob');

    unlink($file);
});
