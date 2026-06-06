<?php

namespace App\Console\Commands;

use App\Services\Notifications\AppointmentReminderScanner;
use App\Services\Notifications\BirthdayReminderScanner;
use App\Services\Notifications\VaccineReminderScanner;
use App\Support\Tenancy\ActiveTenantIterator;
use Illuminate\Console\Command;

class RemindersScanCommand extends Command
{
    protected $signature = 'vetsaas:reminders-scan';

    protected $description = 'Encola recordatorios automáticos (citas, vacunas, cumpleaños) por tenant';

    public function handle(
        ActiveTenantIterator $tenants,
        AppointmentReminderScanner $appointments,
        VaccineReminderScanner $vaccines,
        BirthdayReminderScanner $birthdays,
    ): int {
        $totals = ['cita_48h' => 0, 'cita_2h' => 0, 'vacuna' => 0, 'cumple' => 0];

        $tenants->each(function () use ($appointments, $vaccines, $birthdays, &$totals): void {
            $citas = $appointments->scan();
            $totals['cita_48h'] += $citas['cita_48h'];
            $totals['cita_2h'] += $citas['cita_2h'];
            $totals['vacuna'] += $vaccines->scan();
            $totals['cumple'] += $birthdays->scan();
        });

        $this->info(sprintf(
            'Encolados: %d (48h), %d (2h), %d (vacuna), %d (cumple)',
            $totals['cita_48h'],
            $totals['cita_2h'],
            $totals['vacuna'],
            $totals['cumple'],
        ));

        return self::SUCCESS;
    }
}
