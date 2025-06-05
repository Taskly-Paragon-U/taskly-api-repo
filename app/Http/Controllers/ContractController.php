<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invite;
use App\Models\Contract;
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

        // Load members with pivot data
        $members = $contract->members()->get()->map(function ($member) use ($contract) {
            // Get supervisor_ids_json from invites table
            $invite = Invite::where('email', $member->email)
                        ->where('contract_id', $contract->id)
                        ->where('role', $member->pivot->role)
                        ->where('label', $member->pivot->label) 
                        ->first();
            
            $supervisorIdsJson = $invite ? $invite->supervisor_ids_json : null;
            
            // Lookup supervisor name if set
            $supervisorName = null;
            if ($member->pivot->supervisor_id) {
                $supervisorUser = User::find($member->pivot->supervisor_id);
                $supervisorName = $supervisorUser?->name;
            }

            return [
                'id'               => $member->id,
                'name'             => $member->name,
                'email'            => $member->email,
                'role'             => $member->pivot->role,
                'supervisorId'     => $member->pivot->supervisor_id,  
                'supervisorName'   => $supervisorName,
                'startDate'        => $member->pivot->start_date,
                'endDate'          => $member->pivot->due_date,
                'label'            => $member->pivot->label,
                'pivot_id' => $member->pivot->id,
                'supervisor_ids_json' => $supervisorIdsJson, 
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

    public function updateMember(Request $request, Contract $contract, User $user)
    {
        // Validate any of: start_date, due_date, supervisor_id
        $data = $request->validate([
            'start_date'    => 'nullable|date',
            'due_date'      => 'nullable|date',
            'supervisor_id' => 'nullable|exists:users,id',
        ]);

        // Make sure this user is attached to the contract
        if (! $contract->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Member not on contract'], 404);
        }

        // Update the pivot row
        $contract->members()
                 ->updateExistingPivot($user->id, $data);

        return response()->json(['message' => 'Member updated'], 200);
    }

    // For remove user from a contract
    public function removeMember(Contract $contract, User $user)
    {
        // Only detach if they’re actually attached
        if (! $contract->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Member not on contract'], 404);
        }

        $contract->members()->detach($user->id);

        return response()->json(['message' => 'Member removed'], 200);
    }

        /**
     * GET /api/contracts/{id}/supervisors
     * Get all supervisors for a contract
     */
    public function getSupervisors($id)
    {
        $contract = Contract::findOrFail($id);
        
        // Get all supervisors for this contract
        $supervisors = $contract->supervisors()->get(['users.id', 'users.name', 'users.email']);
        
        return response()->json($supervisors);
    }

}
