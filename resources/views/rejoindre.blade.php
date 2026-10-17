<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rejoindre {{ $name ?? 'Ngoni Tontine' }}</title>
    <meta property="og:title" content="Rejoindre {{ $name ?? 'Ngoni Tontine' }}">
    <meta property="og:description" content="Ouvrez l'invitation dans l'application Ngoni Tontine.">
    <style>
        body { margin: 0; font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; background: #EEF2F8; color: #111B30; }
        .top { background: linear-gradient(135deg, #22498F, #0E1F4A); color: #fff; padding: 36px 24px 56px; text-align: center; }
        .logo { width: 84px; height: 84px; border-radius: 22px; background: #FFCC1F; color: #0F2A5C; font-weight: 900; font-size: 48px; line-height: 84px; margin: 0 auto 16px; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        .card { background: #fff; margin: -32px 18px 0; border-radius: 20px; padding: 24px; box-shadow: 0 10px 24px rgba(15,42,92,.12); text-align: center; }
        .btn { display: block; padding: 18px; border-radius: 14px; font-size: 18px; font-weight: 800; text-decoration: none; margin: 14px 0; }
        .open { background: #0F2A5C; color: #fff; }
        .store { background: #FFCC1F; color: #0F2A5C; }
        .muted { color: #5A6478; font-size: 15px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="top">
        <div class="logo">N</div>
        <h1>{{ $name ? 'Rejoindre « '.$name.' »' : 'Lien plus valable' }}</h1>
        <div>Ngoni Tontine</div>
    </div>
    <div class="card">
        @if ($name)
            <p class="muted">On vous invite à rejoindre ce {{ $kind === 'group' ? 'groupe' : 'caisse' }} sur Ngoni Tontine.</p>
            {{-- Android : ouvre l'app si elle est là, sinon le Play Store. --}}
            <a class="btn open" href="intent://rejoindre/{{ $token }}#Intent;scheme=ngonitontine;package={{ $package }};S.browser_fallback_url={{ urlencode($playStore) }};end"
               onclick="if (!/android/i.test(navigator.userAgent)) { location.href='ngonitontine://rejoindre/{{ $token }}'; return false; }">
                📲 Ouvrir dans l'application
            </a>
            <a class="btn store" href="{{ $playStore }}">⬇️ Télécharger l'application</a>
            <p class="muted">Après l'installation, revenez sur ce lien ou scannez de nouveau le QR code.</p>
        @else
            <p class="muted">Ce lien d'invitation a été remplacé. Demandez le nouveau QR code au bureau.</p>
            <a class="btn store" href="{{ $playStore }}">⬇️ Télécharger l'application</a>
        @endif
    </div>
</body>
</html>
