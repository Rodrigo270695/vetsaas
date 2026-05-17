<?php

return [
    'flash' => [
        'created' => 'Prescription saved successfully.',
        'updated' => 'Prescription updated successfully.',
        'deleted' => 'Prescription deleted successfully.',
    ],
    'validation' => [
        'consulta_invalida' => 'The visit does not belong to the selected patient.',
        'consulta_cerrada' => 'You cannot link a prescription to a closed visit.',
    ],
    'pdf' => [
        'document_title' => 'Veterinary prescription',
        'subtitle' => 'Document for the pet owner. Use only as directed by your veterinarian.',
        'section_patient' => 'Patient and owner',
        'label_patient' => 'Patient',
        'label_owner' => 'Owner',
        'section_context' => 'Prescription details',
        'label_date' => 'Prescription date',
        'label_status' => 'Status',
        'label_consulta' => 'Linked visit',
        'label_vet' => 'Veterinarian',
        'label_sede' => 'Branch',
        'label_obs' => 'Notes',
        'section_meds' => 'Medications',
        'table_med' => 'Medication',
        'table_posology' => 'Dosage',
        'table_days' => 'Duration',
        'table_instructions' => 'Instructions for the owner',
        'empty_meds' => 'No medication lines on file.',
        'days_suffix' => 'days',
        'footer_generated' => 'Generated on :fecha',
        'footer_disclaimer' => 'This document does not replace verbal instructions from your veterinarian. If in doubt or if adverse reactions occur, contact the clinic.',
        'estados' => [
            'borrador' => 'Draft',
            'emitida' => 'Issued',
            'anulada' => 'Voided',
        ],
        'anulada_stamp' => 'VOIDED PRESCRIPTION',
    ],
];
