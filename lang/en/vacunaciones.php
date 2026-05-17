<?php

return [
    'flash' => [
        'created' => 'Vaccination recorded successfully.',
        'updated' => 'Vaccination updated successfully.',
        'deleted' => 'Vaccination deleted successfully.',
    ],
    'validation' => [
        'consulta_invalida' => 'The consultation does not belong to this patient.',
        'consulta_cerrada' => 'You cannot link an application to a consultation that is already closed.',
    ],
    'stock' => [
        'notas' => 'Vaccination: :vacuna — patient :paciente (record :id).',
        'reversion' => 'Reversal due to vaccination edit or cancellation (outbound movement :id).',
        'insufficient_stock' => 'There is not enough stock at the selected branch for this product.',
        'revert_failed' => 'Could not restore stock when deleting the vaccination.',
    ],
];
