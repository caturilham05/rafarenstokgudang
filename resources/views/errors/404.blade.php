<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>404 | Halaman Tidak Ditemukan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --bg: #0f172a;
            --card: #020617;
            --text: #e5e7eb;
            --muted: #94a3b8;
            --accent: #38bdf8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            background: radial-gradient(circle at top, #1e293b, var(--bg));
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: linear-gradient(180deg, #020617, #020617cc);
            border: 1px solid #1e293b;
            border-radius: 16px;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,.4);
        }

        .code {
            font-size: 96px;
            font-weight: 800;
            line-height: 1;
            color: var(--accent);
            margin-bottom: 16px;
        }

        h1 {
            font-size: 22px;
            margin-bottom: 12px;
        }

        p {
            font-size: 15px;
            color: var(--muted);
            margin-bottom: 32px;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        a {
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: all .2s ease;
        }

        .primary {
            background: var(--accent);
            color: #020617;
        }

        .primary:hover {
            opacity: .85;
        }

        .secondary {
            border: 1px solid #334155;
            color: var(--text);
        }

        .secondary:hover {
            background: #020617;
        }

        footer {
            margin-top: 32px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>

    <div class="card">
        <div class="code">404</div>
        <h1>Page Not Found</h1>
        <p>Sorry, the page you're looking for is unavailable or has moved. <br> Please check the URL again or return to the home page.</p>

        <div class="actions">
            <a href="/" class="primary">Back To Home</a>
            <a href="javascript:history.back()" class="secondary">Previous Page</a>
        </div>

        <footer>
            © {{ date('Y') }} {{strtoupper(str_replace('-', ' ', env('APP_NAME')))}}
        </footer>
    </div>

</body>
</html>
