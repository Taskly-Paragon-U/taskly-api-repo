<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invite;
use App\Models\Contract;
use App\Models\SubmitterSupervisor;
use App\Models\SubmitterLabel;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    /**
     * Create a new contract and attach the creator as owner.
     */
    public function store(Request $request)
    {
        // ── BLOCK GMAIL USERS HERE ──
        $domain = Str::after($request->user()->email, '@');
        if ($domain === 'gmail.com') {
            return response()->json([
                'message' => 'You are not allowed to create new contracts.'
            ], 403);
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'details' => 'required|string',
        ]);

        $contract = Contract::create([
            'user_id' => $request->user()->id,
            'name'    => $validated['name'],
            'details' => $validated['details'],
        ]);

        $contract->members()->attach(
            $request->user()->id,
            ['role' => 'owner']
        );

        return response()->json([
            'message'  => 'Contract created',
            'contract' => $contract->load('members'),
        ], 201);
    }

    /**
     * Get contracts where user is creator OR a member (any role).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $contracts = Contract::where('user_id', $user->id)
            ->orWhereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->latest()
            ->get();

        return response()->json($contracts);
    }

    /**
     * Show a single contract with its members (including pivot data).
     */
    public function show($id)
    {
        $contract = Contract::findOrFail($id);

        // Load members with their multiple supervisors and labels
        $members = $contract->members()->get()->map(function ($member) use ($contract) {
            // Get supervisor relationships from SubmitterSupervisor table
            $supervisorRelationships = SubmitterSupervisor::where('contract_id', $contract->id)
                ->where('submitter_id', $member->id)
                ->with('supervisor')
                ->get();
            
            $supervisorIds = $supervisorRelationships->pluck('supervisor_id')->toArray();
            $supervisorNames = $supervisorRelationships->pluck('supervisor.name')->toArray();
            
            // Get label relationships from SubmitterLabel table
            $labelRelationships = SubmitterLabel::where('contract_id', $contract->id)
                ->where('submitter_id', $member->id)
                ->get();
            
            $labels = $labelRelationships->pluck('label')->toArray();
            
            // Fallback to pivot table data if no relationships exist
            if (empty($supervisorIds) && $member->pivot->supervisor_id) {
                $supervisorUser = User::find($member->pivot->supervisor_id);
                if ($supervisorUser) {
                    $supervisorIds = [$member->pivot->supervisor_id];
                    $supervisorNames = [$supervisorUser->name];
                }
            }
            
            if (empty($labels) && $member->pivot->label) {
                $labels = [$member->pivot->label];
            }

            return [
                'id'               => $member->id,
                'name'             => $member->name,
                'email'            => $member->email,
                'role'             => $member->pivot->role,
                'supervisorId'     => $member->pivot->supervisor_id,  
                'supervisorName'   => !empty($supervisorNames) ? $supervisorNames[0] : null,
                'startDate'        => $member->pivot->start_date,
                'endDate'          => $member->pivot->due_date,
                'label'            => $member->pivot->label, // Keep for backward compatibility
                'pivot_id'         => $member->pivot->id,
                'supervisor_ids_json' => json_encode($supervisorIds), 
                // New structure for frontend
                'supervisorIds'    => $supervisorIds,
                'supervisorNames'  => $supervisorNames,
                'labels'           => $labels,
            ];
        });

        return response()->json([
            'contract' => [
                'id'      => $contract->id,
                'name'    => $contract->name,
                'details' => $contract->details,
                'members' => $members,
            ]
        ], 200);
    }

    /**
     * PATCH /api/contracts/{contractId}/members/{userId}
     * Update a member's details in a contract - SUPPORTS MULTIPLE SUPERVISORS & LABELS
     */
    public function updateMember(Request $request, $contractId, $userId)
    {
        // 1) Find the contract and user
        $contract = Contract::findOrFail($contractId);
        $user = User::findOrFail($userId);
        
        // 2) Check if the requesting user has permission to update
        $requestingUser = $request->user();
        if (!$contract->members()->where('user_id', $requestingUser->id)->wherePivot('role', 'owner')->exists()) {
            return response()->json(['message' => 'Forbidden - Only contract owners can update members'], 403);
        }

        // 3) Validate exactly the fields we want
        $data = $request->validate([
            'start_date'     => 'sometimes|nullable|date',
            'due_date'       => 'sometimes|nullable|date',
            'label'          => 'sometimes|nullable|in:TA,AA,Intern',
            'labels'         => 'sometimes|nullable|array', 
            'labels.*'       => 'sometimes|string|in:TA,AA,Intern',
            'supervisor_id'  => 'sometimes|nullable|exists:users,id',
            'supervisor_ids' => 'sometimes|nullable|array',
            'supervisor_ids.*' => 'sometimes|exists:users,id',
        ]);

        // 4) Ensure the user actually belongs to this contract
        if (!$contract->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Member not on this contract'], 404);
        }

        // 5) Build up the pivot‐table update array (for backward compatibility)
        $pivotData = [];
        if (array_key_exists('start_date', $data)) {
            $pivotData['start_date'] = $data['start_date'];
        }
        if (array_key_exists('due_date', $data)) {
            $pivotData['due_date'] = $data['due_date'];
        }

        // 6) Handle labels - use both old and new approach
        if (isset($data['labels'])) {
            // Delete all existing labels for this submitter in this contract
            SubmitterLabel::where('contract_id', $contract->id)
                ->where('submitter_id', $user->id)
                ->delete();

            // Create new label relationships
            foreach ($data['labels'] as $label) {
                SubmitterLabel::create([
                    'contract_id' => $contract->id,
                    'submitter_id' => $user->id,
                    'label' => $label,
                ]);
            }
            
            // Keep first label in pivot table for backward compatibility
            $pivotData['label'] = !empty($data['labels']) ? $data['labels'][0] : null;
        } elseif (array_key_exists('label', $data)) {
            // Handle single label (backward compatibility)
            SubmitterLabel::where('contract_id', $contract->id)
                ->where('submitter_id', $user->id)
                ->delete();
                
            if ($data['label']) {
                SubmitterLabel::create([
                    'contract_id' => $contract->id,
                    'submitter_id' => $user->id,
                    'label' => $data['label'],
                ]);
            }
            
            $pivotData['label'] = $data['label'];
        }

        // 7) Handle supervisors - use both old and new approach
        if (isset($data['supervisor_ids'])) {
            // Delete all existing supervisor relationships
            SubmitterSupervisor::where('contract_id', $contract->id)
                ->where('submitter_id', $user->id)
                ->delete();

            // Create new supervisor relationships
            foreach ($data['supervisor_ids'] as $supId) {
                SubmitterSupervisor::create([
                    'contract_id'  => $contract->id,
                    'submitter_id' => $user->id,
                    'supervisor_id'=> $supId,
                ]);
            }
            
            // Set primary supervisor in pivot table for backward compatibility
            $pivotData['supervisor_id'] = !empty($data['supervisor_ids']) ? $data['supervisor_ids'][0] : null;
        } elseif (array_key_exists('supervisor_id', $data)) {
            // Handle single supervisor (backward compatibility)
            SubmitterSupervisor::where('contract_id', $contract->id)
                ->where('submitter_id', $user->id)
                ->delete();
                
            if ($data['supervisor_id']) {
                SubmitterSupervisor::create([
                    'contract_id'  => $contract->id,
                    'submitter_id' => $user->id,
                    'supervisor_id'=> $data['supervisor_id'],
                ]);
            }
            
            $pivotData['supervisor_id'] = $data['supervisor_id'];
        }

        // 8) Update the pivot table with backward compatibility fields
        if (!empty($pivotData)) {
            $pivotData['updated_at'] = now();
            $contract->members()->updateExistingPivot($user->id, $pivotData);
        }

        // 9) Get updated data for response
        $updatedLabels = SubmitterLabel::where('contract_id', $contract->id)
            ->where('submitter_id', $user->id)
            ->pluck('label')
            ->toArray();
            
        $updatedSupervisorIds = SubmitterSupervisor::where('contract_id', $contract->id)
            ->where('submitter_id', $user->id)
            ->pluck('supervisor_id')
            ->toArray();

        return response()->json([
            'message' => 'Member updated successfully',
            'member_id' => $user->id,
            'contract_id' => $contract->id,
            'updated_data' => [
                'supervisor_ids' => $updatedSupervisorIds,
                'labels' => $updatedLabels,
                'pivot_updates' => $pivotData,
            ]
        ], 200);
    }

    /**
     * DELETE /api/contracts/{contractId}/members/{userId}
     * Remove a user from a contract
     */
    public function removeMember(Request $request, $contractId, $userId)
    {
        $contract = Contract::findOrFail($contractId);
        $user = User::findOrFail($userId);
        
        // Check permissions
        $requestingUser = $request->user();
        if (!$contract->members()->where('user_id', $requestingUser->id)->wherePivot('role', 'owner')->exists()) {
            return response()->json(['message' => 'Forbidden - Only contract owners can remove members'], 403);
        }

        // Only detach if they're actually attached
        if (!$contract->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Member not on contract'], 404);
        }

        // Remove from supervisor relationships
        SubmitterSupervisor::where('contract_id', $contract->id)
            ->where(function($query) use ($user) {
                $query->where('submitter_id', $user->id)
                      ->orWhere('supervisor_id', $user->id);
            })
            ->delete();

        // Remove from label relationships
        SubmitterLabel::where('contract_id', $contract->id)
            ->where('submitter_id', $user->id)
            ->delete();

        // Remove from contract
        $contract->members()->detach($user->id);

        return response()->json(['message' => 'Member removed successfully'], 200);
    }

    /**
     * GET /api/contracts/{id}/supervisors
     * Get all supervisors for a contract
     */
    public function getSupervisors($id)
    {
        $contract = Contract::findOrFail($id);
        
        // Get all supervisors for this contract
        $supervisors = $contract->members()
            ->wherePivot('role', 'supervisor')
            ->get(['users.id', 'users.name', 'users.email']);
        
        return response()->json($supervisors);
    }
}