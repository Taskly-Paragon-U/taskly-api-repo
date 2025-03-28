<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contract;

class ContractController extends Controller
{
    //  Create new contract
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'details' => 'required|string',
        ]);

        $contract = Contract::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'details' => $validated['details'],
        ]);

        return response()->json([
            'message' => 'Contract created',
            'contract' => $contract,
        ], 201);
    }

    // Get contracts where user is creator OR invited
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
}
