<?php
require_once __DIR__ . '/registration/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/geo_location.php';

unset($_SESSION['user_location']);
unset($_SESSION['location_timestamp']);

$locator = new GeoLocator();
$location = $locator->getLocation();

if ($location && !empty($location['country'])) {
    $_SESSION['user_location'] = $location;
    $_SESSION['location_timestamp'] = time();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'country' => $location['country'],
        'city' => $location['city'],
        'region' => $location['region'],
        'country_code' => $location['country_code']
    ]);
} else {
    $_SESSION['user_location'] = [
        'ip' => $locator->ip,
        'country' => 'Selecionar localização', 
        'country_code' => '',
        'region' => '',
        'city' => '', 
        'latitude' => 0,
        'longitude' => 0,
        'timezone' => '',
        'source' => 'Default'
    ];
    $_SESSION['location_timestamp'] = time();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'country' => 'Selecionar localização',
        'message' => 'Não foi possível detectar sua localização automaticamente'
    ]);
}
?>