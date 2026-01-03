@component('mail::message')
# Welcome to BlaFast!

Hello **{{ $userName }}**,

Welcome to BlaFast. Your account has been successfully created and you're ready to get started.

## What's Next?

You can now log in and start using the platform. Here are some things you can do:

- Complete your profile
- Explore the dashboard
- Invite team members
- Set up your organization

@component('mail::button', ['url' => $actionUrl])
Get Started
@endcomponent

If you have any questions or need assistance, our support team is here to help.

Thanks,<br>
{{ config('app.name') }}

@component('mail::subcopy')
If you did not create this account, please contact our support team immediately.
@endcomponent
@endcomponent
