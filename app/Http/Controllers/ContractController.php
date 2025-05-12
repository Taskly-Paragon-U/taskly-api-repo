<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contract;
use Illuminate\Support\Str;         

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

        // ── REST OF YOUR EXISTING LOGIC ──
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
