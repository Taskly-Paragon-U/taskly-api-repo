<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contract;

class ContractController extends Controller
{
    /**
     * Create a new contract and attach the creator as owner.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'details' => 'required|string',
        ]);

        // Create the contract
        $contract = Contract::create([
            'user_id' => $request->user()->id,
            'name'    => $validated['name'],
            'details' => $validated['details'],
        ]);

        // Attach the creator to the pivot with role = owner
        $contract->members()->attach(
            $request->user()->id,
            ['role' => 'owner']
        );

        // Return the freshly created contract with its members
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
     * Show a single contract with its members (and their roles).
     */
    public function show($id)
    {
        $contract = Contract::with('members')->findOrFail($id);

        return response()->json([
            'contract' => $contract
        ], 200);
    }
}
