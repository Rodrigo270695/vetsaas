<?php

declare(strict_types=1);

use App\Services\ClinicBot\ClinicBotAppointmentService;
use App\Support\ClinicBot\ClinicBotPeruClock;
use App\Support\ClinicBot\ClinicBotRelativeDateParser;
use Illuminate\Support\Carbon;

it('interpreta hoy manana y pasado manana en hora peru', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-25 14:00:00', ClinicBotPeruClock::TIMEZONE));

    $parser = new ClinicBotRelativeDateParser;

    expect($parser->parseDate('hoy')->toDateString())->toBe('2026-06-25')
        ->and($parser->parseDate('mañana')->toDateString())->toBe('2026-06-26')
        ->and($parser->parseDate('pasado mañana')->toDateString())->toBe('2026-06-27');
});

it('rechaza citas en el pasado respecto a peru', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-25 14:00:00', ClinicBotPeruClock::TIMEZONE));

    $parser = new ClinicBotRelativeDateParser;
    $result = $parser->parseDateTime('hoy', '10:00');

    expect($result['ok'])->toBeFalse();
});

it('acepta cita futura el mismo dia en peru', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-25 14:00:00', ClinicBotPeruClock::TIMEZONE));

    $parser = new ClinicBotRelativeDateParser;
    $result = $parser->parseDateTime('hoy', '16:30');

    expect($result['ok'])->toBeTrue()
        ->and($result['datetime']->format('Y-m-d H:i'))->toBe('2026-06-25 16:30');
});
