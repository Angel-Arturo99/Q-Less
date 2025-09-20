<?php
// --- LÓGICA DE PHP PARA EL CLIENTE ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Mexico_City');

// --- DATOS COMPARTIDOS EN SESIÓN ---
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = [
        'cesar' => ['password' => '1234', 'wallet_url' => '$ilp.interledger-test.dev/cesar', 'authorized' => true],
        'ana' => ['password' => 'gato', 'wallet_url' => '$ilp.interledger-test.dev/ana', 'authorized' => false]
    ];
}
if (!isset($_SESSION['orders'])) {
    $_SESSION['orders'] = [];
}
$users = &$_SESSION['users'];
$orders = &$_SESSION['orders'];

// --- Datos del Restaurante y Menú ---
$restaurante = ['nombre' => 'Cafeteria ITA', 'tipo_cocina' => 'Café y Antojitos', 'imagen_url' => 'https://i.imgur.com/gucAm8y.jpeg'];
$menu = [
    101 => ['nombre' => 'Chilaquiles Rojos con Pollo', 'precio' => 65.00, 'imagen_url' => 'https://i.imgur.com/lZd3v2L.jpeg', 'prep_time' => 15],
    102 => ['nombre' => 'Torta de Jamón y Queso', 'precio' => 45.00, 'imagen_url' => 'https://i.imgur.com/uR13d5u.jpeg', 'prep_time' => 10],
    103 => ['nombre' => 'Café Americano (12 oz)', 'precio' => 25.00, 'imagen_url' => 'https://i.imgur.com/f9G8Z8J.jpeg']
];

// --- Lógica de Acciones ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $username = strtolower($_POST['username']);
        $password = $_POST['password'];
        if (isset($users[$username]) && $users[$username]['password'] === $password) {
            $_SESSION['user'] = ['name' => $username, 'wallet_url' => $users[$username]['wallet_url'], 'authorized' => $users[$username]['authorized'] ?? false];
            $_SESSION['cart'] = [];
            header('Location: index.php');
            exit;
        } else {
            $login_error = "Usuario o contraseña incorrectos.";
        }
    }

    if (isset($_POST['register'])) {
        $username = strtolower($_POST['username']);
        $password = $_POST['password'];
        $wallet_url = $_POST['wallet_url'];
        if (isset($users[$username])) {
            $register_error = "El nombre de usuario ya existe.";
        } elseif (empty($username) || empty($password) || empty($wallet_url)) {
            $register_error = "Todos los campos son obligatorios.";
        } else {
            $users[$username] = ['password' => $password, 'wallet_url' => $wallet_url, 'authorized' => false];
            $_SESSION['user'] = ['name' => $username, 'wallet_url' => $wallet_url, 'authorized' => false];
            $_SESSION['cart'] = [];
            header('Location: index.php');
            exit;
        }
    }

    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if (isset($_SESSION['user'])) {
        if (isset($_POST['authorize_wallet'])) {
            $_SESSION['user']['authorized'] = true;
            $users[$_SESSION['user']['name']]['authorized'] = true;
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['add_to_cart'])) {
            $product_id = (int)$_POST['product_id'];
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            if (isset($menu[$product_id]) && $quantity > 0) {
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$product_id] = ['name' => $menu[$product_id]['nombre'], 'price' => $menu[$product_id]['precio'], 'prep_time' => $menu[$product_id]['prep_time'] ?? 0, 'quantity' => $quantity];
                }
            }
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['update_quantity'])) {
            $product_id = (int)$_POST['product_id'];
            $change = (int)$_POST['change'];
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $change;
                if ($_SESSION['cart'][$product_id]['quantity'] <= 0) {
                    unset($_SESSION['cart'][$product_id]);
                }
            }
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['remove_item'])) {
            $product_id = (int)$_POST['product_id'];
            if (isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
            }
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['clear_cart'])) {
            $_SESSION['cart'] = [];
            header('Location: index.php');
            exit;
        }

        if (isset($_POST['place_order'])) {
            $cart_total = 0;
            foreach ($_SESSION['cart'] as $item) {
                $cart_total += $item['price'] * $item['quantity'];
            }
            $pickup_time_display = htmlspecialchars($_POST['pickup_time_display']);

            $order = [
                'id' => uniqid('ord_'),
                'user_name' => $_SESSION['user']['name'],
                'date' => (new DateTime())->format('d/m/Y h:i A'),
                'items' => $_SESSION['cart'],
                'total' => $cart_total,
                'pickup_time' => $pickup_time_display,
                'status' => 'pending_approval'
            ];
            $_SESSION['orders'][] = $order;

            $_SESSION['cart'] = [];
            header('Location: index.php?view=orders');
            exit;
        }
    }
}

// Lógica de Vistas
if (isset($_SESSION['user'])) {
    $view = $_GET['view'] ?? 'main';

    if ($view === 'main') {
        $cart_total = 0;
        $max_prep_time = 0;
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $item) {
                $cart_total += $item['price'] * $item['quantity'];
                if (isset($item['prep_time']) && $item['prep_time'] > $max_prep_time) {
                    $max_prep_time = $item['prep_time'];
                }
            }
        }
        $pickup_slots = [];
        if (!empty($_SESSION['cart'])) {
            $buffer_minutes = 5;
            $soonest_pickup_time = (new DateTime())->add(new DateInterval("PT" . ($max_prep_time + $buffer_minutes) . "M"));
            $pickup_slots['ahora'] = 'Ahora (aprox. ' . $soonest_pickup_time->format('h:i A') . ')';
            $start_slot_time = clone $soonest_pickup_time;
            $minutes = (int)$start_slot_time->format('i');
            if ($minutes % 30 != 0) {
                $add_minutes = 30 - ($minutes % 30);
                $start_slot_time->add(new DateInterval("PT{$add_minutes}M"));
            }
            for ($i = 0; $i < 24; $i++) {
                $slot_key = $start_slot_time->format('H:i');
                $pickup_slots[$slot_key] = $start_slot_time->format('h:i A');
                $start_slot_time->add(new DateInterval('PT30M'));
            }
        }
    }
    
    if ($view === 'orders' || $view === 'profile') {
        $user_orders = array_filter($_SESSION['orders'], function ($o) {
            return $o['user_name'] === $_SESSION['user']['name'];
        });
    }

} else {
    $view = $_GET['view'] ?? 'login';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Q-less Cliente</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; background-color: #f8f9fa; color: #212529; font-size: 16px; }
        .auth-container { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .auth-box { background: #ffffff; padding: 40px; border-radius: 0.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; width: 100%; max-width: 400px; }
        .auth-box h1 { margin-top: 0; font-weight: 700; }
        .auth-box .form-group { margin-bottom: 20px; text-align: left; }
        .auth-box label { font-weight: 500; display: block; margin-bottom: 8px; font-size: 0.9rem; }
        .auth-box input { width: 100%; padding: 12px; border: 1px solid #dee2e6; border-radius: 0.5rem; box-sizing: border-box; }
        .auth-switch { margin-top: 20px; } .auth-switch a { color: #0d6efd; text-decoration: none; font-weight: 600; }
        .auth-error { color: #dc3545; margin-bottom: 15px; font-weight: 500; }
        .btn { background-color: #0d6efd; color: white; border: none; padding: 12px 18px; border-radius: 0.5rem; cursor: pointer; font-weight: 600; transition: all 0.2s; width: 100%; font-size: 1rem; text-decoration: none; display: inline-block; text-align: center; box-sizing: border-box; }
        .btn:hover { background-color: #0b5ed7; transform: translateY(-2px); }
        .btn-secondary { background-color: #6c757d; }
        .btn-danger { background-color: #dc3545; color: white; padding: 2px 8px; font-size: 1rem; border-radius: 50%; line-height: 1; border: none; cursor: pointer; }
        .app-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: #ffffff; border-bottom: 1px solid #dee2e6; }
        .app-header div { display: flex; align-items: center; gap: 20px; }
        .app-header h1 { font-size: 1.5rem; margin: 0; font-weight: 700; }
        .app-header a { color: #0d6efd; text-decoration: none; font-weight: 500; }
        .container { max-width: 1280px; margin: 40px auto; padding: 0 20px; }
        .main-grid { display: grid; grid-template-columns: 1fr 400px; gap: 40px; }
        .menu-section, .cart-section, .orders-section, .profile-section { background-color: #ffffff; border-radius: 0.75rem; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); align-self: start; }
        .restaurant-header { text-align: center; margin-bottom: 32px; }
        .restaurant-header img { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; }
        .menu-item { display: grid; grid-template-columns: 80px 1fr auto; align-items: center; gap: 20px; margin-bottom: 20px; border-bottom: 1px solid #dee2e6; padding-bottom: 20px; }
        .menu-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 0.5rem; }
        .menu-item-details h3 { margin: 0 0 5px 0; }
        .price { font-weight: 600; }
        .quantity-selector { display: flex; align-items: center; gap: 8px; }
        .quantity-selector input { width: 50px; text-align: center; padding: 8px; border: 1px solid #dee2e6; border-radius: 6px; }
        .cart-item { display: grid; grid-template-columns: 1fr auto auto auto; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid #dee2e6; }
        .cart-item-controls .qty-btn { background: #f8f9fa; border: 1px solid #dee2e6; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-weight: bold; }
        .cart-total { font-size: 1.5rem; font-weight: 700; text-align: right; margin-top: 20px; border-top: 2px solid #212529; padding-top: 15px; }
        .pickup-section { margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px; }
        .pickup-section select { width: 100%; padding: 12px; border-radius: 0.5rem; border: 1px solid #dee2e6; font-size: 1rem; }
        .authorize-box { text-align: center; padding: 40px; background: #fff; border-radius: 0.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .order-status-card { padding: 20px; border-radius: 0.75rem; margin-bottom: 15px; border-left: 5px solid; }
        .order-status-card h4 { margin-top: 0; }
        .status-pending_approval { border-color: #fd7e14; background-color: #fff3cd; }
        .status-preparing { border-color: #0d6efd; background-color: #cce5ff; }
        .status-ready { border-color: #198754; background-color: #d1e7dd; }
        .status-rejected { border-color: #dc3545; background-color: #f8d7da; }
        .status-payment_failed { border-color: #dc3545; background-color: #f8d7da; }
        .profile-section { max-width: 700px; margin: auto; }
        .profile-info p { font-size: 1.1rem; line-height: 1.6; }
        .profile-info strong { color: #212529; }
        @media (max-width: 992px) { .main-grid { grid-template-columns: 1fr; } .cart-section { margin-top: 40px; } }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['user'])): ?>
        <header class="app-header">
            <div>
                <h1><a href="index.php" style="color:inherit; text-decoration:none;">Q-less</a></h1>
                <a href="index.php?view=orders">Mis Pedidos</a>
                <a href="index.php?view=profile">Mi Perfil</a>
            </div>
            <div>
                <span>Hola, <?= ucfirst(htmlspecialchars($_SESSION['user']['name'])) ?></span>
                <form method="post" style="margin:0;"><button type="submit" name="logout" class="btn btn-secondary" style="width:auto; padding:8px 12px;">Salir</button></form>
            </div>
        </header>

        <div class="container">
            <?php if ($view === 'orders'): ?>
                <div class="orders-section">
                    <h2>Mis Pedidos</h2>
                    <?php if(empty($user_orders)): ?>
                        <p>No tienes pedidos activos o en tu historial.</p>
                    <?php else: ?>
                        <?php foreach(array_reverse($user_orders) as $order): ?>
                            <div class="order-status-card status-<?= $order['status'] ?>">
                                <h4>Pedido #<?= substr($order['id'], -6) ?> (<?= $order['date'] ?>)</h4>
                                <p><strong>Estado:</strong> 
                                    <?php 
                                        switch ($order['status']) {
                                            case 'pending_approval': echo 'Esperando aprobación del restaurante...'; break;
                                            case 'preparing': echo '¡Aceptado! Tu pedido se está preparando.'; break;
                                            case 'ready': echo '¡Tu pedido está listo para recoger!'; break;
                                            case 'rejected': echo 'Lo sentimos, tu pedido fue rechazado.'; break;
                                            case 'payment_failed': echo 'El pago falló. Por favor, contacta al restaurante.'; break;
                                        }
                                    ?>
                                </p>
                                <strong>Total: $<?= number_format($order['total'], 2) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($view === 'profile'): ?>
                 <div class="profile-section">
                    <h2>Mi Perfil</h2>
                    <div class="profile-info">
                        <p><strong>Nombre de Usuario:</strong> <?= ucfirst(htmlspecialchars($_SESSION['user']['name'])) ?></p>
                        <p><strong>Wallet URL Registrada:</strong> <?= htmlspecialchars($_SESSION['user']['wallet_url']) ?></p>
                    </div>
                 </div>
            <?php else: // Vista 'main' ?>
                <?php if (!$_SESSION['user']['authorized']): ?>
                    <div class="orders-section authorize-box">
                        <h2>Activar Pedidos con 1-Clic</h2>
                        <p>Para poder ordenar, necesitas autorizar tu wallet para pagos directos. Este es un paso único.</p>
                        <form method="post"><button type="submit" name="authorize_wallet" class="btn" style="max-width: 200px; margin: 10px auto;">Autorizar Mi Wallet</button></form>
                    </div>
                <?php else: ?>
                    <div class="main-grid">
                        <div class="menu-section">
                             <div class="restaurant-header">
                                <img src="<?= htmlspecialchars($restaurante['imagen_url']) ?>">
                                <h2><?= htmlspecialchars($restaurante['nombre']) ?></h2>
                                <p><?= htmlspecialchars($restaurante['tipo_cocina']) ?></p>
                            </div>
                            <?php if (isset($payment_success)): ?><div class="payment-success" style="background-color:#d1e7dd;..."><?= $payment_success ?></div><?php endif; ?>
                            <h3>Menú del Día</h3>
                            <?php foreach ($menu as $id => $item): ?>
                                 <div class="menu-item">
                                    <img src="<?= htmlspecialchars($item['imagen_url']) ?>">
                                    <div class="menu-item-details">
                                        <h3><?= htmlspecialchars($item['nombre']) ?></h3>
                                        <?php if (isset($item['prep_time'])): ?><div style="font-size:0.8rem; color:#6c757d;">🕒 Prep. estimada: <?= $item['prep_time'] ?> min</div><?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <div class="price">$<?= number_format($item['precio'], 2) ?></div>
                                        <form method="post" class="quantity-selector">
                                            <input type="hidden" name="product_id" value="<?= $id ?>">
                                            <input type="number" name="quantity" value="1" min="1" max="20">
                                            <button type="submit" name="add_to_cart" class="btn" style="width:auto;">+</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="cart-section">
                            <h2>🛒 Tu Pedido</h2>
                            <?php if (empty($_SESSION['cart'])): ?>
                                <div style="text-align:center; padding: 40px 0; color: #6c757d;">Tu carrito está vacío.</div>
                            <?php else: ?>
                                <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                    <div class="cart-item">
                                        <span><?= htmlspecialchars($item['name']) ?></span>
                                        <div class="cart-item-controls">
                                            <form method="post" style="display:inline;"><input type="hidden" name="product_id" value="<?= $product_id ?>"><input type="hidden" name="change" value="-1"><button type="submit" name="update_quantity" class="qty-btn">-</button></form>
                                            <span><?= $item['quantity'] ?></span>
                                            <form method="post" style="display:inline;"><input type="hidden" name="product_id" value="<?= $product_id ?>"><input type="hidden" name="change" value="1"><button type="submit" name="update_quantity" class="qty-btn">+</button></form>
                                        </div>
                                        <strong>$<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
                                        <form method="post" style="display:inline;"><input type="hidden" name="product_id" value="<?= $product_id ?>"><button type="submit" name="remove_item" class="btn-danger">&times;</button></form>
                                    </div>
                                <?php endforeach; ?>
                                <div class="cart-total">Total: $<?= number_format($cart_total, 2) ?></div>
                                <form method="post">
                                    <div class="pickup-section">
                                        <label for="pickup_time" style="font-weight:bold; margin-bottom:10px; display:block;">Selecciona tu hora de recogida:</label>
                                        <p style="font-size:0.9rem; color:#555; text-align:center;">Tiempo de prep. estimado: <strong><?= $max_prep_time ?> min.</strong></p>
                                        <select name="pickup_time_display" id="pickup_time" required>
                                            <?php foreach ($pickup_slots as $display): ?><option value="<?= $display ?>"><?= $display ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="margin-top:20px;"><button type="submit" name="place_order" class="btn">Enviar Pedido</button></div>
                                </form>
                                <form method="post" style="margin-top:10px;"><button type="submit" name="clear_cart" class="btn btn-secondary">Vaciar Carrito</button></form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="auth-container">
            <div class="auth-box">
                <?php if ($view === 'register'): ?>
                    <h1>Crear Cuenta en Q-less</h1>
                    <?php if (isset($register_error)): ?><p class="auth-error"><?= $register_error ?></p><?php endif; ?>
                    <form method="post">
                        <div class="form-group"><label for="username">Usuario:</label><input type="text" name="username" required></div>
                        <div class="form-group"><label for="password">Contraseña:</label><input type="password" name="password" required></div>
                        <div class="form-group"><label for="wallet_url">Wallet URL:</label><input type="text" name="wallet_url" placeholder="$wallet.example/..." required></div>
                        <button type="submit" name="register" class="btn">Crear Cuenta</button>
                    </form>
                    <div class="auth-switch"><p>¿Ya tienes cuenta? <a href="index.php?view=login">Inicia Sesión</a></p></div>
                <?php else: ?>
                    <h1>Bienvenido a Q-less</h1>
                    <?php if (isset($login_error)): ?><p class="auth-error"><?= $login_error ?></p><?php endif; ?>
                    <form method="post">
                        <div class="form-group"><label for="username">Usuario:</label><input type="text" name="username" required></div>
                        <div class="form-group"><label for="password">Contraseña:</label><input type="password" name="password" required></div>
                        <button type="submit" name="login" class="btn">Ingresar</button>
                    </form>
                    <div class="auth-switch"><p>¿No tienes cuenta? <a href="index.php?view=register">Regístrate aquí</a></p></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <script>
        // Pequeño script para asegurar que el texto de la opción seleccionada se envíe
        const pickupSelect = document.getElementById('pickup_time');
        if (pickupSelect) {
            const form = pickupSelect.closest('form');
            let hiddenInput = form.querySelector('input[name="pickup_time_display"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'pickup_time_display';
                form.appendChild(hiddenInput);
            }
            function updateHiddenInput() {
                const selectedOption = pickupSelect.options[pickupSelect.selectedIndex];
                hiddenInput.value = selectedOption.text;
            }
            pickupSelect.addEventListener('change', updateHiddenInput);
            updateHiddenInput();
        }
    </script>
</body>
</html>
