<?php
// --- LÓGICA DE PHP PARA EL PANEL DE ADMIN ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Mexico_City');

function simulate_initiate_outgoing_payment($user_wallet, $amount) { return true; }

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
                    if (simulate_initiate_outgoing_payment($_SESSION['users'][$order['user_name']]['wallet_url'], $order['total'])) {
                        $order['status'] = 'preparing';
                    } else { $order['status'] = 'payment_failed'; }
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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Cocina Central</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: #e9ecef; color: #212529; }
        .login-container { display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-box { background: #fff; padding: 40px; border-radius: 0.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; width: 100%; max-width: 350px; }
        .btn { background-color: #0d6efd; color: white; border: none; padding: 12px 18px; border-radius: 0.5rem; cursor: pointer; font-weight: 600; transition: all 0.2s; width: 100%; font-size: 1rem; margin-top: 20px; }
        .header { background: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .btn-logout { background-color: #6c757d; width: auto; margin-top: 0; }
        .dashboard-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 30px; padding: 30px; }
        .column h2 { font-size: 1.2rem; font-weight: 600; text-transform: uppercase; color: #6c757d; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        .order-card { background: #fff; border-radius: 0.75rem; padding: 20px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); margin-bottom: 20px; }
        .order-card-header { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .order-card ul { list-style: none; padding: 0; margin: 0 0 15px 0; }
        .order-card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px; }
        .btn-accept { background-color: #198754; }
        .btn-reject { background-color: #dc3545; }
        .order-card.ready { border-left: 5px solid #198754; }
        .no-orders { color: #6c757d; text-align: center; padding: 30px; background: #fff; border-radius: 0.75rem; }
    </style>
</head>
<body>
    <?php if (!$_SESSION['admin_logged_in']): ?>
        <div class="login-container">
            <div class="login-box">
                <h1>Cocina Central</h1>
                <form method="post">
                    <input type="password" name="password" placeholder="Contraseña Maestra" required style="width:100%; padding:12px; border-radius:8px; border:1px solid #ccc;">
                    <button type="submit" name="login_admin" class="btn">Ingresar</button>
                    <?php if(isset($admin_login_error)): ?><p style="color:#dc3545; margin-top:15px;"><?= $admin_login_error ?></p><?php endif; ?>
                </form>
            </div>
        </div>
    <?php else: ?>
        <header class="header"><h1>Dashboard de Pedidos</h1><form method="post"><button type="submit" name="logout_admin" class="btn btn-logout">Salir</button></form></header>
        <div class="dashboard-container">
            <div class="column">
                <h2>Nuevas Solicitudes (<?= count($pending_orders) ?>)</h2>
                <?php if(empty($pending_orders)): ?> <p class="no-orders">No hay pedidos nuevos.</p> <?php endif; ?>
                <?php foreach(array_reverse($pending_orders) as $order): ?>
                    <div class="order-card">
                        <div class="order-card-header"><h3>#<?= substr($order['id'], -6) ?></h3><span><?= $order['pickup_time'] ?></span></div>
                        <p><strong>Cliente:</strong> <?= ucfirst(htmlspecialchars($order['user_name'])) ?></p>
                        <ul><?php foreach($order['items'] as $item): ?><li><span>(<?= $item['quantity'] ?>) <?= $item['name'] ?></span></li><?php endforeach; ?></ul>
                        <strong>Total: $<?= number_format($order['total'], 2) ?></strong>
                        <div class="order-card-actions">
                            <form method="post"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><button type="submit" name="reject_order" class="btn btn-reject">Rechazar</button></form>
                            <form method="post"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><button type="submit" name="accept_order" class="btn btn-accept">Aceptar y Cobrar</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="column">
                <h2>En Preparación (<?= count($preparing_orders) ?>)</h2>
                 <?php if(empty($preparing_orders)): ?> <p class="no-orders">Ningún pedido en preparación.</p> <?php endif; ?>
                <?php foreach(array_reverse($preparing_orders) as $order): ?>
                     <div class="order-card">
                        <div class="order-card-header"><h3>#<?= substr($order['id'], -6) ?></h3><span><?= $order['pickup_time'] ?></span></div>
                        <p><strong>Cliente:</strong> <?= ucfirst(htmlspecialchars($order['user_name'])) ?></p>
                        <form method="post" style="margin-top: 20px;"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><button type="submit" name="mark_ready" class="btn">Marcar como Listo</button></form>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="column">
                <h2>Listos para Recoger (<?= count($ready_orders) ?>)</h2>
                <?php if(empty($ready_orders)): ?> <p class="no-orders">Ningún pedido listo.</p> <?php endif; ?>
                <?php foreach(array_reverse($ready_orders) as $order): ?>
                     <div class="order-card ready">
                         <div class="order-card-header"><h3>#<?= substr($order['id'], -6) ?></h3><span><?= $order['pickup_time'] ?></span></div>
                         <p><strong>Cliente:</strong> <?= ucfirst(htmlspecialchars($order['user_name'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <script>
        let refreshTimeout;
        document.addEventListener('mousemove', () => {
            clearTimeout(refreshTimeout);
            refreshTimeout = setTimeout(() => window.location.reload(), 1000);
        });
    </script>
</body>
</html>