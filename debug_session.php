<?php
require_once 'C:\xampp1\htdocs\Gobstore!\includes\functions.php';

echo "Current session status:\n";
echo "  user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "  user_role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
echo "  isLoggedIn(): " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "\n";
echo "  isAdmin(): " . (isAdmin() ? 'TRUE' : 'FALSE') . "\n\n";

if (isLoggedIn()) {
    echo "You ARE logged in as: " . ($_SESSION['user_name'] ?? 'unknown') . "\n";
    echo "The navbar shows the user icon (person icon) instead of Register/Sign In.\n";
    echo "Click the person icon to open the dropdown and click Logout.\n";
    echo "After logout, refresh to see Register/Sign In buttons.\n\n";
    echo "Or run: " . __DIR__ . "\logout.php to force logout.\n";
} else {
    echo "You are NOT logged in. The buttons SHOULD be visible.\n";
}
