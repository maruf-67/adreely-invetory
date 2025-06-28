<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function profileUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $request->user_id,
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::findOrFail($request->user_id);

        $user->fill($request->only([
            'name', 'phone', 'email', 'address',
        ]));

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($user->image && file_exists(public_path($user->image))) {
                unlink(public_path($user->image));
            }
            
            $image = $request->file('image');
            $imageName = 'user_' . $user->id . '_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/users'), $imageName);
            $user->image = 'uploads/users/' . $imageName;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    // Get user profile by ID
    public function getUser($id)
    {
        $user = User::findOrFail($id);
        return response()->json([
            'status' => true,
            'user' => $user,
        ]);
    }

    // Create a new user
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email',
            'user_type' => 'required|in:admin,staff,supplier,retailer,dealer,wholesaler,guest',
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'previous_due' => 'nullable|numeric',
            'previous_credit' => 'nullable|numeric',
            'current_balance' => 'nullable|numeric',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'name', 'phone', 'email', 'user_type', 'address', 'previous_due', 'previous_credit', 'current_balance'
        ]);
        $data['password'] = Hash::make($request->password);

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = 'user_' . time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/users'), $imageName);
            $data['image'] = 'uploads/users/' . $imageName;
        }

        $user = User::create($data);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'user' => $user,
        ]);
    }

    // Update an existing user
    public function updateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $request->user_id,
            'user_type' => 'required|in:admin,staff,supplier,retailer,dealer,wholesaler,guest',
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'previous_due' => 'nullable|numeric',
            'previous_credit' => 'nullable|numeric',
            'current_balance' => 'nullable|numeric',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $user->fill($request->only([
            'name', 'phone', 'email', 'user_type', 'address', 'previous_due', 'previous_credit', 'current_balance'
        ]));

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($user->image && file_exists(public_path($user->image))) {
                unlink(public_path($user->image));
            }
            
            $image = $request->file('image');
            $imageName = 'user_' . $user->id . '_' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads/users'), $imageName);
            $user->image = 'uploads/users/' . $imageName;
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    // Delete a user
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Delete user image if exists
        if ($user->image && file_exists(public_path($user->image))) {
            unlink(public_path($user->image));
        }
        
        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully',
        ]);
    }
}
