<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Server Error - {{ config('app.name', 'NC3 Submission Platform') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Figtree', sans-serif; background: #f8fafc; color: #334155; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
            .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgb(0 0 0 / 0.1); padding: 2rem; text-align: center; max-width: 28rem; width: 100%; }
            .icon { width: 4rem; height: 4rem; border-radius: 50%; background: #fee2e2; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
            .icon svg { width: 2rem; height: 2rem; color: #dc2626; }
            .code { font-size: 3rem; font-weight: 700; color: #e2e8f0; margin-bottom: 0.5rem; }
            h1 { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; }
            p { color: #64748b; margin-bottom: 2rem; font-size: 0.875rem; line-height: 1.5; }
            a { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; background: #0284c7; color: white; font-weight: 500; border-radius: 0.5rem; text-decoration: none; font-size: 0.875rem; transition: background 0.2s; }
            a:hover { background: #0369a1; }
            @media (prefers-color-scheme: dark) {
                body { background: #0f172a; color: #cbd5e1; }
                .card { background: #1e293b; }
                .icon { background: rgba(127, 29, 29, 0.3); }
                .icon svg { color: #f87171; }
                .code { color: #334155; }
                h1 { color: white; }
                p { color: #94a3b8; }
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <p class="code">500</p>
            <h1>Server Error</h1>
            <p>Something went wrong on our end. We're working on it. Please try again later.</p>
            <a href="/">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Return Home
            </a>
        </div>
    </body>
</html>
