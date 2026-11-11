<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; color: #0f172a; }
        .frame { position: absolute; top: 28px; left: 28px; right: 28px; bottom: 28px; border: 6px solid #2f6fe4; padding: 48px 64px; }
        .brand { color: #2f6fe4; font-weight: bold; font-size: 22px; letter-spacing: 2px; }
        h1 { font-size: 40px; margin: 24px 0 4px; }
        .name { font-size: 34px; font-weight: bold; margin: 18px 0; border-bottom: 2px solid #cbd5e1; display: inline-block; padding: 0 24px 6px; }
        .level { font-size: 22px; color: #2f6fe4; font-weight: bold; }
        table { margin: 18px auto 0; border-collapse: collapse; font-size: 13px; }
        td { padding: 4px 14px; border-bottom: 1px solid #e2e8f0; }
        .footer { position: absolute; bottom: 40px; left: 64px; right: 64px; font-size: 11px; color: #475569; }
        .center { text-align: center; }
    </style>
</head>
<body>
<div class="frame">
    <div class="center">
        <div class="brand">ENGLISHPATH</div>
        <h1>Certificate of Achievement</h1>
        <div>This certifies that</div>
        <div class="name">{{ $certificate->user->name }}</div>
        <div>has passed the EnglishPath level exam for</div>
        <div class="level">CEFR {{ $certificate->level->label() }} — {{ $certificate->level->name }}</div>
        <table>
            <tr><td><strong>Total score</strong></td><td><strong>{{ $certificate->score }}</strong></td></tr>
            @foreach ($certificate->skill_scores as $skill => $score)
                <tr><td>{{ ucfirst($skill) }}</td><td>{{ $score }}</td></tr>
            @endforeach
        </table>
    </div>
    <div class="footer">
        Issued {{ $certificate->issued_at->format('d F Y') }} · Verification code <strong>{{ $certificate->code }}</strong> · {{ $verifyUrl }}<br>
        Internal certificate of EnglishPath; not an officially accredited qualification.
    </div>
</div>
</body>
</html>
