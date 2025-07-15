<?php
// --- USER HOMEPAGE (REWRITTEN WITH 'Rs.' SYMBOL) ---

require_once __DIR__ . '/../common/config.php';
// The user header now correctly uses 'Rs.' for any currency display if needed
require_once __DIR__ . '/../common/header_user.php'; 

// --- PHP LOGIC: FETCH DATA FOR HOMEPAGE ---

// 1. Fetch all categories
$categories_result = $conn->query("SELECT id, name, image FROM categories ORDER BY name ASC");

// 2. Fetch featured products (e.g., latest 8 products)
$products_result = $conn->query(
    "SELECT id, name, price, image 
     FROM products 
     ORDER BY created_at DESC 
     LIMIT 8"
);

?>

<!-- Search Bar for Mobile (hidden on desktop) -->
<div class="md:hidden px-4 mb-4">
    <div class="relative">
        <span class="absolute left-3 top-2 text-gray-400"><i class="fas fa-search"></i></span>
        <input type="text" placeholder="Search for products..." class="w-full pl-10 pr-4 py-2 border rounded-full bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
    </div>
</div>

<!-- Category List Section -->
<div id="categories" class="mb-8">
    <h2 class="text-2xl font-bold text-slate-800 mb-4 px-4">Categories</h2>
    <div class="flex overflow-x-auto space-x-4 px-4 pb-2">
        <?php if ($categories_result->num_rows > 0): ?>
            <?php while($cat = $categories_result->fetch_assoc()): ?>
            <a href="product.php?cat_id=<?php echo $cat['id']; ?>" class="flex-shrink-0 w-24 text-center">
                <div class="w-20 h-20 mx-auto bg-white rounded-full shadow-md flex items-center justify-center p-2 overflow-hidden">
                    <img src="../<?php echo htmlspecialchars($cat['image'] ?? 'assets/images/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="max-w-full max-h-full">
                </div>
                <p class="mt-2 text-sm font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($cat['name']); ?></p>
            </a>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-gray-500">No categories found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Featured Products Section -->
<div>
    <h2 class="text-2xl font-bold text-slate-800 mb-4 px-4">Featured Products</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 px-4">
        <?php if ($products_result->num_rows > 0): ?>
            <?php while($prod = $products_result->fetch_assoc()): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden group">
                <a href="product_detail.php?id=<?php echo $prod['id']; ?>" class="block">
                    <img src="../<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="w-full h-40 object-cover group-hover:opacity-80 transition-opacity">
                </a>
                <div class="p-4">
                    <h3 class="text-md font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($prod['name']); ?></h3>
                    
                    <!-- CORRECTED CURRENCY SYMBOL -->
                    <p class="text-lg font-bold text-indigo-600 mt-1">Rs. <?php echo number_format($prod['price'], 2); ?></p>
                    
                    <button onclick="addToCart(<?php echo $prod['id']; ?>)" class="w-full mt-3 bg-indigo-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-gray-500 col-span-full">No featured products available.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Page-specific JavaScript -->
<script>
function addToCart(productId) {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', 1);

    fetch('cart.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartCountElement = document.getElementById('cart-count');
            const cartLink = document.querySelector('a[href="cart.php"]');
            if (cartCountElement) {
                cartCountElement.textContent = data.cart_count;
                cartCountElement.classList.add('transform', 'scale-125');
                setTimeout(() => cartCountElement.classList.remove('transform', 'scale-125'), 200);
            } else {
                const newBadge = document.createElement('span');
                newBadge.id = 'cart-count';
                newBadge.className = 'absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
                newBadge.textContent = data.cart_count;
                cartLink.appendChild(newBadge);
            }
            const toast = document.getElementById('toast');
            toast.textContent = data.message;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 2000);
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>


<?php
// Close connection and include footer
$conn->close();
require_once __DIR__ . '/../common/footer_user.php';
?>