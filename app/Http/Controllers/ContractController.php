<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contract;

class ContractController extends Controller
{
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

        return response()->json(['message' => 'Contract created', 'contract' => $contract], 201);
    }
}
