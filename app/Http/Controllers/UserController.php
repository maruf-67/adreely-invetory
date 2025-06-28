<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    // Get user profile
    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }
        return response()->json([
            'status' => true,
            'user' => $user,
        ]);
    }

    public function profileUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $request->user_id,
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'parties_type' => 'nullable|in:Regular,Priority',
            'password' => 'nullable|string|min:8',
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
            'name',
            'phone',
            'email',
            'address',
            'parties_type',
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
            'user_type' => 'required|in:staff,supplier,retailer,dealer,wholesaler,guest',
            'parties_type' => 'nullable|in:Regular,Priority',
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'previous_due' => 'nullable|numeric',
            'previous_credit' => 'nullable|numeric',
            'current_balance' => 'nullable|numeric',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->only([
            'name',
            'phone',
            'email',
            'user_type',
            'address',
            'previous_due',
            'previous_credit',
            'current_balance',
            'parties_type'
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
    public function updateUser(Request $request, $id=null)
    {
        // Validate the request
        if (!$id) {
            return response()->json([
                'status' => false,
                'message' => 'User ID is required',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $id,
            'user_type' => 'required|in:staff,supplier,retailer,dealer,wholesaler,guest',
            'parties_type' => 'nullable|in:Regular,Priority',
            'image' => 'nullable|file|image|max:2048',
            'address' => 'nullable|string',
            'previous_due' => 'nullable|numeric',
            'previous_credit' => 'nullable|numeric',
            'current_balance' => 'nullable|numeric',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::findOrFail($id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }
        // Fill user data from request
        $user->fill($request->only([
            'name',
            'phone',
            'email',
            'user_type',
            'address',
            'previous_due',
            'previous_credit',
            'current_balance',
            'parties_type'
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
