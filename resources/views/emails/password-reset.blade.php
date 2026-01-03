@component('mail::message')
# Password Reset Request

Hello,

You are receiving this email because we received a password reset request for your account.

@component('mail::button', ['url' => $resetUrl])
Reset Password
@endcomponent

**This password reset link will expire in {{ $expiryMinutes }} minutes.**

If you did not request a password reset, no further action is required. Your password will remain unchanged.

## Security Tips

- Never share your password with anyone
- Use a strong, unique password
- Enable two-factor authentication if available

Thanks,<br>
{{ config('app.name') }}

@component('mail::subcopy')
If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:

[{{ $resetUrl }}]({{ $resetUrl }})
@endcomponent
@endcomponent
