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
        // 1) Validate input. Require label only if role=submitter.
        $data = $request->validate([
            'emails'        => 'required|array',
            'emails.*'      => 'required|email',
            'role'          => ['required','in:submitter,supervisor'],
            'start_date'    => 'required_if:role,submitter|date',
            'due_date'      => 'nullable|date',
            'supervisor_ids' => 'nullable|array', 
            'supervisor_ids.*' => 'exists:users,id',
            'label'         => 'required_if:role,submitter|in:TA,AA,Intern',
        ]);

        $user     = $request->user();
        $contract = Contract::findOrFail($contractId);

        if (! $contract->owners->contains($user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $results = [];

        foreach ($data['emails'] as $email) {
            if ($invitee = User::where('email', $email)->first()) {
                // Check if this user already exists with a different label
                $existingMember = \DB::table('contract_user')
                    ->where('contract_id', $contract->id)
                    ->where('user_id', $invitee->id)
                    ->where('role', $data['role'])
                    ->where('label', '!=', $data['label'])
                    ->exists();

                $attachData = [
                    'role'          => $data['role'],
                    'start_date'    => $data['start_date'] ?? null,
                    'due_date'      => $data['due_date']   ?? null,
                ];

                // If submitting as a "submitter", include label in the pivot
                if ($data['role'] === 'submitter') {
                    $attachData['label'] = $data['label'];
                }
                
                // Add supervisor_id to attachData if available
                if ($data['role'] === 'submitter' && !empty($data['supervisor_ids'])) {
                    $attachData['supervisor_id'] = $data['supervisor_ids'][0];
                }

                // If user already exists with different label, create a new entry
                if ($existingMember && $data['role'] === 'submitter') {
                    // First check if entry with this exact label already exists
                    $exactMatch = \DB::table('contract_user')
                        ->where('contract_id', $contract->id)
                        ->where('user_id', $invitee->id)
                        ->where('role', $data['role'])
                        ->where('label', $data['label'])
                        ->exists();
                    
                    if (!$exactMatch) {
                        // Create a new entry instead of updating
                        \DB::table('contract_user')->insert([
                            'contract_id' => $contract->id,
                            'user_id' => $invitee->id,
                            'role' => $data['role'],
                            'start_date' => $data['start_date'] ?? null,
                            'due_date' => $data['due_date'] ?? null,
                            'label' => $data['label'],
                            'supervisor_id' => !empty($data['supervisor_ids']) ? $data['supervisor_ids'][0] : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        // Update existing entry with the same label
                        \DB::table('contract_user')
                            ->where('contract_id', $contract->id)
                            ->where('user_id', $invitee->id)
                            ->where('role', $data['role'])
                            ->where('label', $data['label'])
                            ->update([
                                'start_date' => $data['start_date'] ?? null,
                                'due_date' => $data['due_date'] ?? null,
                                'supervisor_id' => !empty($data['supervisor_ids']) ? $data['supervisor_ids'][0] : null,
                                'updated_at' => now(),
                            ]);
                    }
                } else {
                    // Use standard sync if no existing entry with different label
                    $contract->members()->syncWithoutDetaching([
                        $invitee->id => $attachData,
                    ]);
                }
                
                // If role is submitter and we have supervisor_ids, create the relationships
                if ($data['role'] === 'submitter' && !empty($data['supervisor_ids'])) {
                    $this->attachSubmitterToSupervisors($contract->id, $invitee->id, $data['supervisor_ids']);
                }
                
                $results[] = [
                    'email'  => $email,
                    'status' => 'attached',
                    'role'   => $data['role'],
                    'label'  => $data['role'] === 'submitter' ? $data['label'] : null,
                ];
            } else {
                // ─── Brand‐new: queue invite with token + metadata ───
                $payload = [
                    'token'         => Str::uuid(),
                    'contract_id'   => $contract->id,
                    'email'         => $email,
                    'role'          => $data['role'],
                    'start_date'    => $data['start_date'] ?? null,
                    'due_date'      => $data['due_date'] ?? null,
                    'invited_by'    => $user->id,
                ];

                // Only add label when role = 'submitter'
                if ($data['role'] === 'submitter') {
                    $payload['label'] = $data['label'];
                }
                
                // Store supervisor_ids in a JSON field if submitter
                if ($data['role'] === 'submitter' && !empty($data['supervisor_ids'])) {
                    $payload['supervisor_ids_json'] = json_encode($data['supervisor_ids']);
                }

                $invite = Invite::create($payload);

                Mail::to($email)->send(new InviteContract($invite));

                $results[] = [
                    'email'  => $email,
                    'status' => 'invited',
                    'role'   => $data['role'],
                    'label'  => $data['role'] === 'submitter' ? $data['label'] : null,
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
            'start_date'    => $invite->start_date,
            'due_date'      => $invite->due_date,
            'label'         => $invite->label,
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

        // Find all other unconsumed invites for this user for the same contract
        $otherInvites = Invite::where('email', $user->email)
                            ->where('contract_id', $invite->contract_id)
                            ->where('token', '!=', $token)
                            ->where('consumed', false)
                            ->get();

        // Process the current invite
        $this->processInviteAcceptance($invite, $user);
        
        // Process all other invites automatically
        foreach ($otherInvites as $otherInvite) {
            $this->processInviteAcceptance($otherInvite, $user);
        }

        // Mark the invite as consumed
        $invite->consumed = true;
        $invite->save();

        // Mark other invites as consumed
        foreach ($otherInvites as $otherInvite) {
            $otherInvite->consumed = true;
            $otherInvite->save();
        }

        return response()->json([
            'message'       => 'Invitation accepted',
            'contract_id'   => $invite->contract->id,
            'contract_name' => $invite->contract->name,
            'role'          => $invite->role,
            'label'         => $invite->label,
        ]);
    }

/**
 * Helper method to process invite acceptance
 */
private function processInviteAcceptance($invite, $user)
{
    // Check if this user already exists in the contract with a DIFFERENT label
    $existingMember = \DB::table('contract_user')
        ->where('contract_id', $invite->contract_id)
        ->where('user_id', $user->id)
        ->where('role', $invite->role)
        ->where('label', '!=', $invite->label)
        ->exists();

    // Create attach data for the contract
    $attachData = [
        'role'          => $invite->role,
        'start_date'    => $invite->start_date,
        'due_date'      => $invite->due_date,
    ];
    
    // Add label if this is a submitter
    if ($invite->role === 'submitter') {
        $attachData['label'] = $invite->label;
    }
    
    // Get supervisor IDs if available
    $supervisorIds = [];
    if (!empty($invite->supervisor_ids_json)) {
        $supervisorIds = json_decode($invite->supervisor_ids_json);
    }
    
    // Add supervisor_id to attachData if available
    if ($invite->role === 'submitter' && !empty($supervisorIds)) {
        $attachData['supervisor_id'] = $supervisorIds[0];
    }

    // If user already exists with different label, we need to create a new entry
    // rather than update the existing one
    if ($existingMember) {
        // Use raw DB insert to avoid unique constraint issues
        \DB::table('contract_user')->insert([
            'contract_id' => $invite->contract_id,
            'user_id' => $user->id,
            'role' => $invite->role,
            'start_date' => $invite->start_date,
            'due_date' => $invite->due_date,
            'label' => $invite->label,
            'supervisor_id' => !empty($supervisorIds) ? $supervisorIds[0] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } else {
        // Attach to contract normally if no existing entry with different label
        $invite->contract->members()->syncWithoutDetaching([
            $user->id => $attachData,
        ]);
    }

    // If this is a submitter and has supervisor_ids_json, attach to supervisors
    if ($invite->role === 'submitter' && !empty($invite->supervisor_ids_json)) {
        $supervisorIds = json_decode($invite->supervisor_ids_json);
        if (is_array($supervisorIds)) {
            $this->attachSubmitterToSupervisors($invite->contract_id, $user->id, $supervisorIds);
        }
    }
}

    /**
     * PATCH /api/invites/{id}
     */
    public function update(Request $request, $id)
    {
        $invite = Invite::findOrFail($id);

        $data = $request->validate([
            'role'          => ['required','in:submitter,supervisor'],
            'start_date'    => 'nullable|date',
            'due_date'      => 'nullable|date',
            'supervisor_ids' => 'nullable|array',
            'supervisor_ids.*' => 'exists:users,id',
            'label'         => 'required_if:role,submitter|in:TA,AA,Intern',
        ]);

        // Create the update payload
        $updateData = [
            'role'       => $data['role'],
            'start_date' => $data['start_date'] ?? null,
            'due_date'   => $data['due_date'] ?? null,
        ];
        
        // Add label if submitter
        if ($data['role'] === 'submitter') {
            $updateData['label'] = $data['label'];
        }
        
        // Add supervisor_ids_json if provided
        if ($data['role'] === 'submitter' && !empty($data['supervisor_ids'])) {
            $updateData['supervisor_ids_json'] = json_encode($data['supervisor_ids']);
            
            // If the invite has been consumed, update the submitter-supervisor relationships
            if ($invite->consumed) {
                // Find the user who accepted this invite
                $user = User::where('email', $invite->email)->first();
                if ($user) {
                    $this->attachSubmitterToSupervisors($invite->contract_id, $user->id, $data['supervisor_ids']);
                }
            }
        }

        $invite->update($updateData);

        return response()->json([
            'message' => 'Invite updated',
            'invite'  => $invite->only(['id','email','role','start_date','due_date','supervisor_ids_json','consumed','label']),
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
                         ->get(['id','email','role','invited_by','consumed','label']);
        return response()->json($invites);
    }

    /**
     * Helper method to attach a submitter to multiple supervisors
     */
    public function attachSubmitterToSupervisors($contractId, $submitterId, $supervisorIds)
    {
        foreach ($supervisorIds as $supervisorId) {
            SubmitterSupervisor::updateOrCreate([
                'contract_id' => $contractId,
                'submitter_id' => $submitterId,
                'supervisor_id' => $supervisorId,
            ]);
        }
    }
}