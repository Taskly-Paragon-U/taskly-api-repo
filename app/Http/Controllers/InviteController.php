<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;                    
use Illuminate\Support\Facades\Mail;
use App\Mail\InviteContract;
use App\Models\SubmitterSupervisor;

class InviteController extends Controller
{
    /**
     * POST /api/contracts/{id}/invite
     */
    public function invite(Request $request, $contractId)
    {
        $data = $request->validate([
            'emails' => 'required|array',
            'emails.*' => 'required|email',
            'role' => 'required|string|in:supervisor,submitter',
            'submitters' => 'sometimes|array',
            'submitters.*.email' => 'required_with:submitters|email',
            'submitters.*.label' => 'nullable|string',
            'submitters.*.supervisor_ids' => 'nullable|array',
            'submitters.*.supervisor_ids.*' => 'exists:users,id',
            'submitters.*.start_date' => 'nullable|date',
            'submitters.*.end_date' => 'nullable|date',
        ]);

        $user = $request->user();
        $contract = Contract::findOrFail($contractId);

        if (!$contract->owners->contains($user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $results = [];

        if ($data['role'] === 'supervisor') {
            foreach ($data['emails'] as $email) {
                if ($invitee = User::where('email', $email)->first()) {
                    $existingEntry = \DB::table('contract_user')
                        ->where('contract_id', $contract->id)
                        ->where('user_id', $invitee->id)
                        ->where('role', 'supervisor')
                        ->exists();

                    if (!$existingEntry) {
                        \DB::table('contract_user')->insert([
                            'contract_id' => $contract->id,
                            'user_id' => $invitee->id,
                            'role' => 'supervisor',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $results[] = [
                        'email' => $email,
                        'status' => 'attached',
                        'role' => 'supervisor',
                    ];
                } else {
                    $invite = Invite::create([
                        'token' => Str::uuid(),
                        'contract_id' => $contract->id,
                        'email' => $email,
                        'role' => 'supervisor',
                        'invited_by' => $user->id,
                    ]);

                    Mail::to($email)->send(new InviteContract($invite));

                    $results[] = [
                        'email' => $email,
                        'status' => 'invited',
                        'role' => 'supervisor',
                    ];
                }
            }
        } else {
            $submitters = $data['submitters'] ?? [];
            
            foreach ($submitters as $submitterData) {
                $email = $submitterData['email'];
                $label = $submitterData['label'] ?? null;
                $supervisorIds = $submitterData['supervisor_ids'] ?? [];
                $startDate = $submitterData['start_date'] ?? null;
                $endDate = $submitterData['end_date'] ?? null;

                if ($invitee = User::where('email', $email)->first()) {
                    $existingEntry = \DB::table('contract_user')
                        ->where('contract_id', $contract->id)
                        ->where('user_id', $invitee->id)
                        ->where('role', 'submitter')
                        ->where('label', $label)
                        ->exists();

                    if (!$existingEntry) {
                        \DB::table('contract_user')->insert([
                            'contract_id' => $contract->id,
                            'user_id' => $invitee->id,
                            'role' => 'submitter',
                            'label' => $label,
                            'start_date' => $startDate,
                            'due_date' => $endDate,
                            'supervisor_id' => !empty($supervisorIds) ? $supervisorIds[0] : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    if (!empty($supervisorIds)) {
                        $this->attachSubmitterToSupervisors($contract->id, $invitee->id, $supervisorIds);
                    }

                    $results[] = [
                        'email' => $email,
                        'status' => 'attached',
                        'role' => 'submitter',
                        'label' => $label,
                    ];
                } else {
                    $invite = Invite::create([
                        'token' => Str::uuid(),
                        'contract_id' => $contract->id,
                        'email' => $email,
                        'role' => 'submitter',
                        'invited_by' => $user->id,
                        'label' => $label,
                        'start_date' => $startDate,
                        'due_date' => $endDate,
                        'supervisor_ids_json' => !empty($supervisorIds) ? json_encode($supervisorIds) : null,
                    ]);

                    Mail::to($email)->send(new InviteContract($invite));

                    $results[] = [
                        'email' => $email,
                        'status' => 'invited',
                        'role' => 'submitter',
                        'label' => $label,
                    ];
                }
            }
        }

        return response()->json([
            'message' => 'Invites processed successfully',
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

        $supervisorIds = [];
        if (!empty($invite->supervisor_ids_json)) {
            $supervisorIds = json_decode($invite->supervisor_ids_json, true);
        }

        return response()->json([
            'contract_id'   => $invite->contract->id,
            'contract_name' => $invite->contract->name,
            'email'         => $invite->email,
            'role'          => $invite->role,
            'start_date'    => $invite->start_date,
            'due_date'      => $invite->due_date,
            'label'         => $invite->label,
            'supervisor_ids' => $supervisorIds,
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

        $existingEntry = \DB::table('contract_user')
            ->where('contract_id', $invite->contract_id)
            ->where('user_id', $user->id)
            ->where('role', $invite->role)
            ->exists();

        if (!$existingEntry) {
            \DB::table('contract_user')->insert([
                'contract_id' => $invite->contract_id,
                'user_id' => $user->id,
                'role' => $invite->role,
                'start_date' => $invite->start_date,
                'due_date' => $invite->due_date,
                'label' => $invite->label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($invite->role === 'submitter' && !empty($invite->supervisor_ids_json)) {
            $supervisorIds = json_decode($invite->supervisor_ids_json, true);
            $this->attachSubmitterToSupervisors($invite->contract_id, $user->id, $supervisorIds);
        }

        $invite->consumed = true;
        $invite->save();

        return response()->json([
            'message' => 'Invitation accepted successfully',
            'contract_id' => $invite->contract->id,
            'contract_name' => $invite->contract->name,
            'role' => $invite->role,
        ]);
    }

    /**
     * GET /api/contracts/{id}/invites
     */
    public function listByContract($contractId)
    {
        $invites = Invite::where('contract_id', $contractId)
                         ->orderBy('created_at', 'desc')
                         ->get(['id', 'email', 'role', 'invited_by', 'consumed', 'label', 'start_date', 'due_date']);

        return response()->json($invites);
    }

    /**
     * Helper method to attach a submitter to multiple supervisors
     */
    private function attachSubmitterToSupervisors($contractId, $submitterId, $supervisorIds)
    {
        SubmitterSupervisor::where('contract_id', $contractId)
                           ->where('submitter_id', $submitterId)
                           ->delete();

        foreach ($supervisorIds as $supervisorId) {
            SubmitterSupervisor::create([
                'contract_id' => $contractId,
                'submitter_id' => $submitterId,
                'supervisor_id' => $supervisorId,
            ]);
        }
    }
}