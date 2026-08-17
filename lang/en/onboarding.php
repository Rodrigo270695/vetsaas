<?php

return [

    'requires_sede' => 'We recommend creating at least one active branch with a location.',
    'requires_sede_geo' => 'Complete department, province and district on your active branch.',

    'banner' => [
        'title' => 'Set up your clinic',
        'subtitle' => 'Complete these steps to get VetSaaS ready. You can explore the platform; with a branch you’ll set up register, appointments, and inventory more clearly.',
        'progress' => ':completed of :total steps',
        'required_badge' => 'Recommended',
        'locked_hint' => 'Best to complete the branch step first',
        'cta' => 'Go to setup',
        'completed' => 'Done',
    ],

    'steps' => [
        'sede' => [
            'title' => 'Create your first branch',
            'description' => 'Where you provide care. Recommended to operate clearly.',
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
