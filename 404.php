<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>404 Error</title>
    <link href="https://fonts.googleapis.com/css?family=Concert+One" rel="stylesheet">
    <link rel="stylesheet" href="./css/404.css">
</head>

<body>
    <div class="text">
        <p>404</p>
    </div>
    <div class="container">
        <div class="caveman">
            <div class="leg">
                <div class="foot">
                    <div class="fingers"></div>
                </div>
            </div>
            <div class="leg">
                <div class="foot">
                    <div class="fingers"></div>
                </div>
            </div>
            <div class="shape">
                <div class="circle"></div>
                <div class="circle"></div>
            </div>
            <div class="head">
                <div class="eye">
                    <div class="nose"></div>
                </div>
                <div class="mouth"></div>
            </div>
            <div class="arm-right">
                <div class="club"></div>
            </div>
        </div>
        <div class="caveman">
            <div class="leg">
                <div class="foot">
                    <div class="fingers"></div>
                </div>
            </div>
            <div class="leg">
                <div class="foot">
                    <div class="fingers"></div>
                </div>
            </div>
            <div class="shape">
                <div class="circle"></div>
                <div class="circle"></div>
            </div>
            <div class="head">
                <div class="eye">
                    <div class="nose"></div>
                </div>
                <div class="mouth"></div>
            </div>
            <div class="arm-right">
                <div class="club"></div>
            </div>
        </div>
    </div>
    <script src='https://use.fontawesome.com/releases/v5.0.7/js/all.js'></script>
    <script>
        var redirectUrl = '<?php echo $isLoggedIn ? "dashboard/index.php" : "index.php"; ?>';
        setTimeout(function() {
            window.location.href = redirectUrl;
        }, 15000);
    </script>
</body>

</html>