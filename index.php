<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
require_once 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
    <div class="bg-white border border-gray-300 rounded-lg shadow-md w-full max-w-sm p-6">
        <div class="text-center mb-6">
            <img src="assets/img/mcgoff.png" alt="McGoff Logo" class="mx-auto w-32 mb-2">
            <h2 class="text-xl font-bold text-gray-800">Clean-up Notices<br>Please Login Below</h2>
        </div>

        <form action="includes/auth.php" method="post" class="space-y-4">
            <input type="text" name="username" placeholder="Username"
                class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                required>

            <input type="password" name="password" placeholder="Password"
                class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                required>

            <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-semibold hover:bg-blue-700 transition">
                Login
            </button>
        </form>

        <div class="mt-4 text-sm text-center text-gray-600 bg-gray-50 p-3 rounded border border-gray-200">
            Didn't get the email?
            <a href="resend_confirm.php" class="text-blue-600 underline hover:text-blue-800">
                Resend Confirmation
            </a>
			<div class="mt-6 text-center">
            <a href="/how-it-works.html" class="inline-block text-blue-600 font-medium hover:underline hover:text-blue-800 transition">
                📘 How it works
            </a>
        </div>
    </div>
</div>
        
        

<?php require_once 'includes/footer.php'; ?>
