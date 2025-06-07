<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Return a list of all users (id + email).
     */
    public function index(Request $request)
    {
        // Select only id and email from users table
        $users = User::select('id', 'email')->get();

        return response()->json([
            'users' => $users,
        ], 200);
    }
}
