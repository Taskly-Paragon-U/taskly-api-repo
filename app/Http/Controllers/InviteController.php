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
use Illuminate\Support\Facades\Log;

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

        // Check if user has permission to invite
        $isOwner = $contract->members()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'owner')
            ->exists();

        if (!$isOwner) {
            return response()->json(['message' => 'Forbidden - Only contract owners can invite members'], 403);
        }

        $results = [];

        if ($data['role'] === 'supervisor') {
            foreach ($data['emails'] as $email) {
                Log::info("Processing supervisor invite for email: {$email}");
                
                // Check if user already exists
                $invitee = User::where('email', $email)->first();
                
                if ($invitee) {
                    Log::info("User exists with ID: {$invitee->id}");
                    
                    // Check if already a member of this contract
                    $existingMembership = $contract->members()
                        ->where('user_id', $invitee->id)
                        ->first();

                    if ($existingMembership) {
                        // Update their role to supervisor if they're not already
                        if ($existingMembership->pivot->role !== 'supervisor') {
                            $contract->members()->updateExistingPivot($invitee->id, [
                                'role' => 'supervisor',
                                'updated_at' => now(),
                            ]);
                            Log::info("Updated existing member {$invitee->id} to supervisor role");
                        }
                    } else {
                        // Add them as a new supervisor
                        $contract->members()->attach($invitee->id, [
                            'role' => 'supervisor',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        Log::info("Added new supervisor member {$invitee->id}");
                    }

                    $results[] = [
                        'email' => $email,
                        'status' => 'attached',
                        'role' => 'supervisor',
                        'user_id' => $invitee->id,
                    ];
                } else {
                    // User doesn't exist, create invite
                    Log::info("User doesn't exist, creating invite for: {$email}");
                    
                    $invite = Invite::create([
                        'token' => Str::uuid(),
                        'contract_id' => $contract->id,
                        'email' => $email,
                        'role' => 'supervisor',
                        'invited_by' => $user->id,
                    ]);

                    // Send email invite
                    try {
                        Mail::to($email)->send(new InviteContract($invite));
                        Log::info("Email sent successfully to: {$email}");
                    } catch (\Exception $e) {
                        Log::error("Failed to send email to {$email}: " . $e->getMessage());
                    }

                    $results[] = [
                        'email' => $email,
                        'status' => 'invited',
                        'role' => 'supervisor',
                        'invite_id' => $invite->id,
                    ];
                }
            }
        } else {
            // Handle submitter invitations
            $submitters = $data['submitters'] ?? [];
            
            // If no submitters array provided, create simple submitter invites
            if (empty($submitters)) {
                foreach ($data['emails'] as $email) {
                    $submitters[] = [
                        'email' => $email,
                        'label' => null,
                        'supervisor_ids' => [],
                        'start_date' => null,
                        'end_date' => null,
                    ];
                }
            }
            
            foreach ($submitters as $submitterData) {
                $email = $submitterData['email'];
                $label = $submitterData['label'] ?? null;
                $supervisorIds = $submitterData['supervisor_ids'] ?? [];
                $startDate = $submitterData['start_date'] ?? null;
                $endDate = $submitterData['end_date'] ?? null;

                Log::info("Processing submitter invite for email: {$email}");

                $invitee = User::where('email', $email)->first();
                
                if ($invitee) {
                    Log::info("Submitter user exists with ID: {$invitee->id}");
                    
                    // Check if already a member
                    $existingMembership = $contract->members()
                        ->where('user_id', $invitee->id)
                        ->first();

                    if (!$existingMembership) {
                        // Add as new submitter
                        $contract->members()->attach($invitee->id, [
                            'role' => 'submitter',
                            'label' => $label,
                            'start_date' => $startDate,
                            'due_date' => $endDate,
                            'supervisor_id' => !empty($supervisorIds) ? $supervisorIds[0] : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        // Handle multiple supervisors
                        if (!empty($supervisorIds)) {
                            $this->attachSubmitterToSupervisors($contract->id, $invitee->id, $supervisorIds);
                        }
                        
                        Log::info("Added new submitter member {$invitee->id}");
                    }

                    $results[] = [
                        'email' => $email,
                        'status' => 'attached',
                        'role' => 'submitter',
                        'label' => $label,
                        'user_id' => $invitee->id,
                    ];
                } else {
                    // Create invite for non-existing user
                    Log::info("Submitter user doesn't exist, creating invite for: {$email}");
                    
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

                    try {
                        Mail::to($email)->send(new InviteContract($invite));
                        Log::info("Email sent successfully to: {$email}");
                    } catch (\Exception $e) {
                        Log::error("Failed to send email to {$email}: " . $e->getMessage());
                    }

                    $results[] = [
                        'email' => $email,
                        'status' => 'invited',
                        'role' => 'submitter',
                        'label' => $label,
                        'invite_id' => $invite->id,
                    ];
                }
            }
        }

        Log::info("Invite processing completed", ['results' => $results]);

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

        Log::info("User {$user->id} accepting invite {$invite->id} for role {$invite->role}");

        if ($user->email !== $invite->email) {
            return response()->json([
                'message' => 'This invitation was sent to '.$invite->email,
            ], 403);
        }

        $contract = Contract::findOrFail($invite->contract_id);

        // Check if already a member of this contract
        $existingMembership = $contract->members()
            ->where('user_id', $user->id)
            ->first();

        if (!$existingMembership) {
            // Add as new member
            $contract->members()->attach($user->id, [
                'role' => $invite->role,
                'start_date' => $invite->start_date,
                'due_date' => $invite->due_date,
                'label' => $invite->label,
                'supervisor_id' => null, // Will be set below if needed
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::info("Added user {$user->id} as {$invite->role} to contract {$contract->id}");
        } else {
            // Update existing membership if role is different
            if ($existingMembership->pivot->role !== $invite->role) {
                $contract->members()->updateExistingPivot($user->id, [
                    'role' => $invite->role,
                    'start_date' => $invite->start_date,
                    'due_date' => $invite->due_date,
                    'label' => $invite->label,
                    'updated_at' => now(),
                ]);
                
                Log::info("Updated user {$user->id} role to {$invite->role} in contract {$contract->id}");
            }
        }

        // Handle supervisor relationships for submitters
        if ($invite->role === 'submitter' && !empty($invite->supervisor_ids_json)) {
            $supervisorIds = json_decode($invite->supervisor_ids_json, true);
            if ($supervisorIds) {
                $this->attachSubmitterToSupervisors($invite->contract_id, $user->id, $supervisorIds);
                Log::info("Attached submitter {$user->id} to supervisors: " . implode(', ', $supervisorIds));
            }
        }

        // Mark invite as consumed
        $invite->consumed = true;
        $invite->save();

        Log::info("Invite {$invite->id} marked as consumed");

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
        // Remove existing relationships
        SubmitterSupervisor::where('contract_id', $contractId)
                           ->where('submitter_id', $submitterId)
                           ->delete();

        // Create new relationships
        foreach ($supervisorIds as $supervisorId) {
            SubmitterSupervisor::create([
                'contract_id' => $contractId,
                'submitter_id' => $submitterId,
                'supervisor_id' => $supervisorId,
            ]);
        }
    }
}