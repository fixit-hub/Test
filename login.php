<?php
// --- USER LOGIN & SIGNUP PAGE (Located in /user/ folder) ---

// Go up one directory to find the 'common' folder
require_once __DIR__ . '/../common/config.php';

// --- AJAX REQUEST HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // ACTION: Handle User Signup
    if ($_POST['action'] === 'signup') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($name) || empty($phone) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Check if email already exists
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'This email is already registered. Please login.']);
            $stmt_check->close();
            exit;
        }
        $stmt_check->close();
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssss", $name, $phone, $email, $hashed_password);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Signup successful! You can now login.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        }
        $stmt->close();
    }

    // ACTION: Handle User Login
    else if ($_POST['action'] === 'login') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, name, password, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                 echo json_encode(['success' => false, 'message' => 'Your account has been blocked. Please contact support.']);
                 exit;
            }

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                echo json_encode(['success' => true, 'redirect_url' => 'index.php']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
        $stmt->close();
    }
    
    $conn->close();
    exit;
}

// Redirect to home if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Signup - Quick Kart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .tab-btn.active {
            border-bottom-width: 2px;
            border-color: #4f46e5; /* indigo-600 */
            color: #4f46e5;
        }
    </style>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-white p-8 rounded-xl shadow-lg">
        <div class="text-center mb-8">
            <i class="fas fa-shopping-cart text-4xl text-indigo-600"></i>
            <h1 class="text-3xl font-bold text-slate-800 mt-2">Welcome to Quick Kart</h1>
        </div>
        
        <div class="flex border-b mb-6">
            <button id="login-tab-btn" class="tab-btn flex-1 py-2 font-semibold text-slate-600 active" onclick="showTab('login')">Login</button>
            <button id="signup-tab-btn" class="tab-btn flex-1 py-2 font-semibold text-slate-500" onclick="showTab('signup')">Sign Up</button>
        </div>

        <div id="message-area" class="mb-4 text-center"></div>

        <div id="login-form-container">
            <form id="login-form" class="space-y-6">
                <input type="hidden" name="action" value="login">
                <div>
                    <label for="login-email" class="text-sm font-medium text-slate-700">Email</label>
                    <input type="email" name="email" id="login-email" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md" required>
                </div>
                <div>
                    <label for="login-password" class="text-sm font-medium text-slate-700">Password</label>
                    <input type="password" name="password" id="login-password" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md" required>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-md hover:bg-indigo-700">Login</button>
            </form>
        </div>

        <div id="signup-form-container" class="hidden">
            <form id="signup-form" class="space-y-4">
                 <input type="hidden" name="action" value="signup">
                <div>
                    <label for="signup-name" class="text-sm font-medium text-slate-700">Full Name</label>
                    <input type="text" name="name" id="signup-name" class="mt-1 block w-full px-3 py-2 border-slate-300 rounded-md" required>
                </div>
                <div>
                    <label for="signup-phone" class="text-sm font-medium text-slate-700">Phone Number</label>
                    <input type="tel" name="phone" id="signup-phone" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md" required>
                </div>
                <div>
                    <label for="signup-email" class="text-sm font-medium text-slate-700">Email</label>
                    <input type="email" name="email" id="signup-email" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md" required>
                </div>
                <div>
                    <label for="signup-password" class="text-sm font-medium text-slate-700">Password</label>
                    <input type="password" name="password" id="signup-password" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md" required>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-md hover:bg-indigo-700">Create Account</button>
            </form>
        </div>
    </div>

<script>
    // --- JavaScript for Tab Switching and Form Submission ---

    const loginTab = document.getElementById('login-tab-btn');
    const signupTab = document.getElementById('signup-tab-btn');
    const loginForm = document.getElementById('login-form-container');
    const signupForm = document.getElementById('signup-form-container');
    const messageArea = document.getElementById('message-area');

    function showTab(tabName) {
        messageArea.innerHTML = '';
        if (tabName === 'login') {
            loginTab.classList.add('active');
            signupTab.classList.remove('active');
            loginForm.classList.remove('hidden');
            signupForm.classList.add('hidden');
        } else {
            loginTab.classList.remove('active');
            signupTab.classList.add('active');
            loginForm.classList.add('hidden');
            signupForm.classList.remove('hidden');
        }
    }

    document.getElementById('login-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('login.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect_url;
            } else {
                messageArea.innerHTML = `<div class="text-red-600 bg-red-100 p-2 rounded">${data.message}</div>`;
            }
        });
    });

    document.getElementById('signup-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('login.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.reset();
                messageArea.innerHTML = `<div class="text-green-600 bg-green-100 p-2 rounded">${data.message}</div>`;
                showTab('login');
            } else {
                 messageArea.innerHTML = `<div class="text-red-600 bg-red-100 p-2 rounded">${data.message}</div>`;
            }
        });
    });
</script>
</body>
</html>