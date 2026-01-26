<?php
// config/constants.php

return [
    'category_labels' => [
        'reciclavel' => ['icon' => '♻️', 'label' => 'Reciclável'],
        'sustentavel' => ['icon' => '🌿', 'label' => 'Sustentável'],
        'servico' => ['icon' => '🛠️', 'label' => 'Serviços'],
        'visiongreen' => ['icon' => '🌱', 'label' => 'VisionGreen'],
        'ecologico' => ['icon' => '🌍', 'label' => 'Ecológico'],
        'outro' => ['icon' => '📦', 'label' => 'Outros']
    ],
    
    'price_ranges' => [
        ['min' => 0, 'max' => 1000, 'label' => 'Até 1.000 MZN'],
        ['min' => 1000, 'max' => 5000, 'label' => '1.000 - 5.000 MZN'],
        ['min' => 5000, 'max' => 10000, 'label' => '5.000 - 10.000 MZN'],
        ['min' => 10000, 'max' => 999999, 'label' => 'Acima de 10.000 MZN']
    ],
    
    'status_map' => [
        'pendente' => ['icon' => '⏳', 'label' => 'Pendente', 'color' => 'warning'],
        'confirmado' => ['icon' => '✓', 'label' => 'Confirmado', 'color' => 'info'],
        'processando' => ['icon' => '⚙️', 'label' => 'Processando', 'color' => 'primary'],
        'enviado' => ['icon' => '🚚', 'label' => 'Enviado', 'color' => 'accent'],
        'entregue' => ['icon' => '✅', 'label' => 'Entregue', 'color' => 'success'],
        'cancelado' => ['icon' => '❌', 'label' => 'Cancelado', 'color' => 'danger']
    ],
    
    'payment_status_map' => [
        'pendente' => ['icon' => '⏳', 'label' => 'Aguardando', 'color' => 'warning'],
        'pago' => ['icon' => '✓', 'label' => 'Pago', 'color' => 'success'],
        'parcial' => ['icon' => '⚠️', 'label' => 'Parcial', 'color' => 'warning'],
        'reembolsado' => ['icon' => '↩️', 'label' => 'Reembolsado', 'color' => 'info']
    ],
    
    'payment_method_map' => [
        'mpesa' => ['icon' => '📱', 'label' => 'M-Pesa'],
        'emola' => ['icon' => '💳', 'label' => 'E-Mola'],
        'visa' => ['icon' => '💳', 'label' => 'Visa'],
        'mastercard' => ['icon' => '💳', 'label' => 'Mastercard'],
        'manual' => ['icon' => '💵', 'label' => 'Pagamento Manual']
    ]
];