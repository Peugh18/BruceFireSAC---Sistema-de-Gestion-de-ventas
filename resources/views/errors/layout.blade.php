<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Bruce Fire</title>
    <link rel="icon" type="image/png" href="/brand/logo-icon.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=oswald:600,700|inter:400,500,600,700" rel="stylesheet" />
    <style>
        :root {
            --bg: #f8fafc;
            --fg: #0f172a;
            --muted: #64748b;
            --primary: #e52320;
            --primary-hover: #cc1e1b;
            --card-border: #e2e8f0;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #09090b;
                --fg: #f8fafc;
                --muted: #94a3b8;
                --card-border: #27272a;
            }
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--fg);
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            box-sizing: border-box;
            text-align: center;
        }
        .brand-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        .brand-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }
        .brand-name {
            font-family: 'Oswald', sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .container {
            max-width: 440px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .chispa-wrap {
            position: relative;
            width: 168px;
            height: 168px;
            margin-bottom: 8px;
            user-select: none;
        }
        .chispa-wrap img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .chispa-oscuro {
            display: none;
        }
        @media (prefers-color-scheme: dark) {
            .chispa-claro {
                display: none;
            }
            .chispa-oscuro {
                display: block;
            }
        }
        .error-code {
            font-family: 'Oswald', sans-serif;
            font-size: 64px;
            font-weight: 700;
            line-height: 1;
            color: var(--primary);
            margin-top: 12px;
            margin-bottom: 8px;
        }
        .error-title {
            font-family: 'Oswald', sans-serif;
            font-size: 24px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin: 0 0 12px;
            color: var(--fg);
        }
        .error-desc {
            font-size: 14px;
            line-height: 1.5;
            color: var(--muted);
            margin: 0 0 28px;
            max-width: 380px;
        }
        .actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 42px;
            padding: 0 22px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            background-color: var(--primary);
            color: #ffffff;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform 0.15s ease, background-color 0.15s ease;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        .btn-primary:active {
            transform: scale(0.97);
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 42px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            background-color: transparent;
            color: var(--fg);
            text-decoration: none;
            border: 1px solid var(--card-border);
            cursor: pointer;
            transition: transform 0.15s ease, background-color 0.15s ease;
        }
        .btn-secondary:hover {
            background-color: rgba(125, 125, 125, 0.1);
        }
        .btn-secondary:active {
            transform: scale(0.97);
        }
    </style>
</head>
<body>
    <div class="brand-header">
        <img src="/brand/logo-icon.png" alt="" class="brand-logo">
        <span class="brand-name">Bruce Fire</span>
    </div>

    <main class="container">
        <div class="chispa-wrap">
            <img src="/brand/chispa/claro/@yield('chispa_pose', 'saludo').webp" alt="Chispa" class="chispa-claro">
            <img src="/brand/chispa/oscuro/@yield('chispa_pose', 'saludo').webp" alt="Chispa" class="chispa-oscuro">
        </div>

        <div class="error-code">@yield('code')</div>
        <h1 class="error-title">@yield('title')</h1>
        <p class="error-desc">@yield('message')</p>

        <div class="actions">
            @yield('actions')
            <a href="/" class="btn-primary">
                Volver al panel
            </a>
        </div>
    </main>
</body>
</html>
