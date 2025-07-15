<?php
// --- USER PRODUCT DETAIL PAGE ---

require_once __DIR__ . '/../common/config.php';

// Check if a product ID is provided, otherwise redirect
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$product_id = intval($_GET['id']);

// --- PHP LOGIC: FETCH DATA ---

// 1. Fetch the main product details
$stmt = $conn->prepare(
    "SELECT p.*, c.name as category_name 
     FROM products p 
     JOIN categories c ON p.cat_id = c.id 
     WHERE p.id = ?"
);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();

if ($product_result->num_rows === 0) {
    // If product doesn't exist, redirect
    header("Location: index.php");
    exit;
}
$product = $product_result->fetch_assoc();
$stmt->close();

// 2. Fetch related products from the same category (excluding the current one)
$related_stmt = $conn->prepare(
    "SELECT id, name, price, image 
     FROM products 
     WHERE cat_id = ? AND id != ? 
     ORDER BY RAND() 
     LIMIT 4"
);
$related_stmt->bind_param("ii", $product['cat_id'], $product_id);
$related_stmt->execute();
$related_products = $related_stmt->get_result();
$related_stmt->close();


// Include header after we have data, so we can set a dynamic title
require_once __DIR__ . '/../common/header_user.php';
?>

<title><?php echo htmlspecialchars($product['name']); ?> - Quick Kart</title>

<!-- Main Product Details Section -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
    <!-- Left: Product Image -->
    <div class="bg-white p-4 rounded-lg shadow-md">
        <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-auto object-contain rounded-lg">
    </div>

    <!-- Right: Product Info & Actions -->
    <div class="flex flex-col justify-between">
        <div>
            <a href="product.php?cat_id=<?php echo $product['cat_id']; ?>" class="text-sm text-indigo-600 hover:underline"><?php echo htmlspecialchars($product['category_name']); ?></a>
            <h1 class="text-3xl md:text-4xl font-bold text-slate-800 mt-2"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <p class="text-3xl font-bold text-indigo-700 my-4">Rs. <?php echo number_format($product['price'], 2); ?></p>
            
            <div class="mt-2">
                <?php if ($product['stock'] > 0): ?>
                    <span class="bg-green-100 text-green-800 text-sm font-medium mr-2 px-2.5 py-0.5 rounded">In Stock</span>
                <?php else: ?>
                    <span class="bg-red-100 text-red-800 text-sm font-medium mr-2 px-2.5 py-0.5 rounded">Out of Stock</span>
                <?php endif; ?>
            </div>

            <p class="text-gray-600 mt-4 leading-relaxed">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </p>
        </div>
        
        <!-- Actions: Quantity and Add to Cart -->
        <?php if ($product['stock'] > 0): ?>
        <div class="mt-6 flex items-center space-x-4">
            <!-- Quantity Selector -->
            <div class="flex items-center border border-gray-300 rounded-md">
                <button id="btn-minus" class="px-3 py-2 text-lg font-bold text-gray-700 hover:bg-gray-100 rounded-l-md">-</button>
                <input type="text" id="quantity" name="quantity" value="1" readonly class="w-12 text-center border-l border-r">
                <button id="btn-plus" class="px-3 py-2 text-lg font-bold text-gray-700 hover:bg-gray-100 rounded-r-md">+</button>
            </div>
            <!-- Add to Cart Button -->
            <button onclick="addToCartDetail(<?php echo $product_id; ?>)" class="flex-1 bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                <i class="fas fa-cart-plus mr-2"></i>Add to Cart
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Related Products Section -->
<?php if ($related_products->num_rows > 0): ?>
<div class="mt-12 border-t pt-8">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">Related Products</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <?php while($related_prod = $related_products->fetch_assoc()): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden group">
                <a href="product_detail.php?id=<?php echo $related_prod['id']; ?>" class="block">
                    <img src="../<?php echo htmlspecialchars($related_prod['image']); ?>" alt="<?php echo htmlspecialchars($related_prod['name']); ?>" class="w-full h-40 object-cover group-hover:opacity-80 transition-opacity">
                </a>
                <div class="p-4">
                    <h3 class="text-md font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($related_prod['name']); ?></h3>
                    <p class="text-lg font-bold text-indigo-600 mt-1">Rs. <?php echo number_format($related_prod['price'], 2); ?></p>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<!-- Page-specific JavaScript -->
<script>
    const btnMinus = document.getElementById('btn-minus');
    const btnPlus = document.getElementById('btn-plus');
    const quantityInput = document.getElementById('quantity');
    const maxStock = <?php echo $product['stock']; ?>;

    if (btnMinus && btnPlus) {
        btnMinus.addEventListener('click', () => {
            let currentVal = parseInt(quantityInput.value);
            if (currentVal > 1) {
                quantityInput.value = currentVal - 1;
            }
        });
        
        btnPlus.addEventListener('click', () => {
            let currentVal = parseInt(quantityInput.value);
            if (currentVal < maxStock) {
                quantityInput.value = currentVal + 1;
            }
        });
    }

function addToCartDetail(productId) {
    const quantity = parseInt(document.getElementById('quantity').value);
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', quantity);

    fetch('cart.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const cartCountElement = document.getElementById('cart-count');
            if (cartCountElement) {
                cartCountElement.textContent = data.cart_count;
            } else {
                 const cartLink = document.querySelector('a[href="cart.php"]');
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