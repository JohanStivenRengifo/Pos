<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #ffffff;
        }
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        strong {
            color: #d9534f; /* Color para resaltar el código OTP */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verificación de OTP</h1>
        <p>Tu código de verificación es: <strong>{{otp}}</strong></p>
        <p>Por favor, ingresa este código en la aplicación para completar la verificación.</p>
        <p>Si no solicitaste este código, por favor ignora este correo.</p>
    </div>
</body>
</html>