<?php
// --- USER CHECKOUT PAGE ---

require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/header_user.php';

// --- SECURITY CHECKS ---
// 1. Require login
require_login(); 

// 2. Check if cart is empty. If so, redirect to cart page.
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// --- FETCH USER DETAILS FOR AUTOFILL ---
$user_stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$error_message = '';

// --- LOGIC: HANDLE ORDER PLACEMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $delivery_address = trim($_POST['address']);
    
    if (empty($delivery_address)) {
        $error_message = 'Please provide a delivery address.';
    } else {
        // --- CALCULATE GRAND TOTAL ON SERVER-SIDE ---
        $product_ids = array_keys($_SESSION['cart']);
        $ids_string = implode(',', $product_ids);
        $sql = "SELECT id, price, stock FROM products WHERE id IN ($ids_string)";
        $result = $conn->query($sql);
        $db_products = [];
        while ($row = $result->fetch_assoc()) {
            $db_products[$row['id']] = $row;
        }

        $grand_total = 0;
        $can_process_order = true;
        foreach ($_SESSION['cart'] as $pid => $quantity) {
            // Check stock again before placing order
            if (!isset($db_products[$pid]) || $quantity > $db_products[$pid]['stock']) {
                $can_process_order = false;
                $error_message = "Sorry, an item in your cart is out of stock or has limited quantity. Please review your cart.";
                break;
            }
            $grand_total += $db_products[$pid]['price'] * $quantity;
        }
        
        // --- PROCESS ORDER USING A TRANSACTION ---
        if ($can_process_order) {
            $conn->begin_transaction();
            try {
                // 1. Insert into 'orders' table
                $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, delivery_address) VALUES (?, ?, 'Placed', ?)");
                $order_stmt->bind_param("ids", $user_id, $grand_total, $delivery_address);
                $order_stmt->execute();
                $order_id = $order_stmt->insert_id;

                // 2. Insert into 'order_items' and Update stock
                $order_item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $update_stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

                foreach ($_SESSION['cart'] as $pid => $quantity) {
                    $price = $db_products[$pid]['price'];
                    // Insert order item
                    $order_item_stmt->bind_param("iiid", $order_id, $pid, $quantity, $price);
                    $order_item_stmt->execute();
                    // Update product stock
                    $update_stock_stmt->bind_param("ii", $quantity, $pid);
                    $update_stock_stmt->execute();
                }

                // If all queries were successful, commit the transaction
                $conn->commit();

                // Clear the cart
                unset($_SESSION['cart']);

                // Redirect to My Orders page with a success message
                $_SESSION['order_success'] = "Your order has been placed successfully!";
                header("Location: order.php");
                exit;

            } catch (Exception $e) {
                // If any query fails, roll back the changes
                $conn->rollback();
                $error_message = "Failed to place order. Please try again. " . $e->getMessage();
            }
        }
    }
}
?>

<title>Checkout - Quick Kart</title>

<div class="px-4">
    <h1 class="text-3xl font-bold text-slate-800 mb-6">Checkout</h1>
    
    <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Left: Shipping Form -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold mb-4">Shipping Information</h2>
                <form action="checkout.php" method="POST" class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="name" value="<?php echo htmlspecialchars($user_data['name']); ?>" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm">
                    </div>
                     <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm">
                    </div>
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700">Full Delivery Address</label>
                        <textarea name="address" id="address" rows="4" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter your House No, Street, Area, City..."></textarea>
                    </div>
                    <div class="pt-4">
                        <button type="submit" name="place_order" class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition">Place Order</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Order Summary -->
        <div class="md:col-span-1">
             <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                <h2 class="text-xl font-bold border-b pb-4 mb-4">Your Order</h2>
                <div class="space-y-2 mb-4">
                     <?php
                        // We need to recalculate total for display here
                        $display_total = 0;
                        if (!empty($_SESSION['cart'])) {
                            $pids = implode(',', array_keys($_SESSION['cart']));
                            $display_sql = "SELECT id, name, price FROM products WHERE id IN ($pids)";
                            $display_res = $conn->query($display_sql);
                            $display_prods = [];
                            while($row = $display_res->fetch_assoc()){ $display_prods[$row['id']] = $row; }

                            foreach ($_SESSION['cart'] as $pid => $qty) {
                                if (isset($display_prods[$pid])) {
                                    $sub = $display_prods[$pid]['price'] * $qty;
                                    $display_total += $sub;
                                    echo "<div class='flex justify-between text-sm'><span class='w-2/3 truncate'>{$display_prods[$pid]['name']} x{$qty}</span> <span>Rs. ".number_format($sub, 2)."</span></div>";
                                }
                            }
                        }
                    ?>
                </div>
                <div class="flex justify-between font-bold text-lg border-t pt-4">
                    <span>Total</span>
                    <span>Rs. <?php echo number_format($display_total, 2); ?></span>
                </div>
                 <div class="mt-6 bg-blue-50 border border-blue-200 text-blue-800 text-center p-3 rounded-lg">
                    <p class="font-semibold"><i class="fas fa-money-bill-wave mr-2"></i>Cash on Delivery</p>
                    <p class="text-sm">You will pay when your order arrives.</p>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
$conn->close();
require_once __DIR__ . '/../common/footer_user.php';
?>