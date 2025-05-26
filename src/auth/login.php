<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $email =$_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    //retrieve user from database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Check if user exists and password is correct
    if ($user && password_verify($password, $user['password_hash']))
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['role'] = $user['role'];

        // Redirect depending on user role
        switch ($user['role']) 
        {
            case 'admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'agent':
                header('Location: ../agent/dashboard.php');
                break;
            case 'client':
                header('Location: ../pages/explore.php');
                break;
            default:
                echo "Invalid user role.";
        }
        exit();
    }
    else
    {
        // Invalid credentials
        $error = "Invalid email or password.";
        header("Location: ../pages/login.php?error=" . urlencode($error));
        exit();
    }
}

?>