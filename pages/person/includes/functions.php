<?php
// includes/functions.php

/**
 * Retorna informações de status formatadas
 */
function getStatusInfo($status) {
    $statusMap = [
        'pendente' => [
            'label' => 'Pendente',
            'class' => 'status-warning',
            'icon' => 'fa-clock',
            'color' => '#f59e0b'
        ],
        'confirmado' => [
            'label' => 'Confirmado',
            'class' => 'status-info',
            'icon' => 'fa-check-circle',
            'color' => '#3b82f6'
        ],
        'processando' => [
            'label' => 'Processando',
            'class' => 'status-primary',
            'icon' => 'fa-spinner',
            'color' => '#8b5cf6'
        ],
        'enviado' => [
            'label' => 'Enviado',
            'class' => 'status-accent',
            'icon' => 'fa-truck',
            'color' => '#06b6d4'
        ],
        'entregue' => [
            'label' => 'Entregue',
            'class' => 'status-success',
            'icon' => 'fa-check-double',
            'color' => '#10b981'
        ],
        'cancelado' => [
            'label' => 'Cancelado',
            'class' => 'status-danger',
            'icon' => 'fa-times-circle',
            'color' => '#ef4444'
        ]
    ];
    
    return $statusMap[$status] ?? [
        'label' => ucfirst($status),
        'class' => 'status-default',
        'icon' => 'fa-circle',
        'color' => '#6b7280'
    ];
}

/**
 * Retorna informações de status de pagamento
 */
function getPaymentStatusInfo($status) {
    $statusMap = [
        'pendente' => [
            'label' => 'Aguardando',
            'class' => 'status-warning',
            'icon' => 'fa-clock',
            'color' => '#f59e0b'
        ],
        'pago' => [
            'label' => 'Pago',
            'class' => 'status-success',
            'icon' => 'fa-check-circle',
            'color' => '#10b981'
        ],
        'parcial' => [
            'label' => 'Parcial',
            'class' => 'status-warning',
            'icon' => 'fa-exclamation-circle',
            'color' => '#f59e0b'
        ],
        'reembolsado' => [
            'label' => 'Reembolsado',
            'class' => 'status-info',
            'icon' => 'fa-undo',
            'color' => '#3b82f6'
        ]
    ];
    
    return $statusMap[$status] ?? [
        'label' => ucfirst($status),
        'class' => 'status-default',
        'icon' => 'fa-circle',
        'color' => '#6b7280'
    ];
}

/**
 * Retorna a melhor imagem disponível do produto
 * Prioriza imagens reais, senão cria avatar com nome do FORNECEDOR e ícone de folha
 */
function getProductImage($product) {
    // Ordem de prioridade: imagem principal > image_path1 > image_path2 > image_path3 > image_path4 > avatar do fornecedor
    
    // Lista de possíveis caminhos de imagem
    $imagePaths = [
        $product['imagem'] ?? '',
        $product['image_path1'] ?? '',
        $product['image_path2'] ?? '',
        $product['image_path3'] ?? '',
        $product['image_path4'] ?? ''
    ];
    
    // Possíveis diretórios
    $directories = [
        '../uploads/',
        'uploads/',
        '../../uploads/',
        'pages/uploads/products/',
        '../pages/uploads/products/'
    ];
    
    foreach ($imagePaths as $imagePath) {
        if (!empty($imagePath)) {
            foreach ($directories as $dir) {
                $fullPath = $dir . $imagePath;
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }
        }
    }
    
    // Fallback: gerar avatar com nome do FORNECEDOR e ícone de folha
    $companyName = $product['company_name'] ?? $product['fornecedor_nome'] ?? $product['empresa_nome'] ?? 'Fornecedor';
    
    // Gerar cor baseada no nome
    $colors = [
        '#10b981', // Verde
        '#16a34a', // Verde escuro
        '#059669', // Verde água
        '#84cc16', // Lima
        '#22c55e', // Verde claro
        '#15803d', // Verde floresta
    ];
    
    $colorIndex = abs(crc32($companyName)) % count($colors);
    $bgColor = str_replace('#', '', $colors[$colorIndex]);
    
    // URL com ícone de folha (emoji Unicode)
    return "https://ui-avatars.com/api/?name=" . urlencode($companyName) . 
           "&size=400" .
           "&background=" . $bgColor . 
           "&color=fff" . 
           "&bold=true" . 
           "&font-size=0.35" .
           "&length=2";
}

/**
 * Gera HTML de imagem de produto com fallback
 */
function renderProductImage($product, $alt = '', $class = 'product-image') {
    $imageSrc = getProductImage($product);
    $altText = !empty($alt) ? $alt : ($product['nome'] ?? 'Produto');
    $companyName = $product['company_name'] ?? $product['fornecedor_nome'] ?? 'Fornecedor';
    
    return sprintf(
        '<img src="%s" alt="%s" class="%s" loading="lazy" onerror="this.onerror=null; this.src=\'https://ui-avatars.com/api/?name=%s&size=400&background=10b981&color=fff&bold=true&font-size=0.35&length=2\'">',
        htmlspecialchars($imageSrc),
        htmlspecialchars($altText),
        htmlspecialchars($class),
        urlencode($companyName)
    );
}

/**
 * Formata valor monetário
 */
function formatCurrency($value, $currency = 'MZN') {
    return strtoupper($currency) . ' ' . number_format($value, 2, ',', '.');
}

/**
 * Calcula tempo decorrido de forma amigável
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Agora mesmo';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "Há {$mins} minuto" . ($mins > 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Há {$hours} hora" . ($hours > 1 ? 's' : '');
    } elseif ($diff < 172800) {
        return 'Ontem';
    } else {
        $days = floor($diff / 86400);
        if ($days < 30) {
            return "Há {$days} dia" . ($days > 1 ? 's' : '');
        } else {
            return date('d/m/Y', $time);
        }
    }
}

/**
 * Gera estrelas de avaliação
 */
function generateStars($rating, $max = 5) {
    $html = '<div class="stars-display">';
    $fullStars = floor($rating);
    $hasHalfStar = ($rating - $fullStars) >= 0.5;
    
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fa-solid fa-star"></i>';
    }
    
    if ($hasHalfStar) {
        $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
    }
    
    $emptyStars = $max - $fullStars - ($hasHalfStar ? 1 : 0);
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="fa-regular fa-star"></i>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Trunca texto mantendo palavras inteiras
 */
function truncateText($text, $maxLength = 100, $suffix = '...') {
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    
    $truncated = substr($text, 0, $maxLength);
    $lastSpace = strrpos($truncated, ' ');
    
    if ($lastSpace !== false) {
        $truncated = substr($truncated, 0, $lastSpace);
    }
    
    return $truncated . $suffix;
}

/**
 * Sanitiza output HTML
 */
function h($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica se usuário tem permissão
 */
function hasPermission($requiredRole) {
    if (!isset($_SESSION['auth']['role'])) {
        return false;
    }
    
    $roles = [
        'user' => 1,
        'admin' => 2,
        'superadmin' => 3
    ];
    
    $userRole = $_SESSION['auth']['role'];
    
    return ($roles[$userRole] ?? 0) >= ($roles[$requiredRole] ?? 999);
}

/**
 * Formata número de forma compacta (1K, 1M, etc)
 */
function formatCompactNumber($number) {
    if ($number < 1000) {
        return number_format($number, 0, ',', '.');
    } elseif ($number < 1000000) {
        return number_format($number / 1000, 1, ',', '.') . 'K';
    } else {
        return number_format($number / 1000000, 1, ',', '.') . 'M';
    }
}

/**
 * Gera cor baseada em string (útil para avatares)
 */
function getColorFromString($string) {
    $hash = 0;
    for ($i = 0; $i < strlen($string); $i++) {
        $hash = ord($string[$i]) + (($hash << 5) - $hash);
    }
    
    $color = '#';
    for ($i = 0; $i < 3; $i++) {
        $value = ($hash >> ($i * 8)) & 0xFF;
        $color .= str_pad(dechex($value), 2, '0', STR_PAD_LEFT);
    }
    
    return $color;
}

/**
 * Valida CPF/NUIT
 */
function validateDocument($document) {
    $document = preg_replace('/[^0-9]/', '', $document);
    
    if (strlen($document) < 9 || strlen($document) > 14) {
        return false;
    }
    
    return true;
}

/**
 * Formata telefone
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) === 9) {
        return substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5);
    }
    
    return $phone;
}

/**
 * Gera avatar SVG com ícone de folha no fundo
 * Alternativa mais personalizada ao UI Avatars
 */
function generateCompanyAvatar($companyName, $size = 300) {
    $colors = ['#10b981', '#16a34a', '#059669', '#84cc16', '#22c55e', '#15803d'];
    $colorIndex = abs(crc32($companyName)) % count($colors);
    $bgColor = $colors[$colorIndex];
    
    // Pegar iniciais (máximo 2 letras)
    $words = explode(' ', trim($companyName));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    $initials = substr($initials, 0, 2);
    
    // SVG com folha no fundo
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="' . $size . '" height="' . $size . '" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
        <!-- Background -->
        <rect width="300" height="300" fill="' . $bgColor . '"/>
        
        <!-- Folha decorativa (fundo) -->
        <g opacity="0.15">
            <path d="M150 80 Q200 100 220 150 Q200 200 150 220 Q100 200 80 150 Q100 100 150 80 Z" fill="#fff"/>
            <path d="M150 80 L150 220" stroke="#fff" stroke-width="3"/>
        </g>
        
        <!-- Texto (iniciais) -->
        <text x="150" y="180" font-family="Arial, sans-serif" font-size="100" font-weight="bold" fill="#fff" text-anchor="middle">
            ' . htmlspecialchars($initials) . '
        </text>
    </svg>';
    
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}