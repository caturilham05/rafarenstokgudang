<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
            color: #333;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 18px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0px 6px 20px rgba(0,0,0,0.12);
            animation: fadeIn 0.7s ease-in-out;
        }

        h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        p {
            font-size: 16px;
            margin-bottom: 20px;
            color: #555;
        }

        .loader {
            border: 6px solid #ddd;
            border-top: 6px solid #3498db;
            border-radius: 50%;
            width: 52px;
            height: 52px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .footer {
            margin-top: 25px;
            font-size: 13px;
            color: #999;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>⚙️ Site Under Maintenance</h1>
    <p>Kami sedang melakukan pengembangan untuk website <a href="https://rafarenstokgudang.com">rafarenstokgudang.com</a>.
       Silakan kembali beberapa saat lagi.</p>

    <div class="loader"></div>

    <div class="footer">
        &copy; {{ date('Y') }} Rafaren Stok Gudang — All Rights Reserved.
    </div>
</div>

</body>
</html>
