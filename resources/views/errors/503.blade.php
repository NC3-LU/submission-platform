<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Maintenance - {{ config('app.name', 'NC3 Submission Platform') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Figtree', sans-serif; background: #f8fafc; color: #334155; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
            .card { background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgb(0 0 0 / 0.1); padding: 2rem; text-align: center; max-width: 28rem; width: 100%; }
            .icon { width: 4rem; height: 4rem; border-radius: 50%; background: #e0f2fe; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
            .icon svg { width: 2rem; height: 2rem; color: #0284c7; }
            .code { font-size: 3rem; font-weight: 700; color: #e2e8f0; margin-bottom: 0.5rem; }
            h1 { font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; }
            p { color: #64748b; margin-bottom: 2rem; font-size: 0.875rem; line-height: 1.5; }
            @media (prefers-color-scheme: dark) {
                body { background: #0f172a; color: #cbd5e1; }
                .card { background: #1e293b; }
                .icon { background: rgba(14, 116, 144, 0.2); }
                .icon svg { color: #38bdf8; }
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <p class="code">503</p>
            <h1>We'll Be Right Back</h1>
            <p>We're performing scheduled maintenance to improve your experience. Please check back shortly.</p>
        </div>
    </body>
</html>
