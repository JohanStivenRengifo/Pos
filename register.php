<?php
session_start();
require '../auth/forms/registro.php'; 
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Registro - POSPro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="icon" href="/favicon/favicon.ico" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/typicons/2.0.9/typicons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.0/animate.min.css">
    <link rel="stylesheet" href="/css/login.css">
</head>

<body id="particles-js">
    <div class="animated bounceInDown">
        <div class="container">
            <?php if (!empty($error_message)): ?>
                <span class="error animated tada" id="msg"><?php echo htmlspecialchars($error_message); ?></span>
            <?php endif; ?>
            <form name="form1" class="box" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return checkStuff()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <h4>Regis<span>tro</span></h4>
                <h5>Create account.</h5>
                <input type="text" name="email" placeholder="Email" autocomplete="off" required>
                <i class="typcn typcn-eye" id="eye"></i>
                <input type="password" name="password" placeholder="Password" id="pwd" autocomplete="off" required>
                <input type="password" name="confirm_password" placeholder="Confirm_ Password" id="pwd" autocomplete="off" required>              
                <input type="submit" value="Sign in" class="btn1">
            </form>
            <a href="/index.php" class="dnthave">Ya tienes cuenta? Inicia Sección.</a>
        </div>
    </div>
    <script src="https://cldup.com/S6Ptkwu_qA.js"></script>
    <script src="/js/login.js"></script>
    <script>
        window.onload = function() {
            <?php if (!empty($success_message)): ?>
                alert("<?php echo htmlspecialchars($success_message); ?>");
            <?php elseif (!empty($error_message)): ?>
                alert("<?php echo htmlspecialchars($error_message); ?>");
            <?php endif; ?>
        };
    </script>
</body>
</html>