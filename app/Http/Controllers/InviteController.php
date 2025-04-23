<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Models\Invite;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function invite(Request $request, $contractId)
    {
        // 1) Validate input
        $data = $request->validate([
            'emails'    => 'required|array',
            'emails.*'  => 'required|email',
            'role'      => ['required','in:submitter,supervisor'],
        ]);

        $user = $request->user();
        $contract = Contract::findOrFail($contractId);

        // 2) Only the contract owner may invite
        if (! $contract->owners->contains($user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $results = [];

        foreach ($data['emails'] as $email) {
            $invitee = User::where('email', $email)->first();

            if ($invitee) {
                // 3) Attach existing user with the correct role
                $contract->members()
                         ->syncWithoutDetaching([
                             $invitee->id => ['role' => $data['role']]
                         ]);

                $results[] = [
                    'email'  => $email,
                    'status' => 'attached',
                    'role'   => $data['role'],
                ];
            } else {
                // 4) Create a pending invite, storing the role
                $invite = Invite::firstOrCreate(
                    [
                        'contract_id' => $contract->id,
                        'email'       => $email,
                    ],
                    [
                        'invited_by'  => $user->id,
                        'role'        => $data['role'],
                    ]
                );

                $results[] = [
                    'email'  => $email,
                    'status' => 'invited',
                    'role'   => $data['role'],
                ];
            }
        }

        return response()->json([
            'message' => 'Invites processed',
            'results' => $results,
        ], 200);
    }
}
