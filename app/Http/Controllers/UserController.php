<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Property;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Services\Logger;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::all();
            return view('users.index', compact('users'));
        } catch (\Exception $e) {
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function create()
    {
        try {
            return view('users.create');
        } catch (\Exception $e) {
            return back()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    
    public function store(Request $request)
{
    $logger = \App\Services\Logger::getInstance();
    try {
    $data = $request->validate([
        'name' => 'required|string|max:30',
        'email' => 'required|string|email|unique:users,email|max:60',
        'password' => 'required|string|min:8',
        'birth_date' => 'required|date',
        'gender' => 'required|in:male,female,other',
        'location' => 'required|string|max:255',
        'phone' => 'required|numeric|digits_between:10,11',
        'role' => 'required|string|in:admin,seller,buyer'
    ]);
      
    $secretKey = env('PASSWORD_HMAC_KEY');
    $hmacHash = hash_hmac('sha256', $request->password, $secretKey);
    $bcryptHash = Hash::make($hmacHash);

    $user = User::create([
        'name' => $data['name'],
            'email'=> $data['email'],
            'password'=> $bcryptHash,
            'birth_date'=> $data['birth_date'],
            'gender'=> $data['gender'],
            'location'=> $data['location'],
            'phone'=> $data['phone'],
            'role'=> $data['role'],
    ]);
        $logger->info('New user created successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

    if ($request->ajax()) {
        return response()->json(['success' => true, 'user' => $user]);
    }

    return back()->with('success', 'User created successfully');
    } catch (\Exception $e) {
        $logger->error('User creation failed', [
            'email' => $request->email,
            'error' => $e->getMessage(),
        ]);

        if ($request->ajax()) {
            return response()->json(['error' => 'User creation failed'], 500);
        }

        return back()->with('error', 'Failed to create user. Please try again.');
    }
}


    // UserController.php
public function search(Request $request)
{
    $logger = Logger::getInstance();
    
    try {
        $logger->info('User search performed', [
            'user_id' => auth()->id(),
            'search_query' => $query,
        ]);
    } catch (\Exception $e) {
        $logger->error('Logging failed on user search', [
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
        ]);
    }

    $users = User::where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->get();

    // Return JSON if AJAX
    if ($request->ajax()) {
        return response()->json($users);
    }

    // Otherwise, just return the same view with filtered users
    return view('users-management', ['users' => $users]);
}



    

    public function update(Request $request, User $user)
{
    $logger = Logger::getInstance();
    try {
        $data = $request->validate([
            'name' => 'required|string|max:30',
                'email' => 'required|string|email|unique:users,email,' . $user->id . '|max:60',
                'password' => 'nullable|string|min:8',
                'birth_date' => 'required|date',
            'gender' => 'required|in:male,female,other',
                'location' => 'required|string|max:255',
                'phone' => 'required|numeric|digits_between:10,11',
            'role' => 'required|string|in:admin,seller,buyer',
        ]);

        if (!empty($data['password'])) {
            $secretKey = env('PASSWORD_HMAC_KEY');
            $hmacHash = hash_hmac('sha256', $data['password'], $secretKey);
            $bcryptHash = Hash::make($hmacHash);
    $data['password'] = $bcryptHash;
        } else {
            unset($data['password']);
        }

        $user->update($data);

        $logger->info('User updated successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'updated_by' => auth()->id(),
        ]);

        // Return JSON for AJAX
        return response()->json(['success' => true, 'user' => $user]);

    } catch (\Exception $e) {
        $logger->error('User update failed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'updated_by' => auth()->id(),
            'error' => $e->getMessage(),
        ]);

        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}



    public function destroy(User $user)
{
    $logger = Logger::getInstance();
    try {
        $user->delete();
        
        $logger->info('User deleted successfully', [
            'user_id' => $user->id,
            'deleted_by' => auth()->id(),
        ]);

        if(request()->ajax()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('users.index')->with('success', 'User deleted successfully');
    } catch (\Exception $e) {
        $logger->error('User deletion failed', [
            'user_id' => $user->id,
            'deleted_by' => auth()->id(),
            'error' => $e->getMessage(),
        ]);

        if(request()->ajax()) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return back()->with('error', 'Something went wrong: ' . $e->getMessage());
    }
}

    public function usersManagement()
    {
        $logger = \App\Services\Logger::getInstance();
        try {
        $users = User::all();
        $properties = Property::all();
            
            $logger->info('Accessed users management page', [
            'accessed_by' => auth()->id(),
            'users_count' => $users->count(),
            'properties_count' => $properties->count(),
        ]);
            
        return view('users.users-management', compact('users', 'properties'));
            
        } catch (\Exception $e) {
        $logger->error('Failed to load users management page', [
            'accessed_by' => auth()->id(),
            'error' => $e->getMessage(),
        ]);
            
        return back()->with('error', 'Unable to load users management page.');
    }
    }

}
