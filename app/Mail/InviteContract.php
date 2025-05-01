<?php

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteContract extends Mailable
{
    use Queueable, SerializesModels;

    public Invite $invite;

    /**
     * Create a new message instance.
     */
    public function __construct(Invite $invite)
    {
        $this->invite = $invite;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join contract “{$this->invite->contract->name}”"
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontend = config('app.frontend_url', env('FRONTEND_URL'));
        $inviteLink = rtrim($frontend, '/') . '/invites/' . $this->invite->token;

        return new Content(
            markdown: 'emails.invites.contract',
            with: [
                'contractName' => $this->invite->contract->name,
                'role'         => $this->invite->role,
                'inviteLink'   => $inviteLink,
                'inviterName'  => $this->invite->inviter->name,
            ],
        );
    }

    /**
     * Attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
