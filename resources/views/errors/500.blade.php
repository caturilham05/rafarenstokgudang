<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>500 - Internal Server Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #7f1d1d, #dc2626);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.12);
            padding: 50px 40px;
            border-radius: 16px;
            max-width: 520px;
            width: 90%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }

        h1 {
            font-size: 96px;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        h2 {
            font-size: 26px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        p {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 35px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: #fff;
            color: #dc2626;
            font-weight: 600;
            border-radius: 30px;
            text-decoration: none;
            transition: 0.3s ease;
        }

        .btn:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 72px;
            }

            h2 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="icon">⚠️</div>
    <h1>500</h1>
    <h2>Internal Server Error</h2>
    <p>An error occurred on our server. <br> Please try again later or contact an administrator.</p>

    <a href="{{ url('/') }}" class="btn">Back To Home</a>
</div>

</body>
</html>
