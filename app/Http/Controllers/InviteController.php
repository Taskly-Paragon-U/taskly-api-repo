<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;                    
use Illuminate\Support\Facades\Mail;
use App\Mail\InviteContract;

class InviteController extends Controller
{
    /**
     * POST /api/contracts/{id}/invite
     */
    public function invite(Request $request, $contractId)
    {
        
        // 1) Validate input
        $data = $request->validate([
            'emails'    => 'required|array',
            'emails.*'  => 'required|email',
            'role'      => ['required','in:submitter,supervisor'],
            'major'          => 'nullable|string',
            'contract_start' => 'nullable|date',
            'contract_end'   => 'nullable|date',
            'supervisor_id'  => 'nullable|exists:users,id',
        ]);

        $user = $request->user();
        $contract = Contract::findOrFail($contractId);

        // 2) Only the contract owner may invite
        if (! $contract->owners->contains($user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $results = [];

        foreach ($data['emails'] as $email) {
            if ($invitee = User::where('email', $email)->first()) {
                $contract->members()
                         ->syncWithoutDetaching([
                             $invitee->id => ['role' => $data['role']],
                         ]);

                $results[] = [
                    'email'  => $email,
                    'status' => 'attached',
                    'role'   => $data['role'],
                ];
            }else {
                
                // 2) NEW EMAIL: create a full Invite record *including* token
            $invite = Invite::create([
                'token'          => Str::uuid(),
                'contract_id'    => $contract->id,
                'email'          => $email,
                'role'           => $data['role'],
                'invited_by'     => $user->id,
                'major'          => $data['major'] ?? null,
                'contract_start' => $data['contract_start'] ?? null,
                'contract_end'   => $data['contract_end'] ?? null,
                'supervisor_id'  => $data['supervisor_id'] ?? null,
            ]);


                // 3) Send the invitation email
                Mail::to($email)->send(new InviteContract($invite));

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

    /**
     * GET /api/invites/{token}
     */
    public function show($token)
    {
        $invite = Invite::where('token', $token)
                        ->where('consumed', false)
                        ->with('contract:id,name')
                        ->firstOrFail();

        return response()->json([
            'contract_id'   => $invite->contract->id,
            'contract_name' => $invite->contract->name,
            'email'         => $invite->email,
            'role'          => $invite->role,
        ]);
    }

    /**
     * POST /api/invites/{token}/accept
     */
    public function accept(Request $request, $token)
    {
        $user = $request->user();

        $invite = Invite::where('token', $token)
                        ->where('consumed', false)
                        ->firstOrFail();

        if ($user->email !== $invite->email) {
            return response()->json([
                'message' => 'This invitation was sent to '.$invite->email,
            ], 403);
        }

        // Attach to contract
        $invite->contract
               ->users()
               ->syncWithoutDetaching([
                   $user->id => ['role' => $invite->role],
               ]);

        // Mark invite consumed
        $invite->consumed = true;
        $invite->save();

        return response()->json([
            'message'       => 'Invitation accepted',
            'contract_id'   => $invite->contract->id,
            'contract_name' => $invite->contract->name,
            'role'          => $invite->role,
        ]);
    }

    public function listByContract($contractId)
    {
        $invites = Invite::where('contract_id', $contractId)
                         ->orderBy('created_at', 'desc')
                         ->get(['id','email','role','invited_by','consumed']);
        return response()->json($invites);
    }
}
