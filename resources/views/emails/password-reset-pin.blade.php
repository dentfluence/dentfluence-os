<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Reset PIN</title>
<style>
    body { margin:0; padding:0; background:#f5f0fa; font-family:'Segoe UI',Arial,sans-serif; }
    .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 24px rgba(60,0,90,0.10); }
    .header { background:linear-gradient(135deg,#5a006e,#9c27b0); padding:32px 40px; text-align:center; }
    .header h1 { margin:0; color:#fff; font-size:22px; font-weight:600; letter-spacing:0.06em; }
    .header p  { margin:6px 0 0; color:rgba(255,255,255,0.75); font-size:13px; letter-spacing:0.08em; text-transform:uppercase; }
    .body { padding:36px 40px; }
    .body p { color:#4a3060; font-size:14px; line-height:1.7; margin:0 0 16px; }
    .pin-box { background:#f5eefa; border:2px solid #b060d8; border-radius:10px; padding:20px; text-align:center; margin:24px 0; }
    .pin-box .label { font-size:11px; font-weight:600; letter-spacing:0.25em; text-transform:uppercase; color:#9040b8; margin-bottom:8px; }
    .pin-digits { font-size:42px; font-weight:700; color:#5a006e; letter-spacing:0.18em; font-variant-numeric:tabular-nums; }
    .expiry { font-size:12px; color:#a080b8; margin-top:8px; }
    .footer { background:#f9f5ff; border-top:1px solid #e8d8f8; padding:20px 40px; text-align:center; }
    .footer p { margin:0; font-size:11.5px; color:#b0a0c0; line-height:1.7; }
    .footer a { color:#7c18a0; text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>{{ $clinicName }}</h1>
        <p>Password Reset Request</p>
    </div>
    <div class="body">
        <p>You requested to reset your password. Use the PIN below to continue. This PIN is valid for <strong>15 minutes</strong>.</p>

        <div class="pin-box">
            <div class="label">Your Reset PIN</div>
            <div class="pin-digits">{{ $pin }}</div>
            <div class="expiry">Expires in 15 minutes</div>
        </div>

        <p>Enter this PIN on the login page to set your new password.</p>
        <p style="font-size:13px;color:#9080a8;">If you did not request a password reset, you can safely ignore this email. Your account remains secure.</p>
    </div>
    <div class="footer">
        <p>{{ $clinicName }} &mdash; Dentfluence Infinity</p>
        <p>This is an automated email. Please do not reply.</p>
    </div>
</div>
</body>
</html>
