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
        $request->validate([
            'emails' => 'required|array',
            'emails.*' => 'required|email',
        ]);

        $contract = Contract::findOrFail($contractId);

        // Only creator can invite
        if ($contract->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $results = [];

        foreach ($request->emails as $email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                // Registered user — attach immediately
                $contract->members()->syncWithoutDetaching($user->id);
                $results[] = ['email' => $email, 'status' => 'attached'];
            } else {
                // Not registered — create invite
                Invite::firstOrCreate([
                    'contract_id' => $contract->id,
                    'email' => $email,
                    'invited_by' => $request->user()->id,
                ]);
                // (Optional) Trigger email here
                $results[] = ['email' => $email, 'status' => 'invited'];
            }
        }

        return response()->json([
            'message' => 'Invites processed',
            'results' => $results
        ]);
    }
}
