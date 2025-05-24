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
// inside InviteController.php

    public function invite(Request $request, $contractId)
    {
        // 1) Validate input, now including start_date & due_date
        $data = $request->validate([
            'emails'     => 'required|array',
            'emails.*'   => 'required|email',
            'role'       => ['required','in:submitter,supervisor'],
            'start_date' => 'required_if:role,submitter|date',
            'due_date'   => 'nullable|date',
            'supervisor_id' => 'nullable|integer|exists:users,id',
        ]);

        $user     = $request->user();
        $contract = Contract::findOrFail($contractId);

        if (! $contract->owners->contains($user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $results = [];

        foreach ($data['emails'] as $email) {
            if ($invitee = User::where('email', $email)->first()) {
                // existing user → attach immediately, writing start/due dates into the pivot
                $contract->members()->syncWithoutDetaching([
                    $invitee->id => [
                        'role'         => $data['role'],
                        'start_date'   => $data['start_date'] ?? null,
                        'due_date'     => $data['due_date']   ?? null,
                        'supervisor_id'=> $data['supervisor_id'] ?? null,
                    ],
                ]);

                $results[] = [
                    'email'  => $email,
                    'status' => 'attached',
                    'role'   => $data['role'],
                ];
            } else {
                // brand-new → queue invite with token + metadata
                $invite = Invite::create([
                    'token'         => Str::uuid(),
                    'contract_id'   => $contract->id,
                    'email'         => $email,
                    'role'          => $data['role'],
                    'start_date'    => $data['start_date']    ?? null,
                    'due_date'      => $data['due_date']      ?? null,
                    'supervisor_id' => $data['supervisor_id'] ?? null,
                    'invited_by'    => $user->id,
                ]);

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

        // Attach to contract on the same 'members()' relation
        // so we get start_date, due_date & supervisor_id too.
        $invite->contract
            ->members()
            ->syncWithoutDetaching([
                $user->id => [
                    'role'          => $invite->role,
                    'start_date'    => $invite->start_date,
                    'due_date'      => $invite->due_date,
                    'supervisor_id' => $invite->supervisor_id,
                ],
            ]);

        // Mark the invite as consumed
        $invite->consumed = true;
        $invite->save();

        return response()->json([
            'message'       => 'Invitation accepted',
            'contract_id'   => $invite->contract->id,
            'contract_name' => $invite->contract->name,
            'role'          => $invite->role,
        ]);
    }

    /**
     * PATCH /api/invites/{id}
     */
    public function update(Request $request, $id)
    {
        $invite = Invite::findOrFail($id);

        $data = $request->validate([
            'role'       => ['required','in:submitter,supervisor'],
            'start_date' => 'nullable|date',
            'due_date'   => 'nullable|date',
            'supervisor_id' => 'nullable|integer|exists:users,id',
        ]);

        $invite->update($data);

        return response()->json([
            'message' => 'Invite updated',
            'invite'  => $invite->only(['id','email','role','start_date','due_date','supervisor_id','consumed']),
        ], 200);
    }

    /**
     * DELETE /api/invites/{id}
     */
    public function destroy($id)
    {
        $invite = Invite::findOrFail($id);
        $invite->delete();

        return response()->json([
            'message' => 'Invite removed',
        ], 200);
    }

    public function listByContract($contractId)
    {
        $invites = Invite::where('contract_id', $contractId)
                         ->orderBy('created_at', 'desc')
                         ->get(['id','email','role','invited_by','consumed']);
        return response()->json($invites);
    }
}
