<?php
// --- USER'S "MY ORDERS" PAGE ---

require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/header_user.php';

// This page requires the user to be logged in.
require_login();

$user_id = $_SESSION['user_id'];

// --- PHP LOGIC: FETCH USER'S ORDERS ---

// 1. Fetch Active Orders (Placed, Dispatched)
$active_stmt = $conn->prepare(
    "SELECT o.id, o.total_amount, o.status, o.created_at, 
     (SELECT p.image FROM products p JOIN order_items oi ON p.id = oi.product_id WHERE oi.order_id = o.id LIMIT 1) as product_image
     FROM orders o 
     WHERE o.user_id = ? AND (o.status = 'Placed' OR o.status = 'Dispatched')
     ORDER BY o.created_at DESC"
);
$active_stmt->bind_param("i", $user_id);
$active_stmt->execute();
$active_orders = $active_stmt->get_result();
$active_stmt->close();

// 2. Fetch Order History (Delivered, Cancelled)
$history_stmt = $conn->prepare(
    "SELECT o.id, o.total_amount, o.status, o.created_at,
     (SELECT p.image FROM products p JOIN order_items oi ON p.id = oi.product_id WHERE oi.order_id = o.id LIMIT 1) as product_image
     FROM orders o 
     WHERE o.user_id = ? AND (o.status = 'Delivered' OR o.status = 'Cancelled')
     ORDER BY o.created_at DESC"
);
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history_orders = $history_stmt->get_result();
$history_stmt->close();

?>
<title>My Orders - Quick Kart</title>

<div class="px-4">
    <h1 class="text-3xl font-bold text-slate-800 mb-6">My Orders</h1>

    <!-- Display success message from checkout if it exists -->
    <?php
    if (isset($_SESSION['order_success'])) {
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p>' . $_SESSION['order_success'] . '</p></div>';
        unset($_SESSION['order_success']);
    }
    ?>

    <!-- Tab Navigation -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button onclick="showOrderTab('active')" id="active-tab" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">
                Active Orders
            </button>
            <button onclick="showOrderTab('history')" id="history-tab" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                Order History
            </button>
        </nav>
    </div>

    <!-- Active Orders Content -->
    <div id="active-orders" class="mt-6 space-y-6">
        <?php if ($active_orders->num_rows > 0): ?>
            <?php while ($order = $active_orders->fetch_assoc()): ?>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <div class="flex flex-col sm:flex-row justify-between border-b pb-4 mb-4">
                        <div>
                            <p class="font-bold text-lg text-gray-800">Order #<?php echo $order['id']; ?></p>
                            <p class="text-sm text-gray-500">Placed on: <?php echo date("d M Y", strtotime($order['created_at'])); ?></p>
                        </div>
                        <p class="font-bold text-lg text-indigo-600 mt-2 sm:mt-0">Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                    </div>
                    <!-- Progress Tracker -->
                    <div class="flex items-center justify-between mt-4 text-xs sm:text-sm">
                        <div class="step-item text-center <?php if (in_array($order['status'], ['Placed', 'Dispatched', 'Delivered'])) echo 'text-indigo-600 font-bold'; ?>">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 mx-auto rounded-full flex items-center justify-center bg-indigo-600 text-white"><i class="fas fa-check"></i></div>
                            <p class="mt-1">Placed</p>
                        </div>
                        <div class="flex-1 h-1 bg-gray-200"><div class="h-1 <?php if (in_array($order['status'], ['Dispatched', 'Delivered'])) echo 'bg-indigo-600'; ?>"></div></div>
                        <div class="step-item text-center <?php if (in_array($order['status'], ['Dispatched', 'Delivered'])) echo 'text-indigo-600 font-bold'; ?>">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 mx-auto rounded-full flex items-center justify-center <?php if (in_array($order['status'], ['Dispatched', 'Delivered'])) echo 'bg-indigo-600 text-white'; else echo 'bg-gray-200 text-gray-500'; ?>"><i class="fas fa-truck"></i></div>
                            <p class="mt-1">Dispatched</p>
                        </div>
                        <div class="flex-1 h-1 bg-gray-200"><div class="h-1 <?php if ($order['status'] === 'Delivered') echo 'bg-indigo-600'; ?>"></div></div>
                        <div class="step-item text-center <?php if ($order['status'] === 'Delivered') echo 'text-indigo-600 font-bold'; ?>">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 mx-auto rounded-full flex items-center justify-center <?php if ($order['status'] === 'Delivered') echo 'bg-indigo-600 text-white'; else echo 'bg-gray-200 text-gray-500'; ?>"><i class="fas fa-box-check"></i></div>
                            <p class="mt-1">Delivered</p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center text-gray-500 py-10">You have no active orders.</p>
        <?php endif; ?>
    </div>

    <!-- Order History Content (Hidden by default) -->
    <div id="history-orders" class="mt-6 space-y-4 hidden">
        <?php if ($history_orders->num_rows > 0): ?>
             <?php while ($order = $history_orders->fetch_assoc()): ?>
                 <div class="bg-white rounded-lg shadow-md p-4 flex items-center justify-between">
                    <div class="flex items-center">
                        <img src="../<?php echo htmlspecialchars($order['product_image'] ?? 'assets/images/placeholder.png'); ?>" class="w-16 h-16 object-cover rounded mr-4 hidden sm:block">
                        <div>
                            <p class="font-bold text-gray-800">Order #<?php echo $order['id']; ?></p>
                            <p class="text-sm text-gray-500"><?php echo date("d M Y", strtotime($order['created_at'])); ?></p>
                            <p class="text-sm font-semibold">Total: Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $order['status'] === 'Delivered' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($order['status']); ?>
                    </span>
                 </div>
             <?php endwhile; ?>
        <?php else: ?>
             <p class="text-center text-gray-500 py-10">You have no past orders.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function showOrderTab(tabName) {
        const activeTab = document.getElementById('active-tab');
        const historyTab = document.getElementById('history-tab');
        const activeOrders = document.getElementById('active-orders');
        const historyOrders = document.getElementById('history-orders');

        if (tabName === 'active') {
            activeTab.classList.add('border-indigo-500', 'text-indigo-600');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            historyTab.classList.add('border-transparent', 'text-gray-500');
            historyTab.classList.remove('border-indigo-500', 'text-indigo-600');
            activeOrders.classList.remove('hidden');
            historyOrders.classList.add('hidden');
        } else {
            historyTab.classList.add('border-indigo-500', 'text-indigo-600');
            historyTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-transparent', 'text-gray-500');
            activeTab.classList.remove('border-indigo-500', 'text-indigo-600');
            historyOrders.classList.remove('hidden');
            activeOrders.classList.add('hidden');
        }
    }
</script>

<?php
$conn->close();
require_once __DIR__ . '/../common/footer_user.php';
?>