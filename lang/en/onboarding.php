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
            'description' => 'Where you provide care. Minimum requirement to operate.',
        ],
        'clinic' => [
            'title' => 'Clinic details',
            'description' => 'Tax ID, legal name, and logo for receipts.',
        ],
        'paciente' => [
            'title' => 'Register a patient',
            'description' => 'First pet for appointments and medical records.',
        ],
    ],

];
