<?php
// --- USER PRODUCT LISTING PAGE (BY CATEGORY) ---

require_once __DIR__ . '/../common/config.php';

// Check if a category ID is provided, otherwise redirect to homepage
if (!isset($_GET['cat_id']) || !is_numeric($_GET['cat_id'])) {
    header("Location: index.php");
    exit;
}
$cat_id = intval($_GET['cat_id']);

// Get sorting option from URL
$sort_option = $_GET['sort'] ?? 'newest';

// --- PHP LOGIC: FETCH DATA ---

// 1. Fetch the category name for the page title
$cat_stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
$cat_stmt->bind_param("i", $cat_id);
$cat_stmt->execute();
$cat_result = $cat_stmt->get_result();
if ($cat_result->num_rows === 0) {
    // If category doesn't exist, redirect
    header("Location: index.php");
    exit;
}
$category = $cat_result->fetch_assoc();
$category_name = $category['name'];
$cat_stmt->close();

// 2. Set the ORDER BY clause for the SQL query based on the sort option
switch ($sort_option) {
    case 'price_asc':
        $order_by = "price ASC";
        break;
    case 'price_desc':
        $order_by = "price DESC";
        break;
    default:
        $order_by = "created_at DESC"; // 'newest'
}

// 3. Fetch products for the selected category using the determined sort order
$prod_stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE cat_id = ? ORDER BY $order_by");
$prod_stmt->bind_param("i", $cat_id);
$prod_stmt->execute();
$products_result = $prod_stmt->get_result();

// Include the header AFTER fetching category name, so we can set the page title
require_once __DIR__ . '/../common/header_user.php';
?>

<!-- Set the page title dynamically -->
<title><?php echo htmlspecialchars($category_name); ?> - Quick Kart</title>

<!-- Page Header with Title and Sorting -->
<div class="flex flex-col md:flex-row justify-between items-center mb-6 px-4">
    <h2 class="text-2xl md:text-3xl font-bold text-slate-800 mb-4 md:mb-0">
        <?php echo htmlspecialchars($category_name); ?>
    </h2>
    
    <!-- Sorting Dropdown Form -->
    <form action="product.php" method="GET" class="flex items-center space-x-2">
        <input type="hidden" name="cat_id" value="<?php echo $cat_id; ?>">
        <label for="sort" class="text-sm font-medium text-gray-600">Sort by:</label>
        <select name="sort" id="sort" onchange="this.form.submit()" class="bg-white border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 text-sm">
            <option value="newest" <?php if($sort_option == 'newest') echo 'selected'; ?>>Newest</option>
            <option value="price_asc" <?php if($sort_option == 'price_asc') echo 'selected'; ?>>Price: Low to High</option>
            <option value="price_desc" <?php if($sort_option == 'price_desc') echo 'selected'; ?>>Price: High to Low</option>
        </select>
    </form>
</div>

<!-- Products Grid -->
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
        <div class="col-span-full text-center py-10">
            <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-500">No products found in this category yet.</p>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Add to Cart -->
<script>
// This function is identical to the one on index.php
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
// Close resources and include footer
$prod_stmt->close();
$conn->close();
require_once __DIR__ . '/../common/footer_user.php';
?>