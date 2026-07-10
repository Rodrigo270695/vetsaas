<?php

return [

    'requires_sede' => 'Create at least one active branch before using the rest of the system.',

    'banner' => [
        'title' => 'Set up your clinic',
        'subtitle' => 'Complete these steps to get VetSaaS ready. Without a branch you cannot use register, appointments, or inventory.',
        'progress' => ':completed of :total steps',
        'required_badge' => 'Required',
        'locked_hint' => 'Complete the branch step first',
        'cta' => 'Go to setup',
        'completed' => 'Done',
    ],

    'steps' => [
        'sede' => [
            'title' => 'Create your first branch',
            'description' => 'Define where you provide care. This is the minimum requirement to operate.',
        ],
        'clinic' => [
            'title' => 'Clinic details',
            'description' => 'Tax ID, legal name, and logo for receipts and communications.',
        ],
        'team' => [
            'title' => 'Invite your team',
            'description' => 'Add vets, front desk, and other clinic users.',
        ],
        'paciente' => [
            'title' => 'Register a patient',
            'description' => 'Add the first pet for appointments and medical records.',
        ],
        'activity' => [
            'title' => 'First appointment or sale',
            'description' => 'Schedule an appointment or record a sale to test the real workflow.',
        ],
        'fel' => [
            'title' => 'Electronic invoicing',
            'description' => 'Connect APISUNAT and set up series for receipts and invoices.',
        ],
    ],

];
