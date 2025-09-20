<?php
// --- LÓGICA DE PHP PARA EL PANEL DE ADMIN ---

// Incluir el autoload de Composer para usar Guzzle
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Mexico_City');

// --- NUEVA FUNCIÓN PARA PAGOS REALES CON OPEN PAYMENTS ---
// Esta función crea una solicitud de PAGO ENTRANTE (INCOMING PAYMENT)
// El restaurante (receptor) crea esta solicitud para que el cliente (remitente) pueda pagarla.
function initiate_incoming_payment($customer_wallet_url, $amount, $asset_code, $asset_scale) {
    // URL del servidor de recursos del restaurante (donde se crean los pagos)
    // DEBES REEMPLAZAR ESTO CON LA URL DE TU PROPIA WALLET DE RESTAURANTE
    $restaurant_wallet_url = 'https://ilp.interledger-test.dev/goku'; // EJEMPLO: Wallet del receptor

    $client = new Client();

    try {
        // 1. Obtener los detalles de la wallet del restaurante (el receptor)
        $response = $client->get($restaurant_wallet_url, ['headers' => ['Accept' => 'application/json']]);
        $receivingWalletAddress = json_decode($response->getBody(), true);

        // 2. Solicitar una concesión (grant) para crear un pago entrante
        // Esto le da permiso a nuestro script para crear una solicitud de pago en nombre del restaurante.
        $grant_request_body = [
            'access_token' => [
                'access' => [
                    [
                        'type' => 'incoming-payment',
                        'actions' => ['create', 'read', 'list'],
                    ]
                ]
            ]
        ];
        $grant_response = $client->post(
            $receivingWalletAddress['authServer'],
            [
                'json' => $grant_request_body,
                'headers' => ['Content-Type' => 'application/json']
            ]
        );
        $incomingPaymentGrant = json_decode($grant_response->getBody(), true);
        $access_token = $incomingPaymentGrant['access_token']['value'];

        // 3. Crear el Pago Entrante (Incoming Payment)
        // Esta es la "factura" o "solicitud de pago" que el cliente pagará.
        $incoming_payment_body = [
            'walletAddress' => $restaurant_wallet_url,
            'incomingAmount' => [
                'assetCode' => $asset_code, // ej: 'USD'
                'assetScale' => $asset_scale, // ej: 2
                'value' => (string)($amount * pow(10, $asset_scale)) // Convertir a la unidad más pequeña (centavos)
            ],
            'expiresAt' => (new DateTime('+1 hour'))->format(DateTime::RFC3339) // El pago expira en 1 hora
        ];

        $payment_response = $client->post(
            $receivingWalletAddress['resourceServer'] . '/incoming-payments',
            [
                'headers' => [
                    'Authorization' => 'GNAP ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'json' => $incoming_payment_body
            ]
        );
        
        $incomingPayment = json_decode($payment_response->getBody(), true);
        
        // El pago entrante se creó con éxito.
        // En un escenario real más complejo, aquí iniciarías el pago saliente desde la wallet del cliente
        // usando el `id` de $incomingPayment. Por simplicidad, aquí solo confirmamos la creación.
        error_log("Incoming Payment creado con éxito: " . $incomingPayment['id']);
        return true;

    } catch (RequestException $e) {
        // Si hay un error en la comunicación con la API, lo registramos.
        error_log($e->getMessage());
        if ($e->hasResponse()) {
            error_log($e->getResponse()->getBody()->getContents());
        }
        return false;
    }
}


$admin_password = "admin123";
if (!isset($_SESSION['admin_logged_in'])) { $_SESSION['admin_logged_in'] = false; }
if (isset($_SESSION['users']) && !isset($_SESSION['orders'])) { $_SESSION['orders'] = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_admin'])) {
        if ($_POST['password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php'); exit;
        } else { $admin_login_error = "Contraseña incorrecta."; }
    }
    if (isset($_POST['logout_admin'])) { $_SESSION['admin_logged_in'] = false; header('Location: admin.php'); exit; }

    if ($_SESSION['admin_logged_in'] && isset($_POST['order_id'])) {
        $order_id_to_update = $_POST['order_id'];
        foreach ($_SESSION['orders'] as &$order) { 
            if ($order['id'] === $order_id_to_update) {
                if (isset($_POST['accept_order'])) {
                    // --- LÓGICA DE PAGO ACTUALIZADA ---
                    $customer_wallet_url = $_SESSION['users'][$order['user_name']]['wallet_url'];
                    $order_total = $order['total'];
                    
                    // Asumimos un código y escala de activos. Esto podría venir de la configuración de la wallet.
                    $asset_code = 'USD'; 
                    $asset_scale = 2;

                    // Llamamos a la nueva función de pago real
                    if (initiate_incoming_payment($customer_wallet_url, $order_total, $asset_code, $asset_scale)) {
                        $order['status'] = 'preparing';
                    } else { 
                        $order['status'] = 'payment_failed'; 
                    }
                }
                if (isset($_POST['reject_order'])) { $order['status'] = 'rejected'; }
                if (isset($_POST['mark_ready'])) { $order['status'] = 'ready'; }
                header('Location: admin.php'); exit;
            }
        }
        unset($order);
    }
}

// Filtrar pedidos por estado
$pending_orders = []; $preparing_orders = []; $ready_orders = [];
if (isset($_SESSION['orders']) && is_array($_SESSION['orders'])) {
    $pending_orders = array_filter($_SESSION['orders'], function($o){ return $o['status'] === 'pending_approval'; });
    $preparing_orders = array_filter($_SESSION['orders'], function($o){ return $o['status'] === 'preparing'; });
    $ready_orders = array_filter($_SESSION['orders'], function($o){ return $o['status'] === 'ready'; });
}
?>
<!-- El resto del HTML permanece exactamente igual -->
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- ... tu head ... -->
</head>
<body>
    <?php if (!$_SESSION['admin_logged_in']): ?>
        <!-- ... tu formulario de login ... -->
    <?php else: ?>
        <!-- ... tu dashboard ... -->
    <?php endif; ?>
    <!-- ... tu script ... -->
</body>
</html>
