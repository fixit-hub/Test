<?php
// --- USER LOGOUT SCRIPT ---

// Step 1: Start the session to access its data.
// It's necessary to start the session before you can destroy it.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Step 2: Unset all session variables.
// This clears all data stored in the session.
$_SESSION = [];

// Step 3: Destroy the session itself.
// This removes the session from the server.
session_destroy();

// Step 4: Redirect the user to the login page.
// After logging out, the user is sent back to the login screen.
header("Location: login.php");
exit(); // Crucial to prevent further script execution after a redirect.
?>