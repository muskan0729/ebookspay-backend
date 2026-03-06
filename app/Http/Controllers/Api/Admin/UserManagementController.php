<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    // ================= GET ALL USERS =================
    public function index(Request $request)
    {
        try {
            return User::latest()->paginate(10);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= GET SINGLE USER =================
    public function show($id)
    {
        try {
            return User::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= UPDATE USER =================
    public function update(Request $request, $id)
    {
        
        
        try {
           
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'password' => 'nullable|min:6',
                'role' => 'sometimes|in:user,admin'
            ]);

            $user = User::findOrFail($id);
            
            $data = $request->only(['name', 'email', 'role']);
            
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);
           $user->save();

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= GET USER ORDERS =================
    public function orders($id)
    {
        try {
            $user = User::findOrFail($id);

            return $user->orders()
                ->with(['cart.items.ebook'])
                ->latest()
                ->paginate(10);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    // ================= DELETE USER =================
    public function destroy($id)
    {
    try {
        $user = User::findOrFail($id);

        // Optional: prevent deleting self (admin safety)
        if (auth()->id() === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'User not found'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete user',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}

}