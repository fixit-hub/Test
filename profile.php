<?php
// --- USER PROFILE PAGE ---

require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/header_user.php';

// This page requires the user to be logged in.
require_login();

$user_id = $_SESSION['user_id'];
$update_message = '';
$password_message = '';

// --- LOGIC 1: HANDLE PROFILE INFO UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);

    if (empty($name) || empty($phone)) {
        $update_message = '<div class="bg-red-100 text-red-700 p-3 rounded">Name and Phone cannot be empty.</div>';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name; // Update session name
            $update_message = '<div class="bg-green-100 text-green-700 p-3 rounded">Profile updated successfully!</div>';
        } else {
            $update_message = '<div class="bg-red-100 text-red-700 p-3 rounded">Failed to update profile.</div>';
        }
        $stmt->close();
    }
}

// --- LOGIC 2: HANDLE PASSWORD CHANGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!password_verify($current_password, $user['password'])) {
        $password_message = '<div class="bg-red-100 text-red-700 p-3 rounded">Incorrect current password.</div>';
    } elseif ($new_password !== $confirm_password) {
        $password_message = '<div class="bg-red-100 text-red-700 p-3 rounded">New passwords do not match.</div>';
    } elseif (empty($new_password)) {
        $password_message = '<div class="bg-red-100 text-red-700 p-3 rounded">New password cannot be empty.</div>';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            $password_message = '<div class="bg-green-100 text-green-700 p-3 rounded">Password changed successfully!</div>';
        } else {
            $password_message = '<div class="bg-red-100 text-red-700 p-3 rounded">Failed to change password.</div>';
        }
        $stmt->close();
    }
}


// Fetch latest user data for display
$stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

?>
<title>My Profile - Quick Kart</title>

<div class="px-4">
    <h1 class="text-3xl font-bold text-slate-800 mb-6">My Profile</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Left: Update Profile Form -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-4">Personal Information</h2>
            <?php if($update_message) echo $update_message; ?>
            <form action="profile.php" method="POST" class="space-y-4 mt-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user_data['name']); ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                    <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" readonly class="mt-1 block w-full bg-gray-100 border-gray-300 rounded-md shadow-sm">
                    <p class="text-xs text-gray-500 mt-1">Email address cannot be changed.</p>
                </div>
                <button type="submit" name="update_profile" class="w-full bg-indigo-600 text-white py-2 rounded-lg font-semibold hover:bg-indigo-700">Update Profile</button>
            </form>
        </div>

        <!-- Right: Change Password & Logout -->
        <div class="space-y-8">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">Change Password</h2>
                 <?php if($password_message) echo $password_message; ?>
                <form action="profile.php" method="POST" class="space-y-4 mt-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                        <input type="password" name="current_password" id="current_password" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                     <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                        <input type="password" name="new_password" id="new_password" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                     <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>
                    <button type="submit" name="change_password" class="w-full bg-slate-700 text-white py-2 rounded-lg font-semibold hover:bg-slate-800">Change Password</button>
                </form>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                 <h2 class="text-xl font-bold mb-4">Logout</h2>
                 <p class="text-gray-600 mb-4">Are you sure you want to end your session?</p>
                 <a href="logout.php" class="w-full block bg-red-600 text-white py-2 rounded-lg font-semibold hover:bg-red-700">Logout</a>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../common/footer_user.php';
?>