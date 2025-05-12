@component('mail::message')
# You’ve been invited!

Hi there,

**{{ $inviterName }}** has invited you to join the **"{{ $contractName }}"** contract as a **{{ ucfirst($role) }}**.

@component('mail::button', ['url' => $inviteLink])
Accept Invitation
@endcomponent

If you don't have a Taskly account yet, you'll be prompted to sign up (pre-filled with your email).  
If you already have an account, just log in and you'll be added automatically.

Thanks,<br>
Taskly
{{-- {{ config('app.name') }} --}}
@endcomponent
