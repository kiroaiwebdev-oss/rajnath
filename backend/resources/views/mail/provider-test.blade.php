<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#fdfbf6;font-family:Arial,Helvetica,sans-serif;color:#141a3c">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
    <table role="presentation" width="100%" style="max-width:520px;background:#ffffff;border:1px solid #d7d9e8;border-radius:16px">
        <tr><td style="padding:28px">
            <p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#8a6514">{{ config('brand')['brand.name'] }}</p>
            <h1 style="margin:12px 0 0;font-size:20px">Email is working</h1>
            <p style="margin:12px 0 0;font-size:15px;line-height:22px;color:#444d80">This test message was sent from Admin → Email &amp; SMS by {{ $sentBy }} at {{ now('Asia/Kolkata')->format('d M Y, H:i') }} IST. Your SMTP settings can deliver mail.</p>
        </td></tr>
    </table>
</td></tr></table>
</body>
</html>
