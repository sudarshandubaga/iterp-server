<!doctype html>
<html lang="en" style="background:#f4f5f7;">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset your password</title>
</head>
<body style="margin:0;padding:32px 12px;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e6e8eb;">
    <div style="background:#4f46e5;padding:22px 28px;">
      <h1 style="margin:0;color:#ffffff;font-size:20px;letter-spacing:.3px;">Reset your password</h1>
    </div>
    <div style="padding:28px;">
      <p style="margin:0 0 14px;color:#1f2937;font-size:16px;line-height:1.5;">Hi<?php if (!empty($data['name'])) : ?> <?= e($data['name']) ?><?php endif; ?>,</p>
      <p style="margin:0 0 20px;color:#374151;font-size:15px;line-height:1.6;">
        We received a request to reset the password for your <strong>Iterp</strong> account. Use the button below to choose a new password. This link expires in <strong><?= e($data['expires'] ?? '1 hour') ?></strong> and can only be used once.
      </p>
      <p style="margin:0 0 26px;text-align:center;">
        <a href="<?= e($data['url']) ?>" style="display:inline-block;padding:13px 30px;background:#4f46e5;color:#ffffff;text-decoration:none;border-radius:6px;font-size:15px;font-weight:600;">Reset Password</a>
      </p>
      <p style="margin:0 0 8px;color:#6b7280;font-size:13px;line-height:1.5;">Or copy and paste this link into your browser:</p>
      <p style="margin:0;color:#6b7280;font-size:13px;word-break:break-all;"><?= e($data['url']) ?></p>
    </div>
    <div style="background:#f9fafb;padding:14px 28px;border-top:1px solid #eef0f3;">
      <p style="margin:0;color:#9ca3af;font-size:12px;">If you did not request a password reset, you can safely ignore this email.</p>
    </div>
  </div>
</body>
</html>