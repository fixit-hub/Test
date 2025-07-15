<?php
// --- USER SHOPPING CART PAGE & AJAX HANDLER ---

require_once __DIR__ . '/../common/config.php';

// --- PART 1: AJAX REQUEST HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid Product ID.']);
        exit;
    }

    // ACTION: Add item to cart
    if ($_POST['action'] === 'add') {
        $quantity = intval($_POST['quantity'] ?? 1);
        
        // Check current quantity in cart
        $current_qty_in_cart = $_SESSION['cart'][$product_id] ?? 0;
        $new_qty = $current_qty_in_cart + $quantity;

        // Check stock
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stock = $stmt->get_result()->fetch_assoc()['stock'];
        $stmt->close();

        if ($new_qty > $stock) {
            echo json_encode(['success' => false, 'message' => 'Cannot add more. Not enough stock!']);
        } else {
            $_SESSION['cart'][$product_id] = $new_qty;
            echo json_encode(['success' => true, 'message' => 'Item added to cart!', 'cart_count' => count($_SESSION['cart'])]);
        }
    }
    
    // ACTION: Update item quantity
    else if ($_POST['action'] === 'update') {
        $quantity = intval($_POST['quantity']);
        if ($quantity > 0) {
            $_SESSION['cart'][$product_id] = $quantity;
            // No JSON response needed for simple updates on the cart page itself, page will be reloaded or handled by JS.
        }
        // Redirect back to cart page to show changes
        header('Location: cart.php');
    }
    
    // ACTION: Delete item from cart
    else if ($_POST['action'] === 'delete') {
        unset($_SESSION['cart'][$product_id]);
        // Redirect back to cart page
        header('Location: cart.php');
    }

    exit;
}

// --- PART 2: HTML PAGE DISPLAY (for GET requests) ---

require_once __DIR__ . '/../common/header_user.php';

$cart_items = [];
$grand_total = 0;

if (!empty($_SESSION['cart'])) {
    // Get product IDs from cart to fetch details in one query
    $product_ids = array_keys($_SESSION['cart']);
    $ids_string = implode(',', $product_ids);
    
    $sql = "SELECT id, name, price, image, stock FROM products WHERE id IN ($ids_string)";
    $result = $conn->query($sql);
    
    $db_products = [];
    while ($row = $result->fetch_assoc()) {
        $db_products[$row['id']] = $row;
    }

    // Prepare cart items for display
    foreach ($_SESSION['cart'] as $pid => $quantity) {
        if (isset($db_products[$pid])) {
            $product = $db_products[$pid];
            $sub_total = $product['price'] * $quantity;
            $grand_total += $sub_total;
            
            $cart_items[] = [
                'id' => $pid,
                'name' => $product['name'],
                'image' => $product['image'],
                'price' => $product['price'],
                'stock' => $product['stock'],
                'quantity' => $quantity,
                'sub_total' => $sub_total
            ];
        } else {
            // If a product in cart is no longer in DB, remove it from session
            unset($_SESSION['cart'][$pid]);
        }
    }
}
?>

<title>Shopping Cart - Quick Kart</title>

<div class="px-4">
    <h1 class="text-3xl font-bold text-slate-800 mb-6">Your Shopping Cart</h1>

    <?php if (empty($cart_items)): ?>
        <div class="text-center py-20 bg-white rounded-lg shadow-md">
            <i class="fas fa-shopping-cart text-5xl text-gray-300 mb-4"></i>
            <p class="text-xl text-gray-600">Your cart is empty.</p>
            <a href="index.php" class="mt-4 inline-block bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Cart Items List -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-md p-4">
                <div class="space-y-4">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="flex flex-col sm:flex-row items-center justify-between border-b pb-4">
                        <div class="flex items-center mb-4 sm:mb-0">
                            <img src="../<?php echo htmlspecialchars($item['image']); ?>" class="w-20 h-20 object-cover rounded mr-4">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></p>
                                <p class="text-sm text-gray-600">Rs. <?php echo number_format($item['price'], 2); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <!-- Quantity Form -->
                            <form action="cart.php" method="POST" class="flex items-center">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" 
                                       class="w-16 text-center border-gray-300 rounded" onchange="this.form.submit()">
                            </form>
                            <!-- Subtotal -->
                            <p class="w-24 text-right font-semibold">Rs. <?php echo number_format($item['sub_total'], 2); ?></p>
                            <!-- Remove Form -->
                            <form action="cart.php" method="POST">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700" title="Remove Item"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                    <h2 class="text-xl font-bold border-b pb-4 mb-4">Order Summary</h2>
                    <div class="flex justify-between mb-2">
                        <span>Subtotal</span>
                        <span>Rs. <?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    <div class="flex justify-between mb-4">
                        <span>Shipping Fee</span>
                        <span>Free</span>
                    </div>
                    <div class="flex justify-between font-bold text-lg border-t pt-4">
                        <span>Grand Total</span>
                        <span>Rs. <?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    <a href="checkout.php" class="block w-full text-center bg-green-600 text-white mt-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition">
                        Proceed to Checkout
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>


<?php
$conn->close();
require_once __DIR__ . '/../common/footer_user.php';
?>