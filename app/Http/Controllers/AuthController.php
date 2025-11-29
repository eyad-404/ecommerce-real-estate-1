<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\Logger;

class AuthController extends Controller
{
    public function showRegister(){
        $logger = \App\Services\Logger::getInstance();

    try {
        $logger->info('Register page accessed', [
            'accessed_by' => auth()->id() ?? null,
        ]);
    } catch (\Exception $e) {
        $logger->error('Register page access logging failed', [
            'error' => $e->getMessage(),
        ]);
    }
        return view('myauth.register');
    }
    public function register(Request $request){
        $logger = \App\Services\Logger::getInstance();
        
    try {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email'=>'required|email|unique:users',
            'password'=>'required|min:8|confirmed',
            'phone'=>'required|digits_between:10,11',
            'role'=>'required|in:admin,developer,buyer,seller',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female',
            'location' => 'required|string|max:255',
        ]);

        $secretKey = env('PASSWORD_HMAC_KEY');
        $hmacHash = hash_hmac('sha256', $data['password'], $secretKey);
        $bcryptHash = Hash::make($hmacHash);
        

        $user = User::create([
            'name' => $data['name'],
            'email'=>$data['email'],
            'password'=>$bcryptHash,
            'phone'=>$data['phone'],
            'role'=>$data['role'],
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'location' => $data['location'],
        ]);

        $logger->info('New user registered', ['user_id' => $user->id, 'email' => $user->email]);

        Auth::login($user);
        return redirect()->route('home')->with('success',"account created successfully");
    } catch (\Exception $e) {
        $logger->error('User registration failed', ['error' => $e->getMessage(), 'email' => $request->email]);
        return back()->with('error', 'Something went wrong: ' . $e->getMessage());
    }
    }
    public function showlogin(){

        $logger = \App\Services\Logger::getInstance();

    try {
        $logger->info('Login page accessed', [
            'accessed_by' => auth()->id() ?? null,
        ]);
    } catch (\Exception $e) {
        $logger->error('Login page access logging failed', [
            'error' => $e->getMessage(),
        ]);
    }
        
        return view('myauth.login');
    }
    public function login(Request $request){

        $logger = \App\Services\Logger::getInstance();
        
      try {
        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user) {
            $secretKey = env('PASSWORD_HMAC_KEY');
            $hmacHash = hash_hmac('sha256', $request->password, $secretKey);

            if (Hash::check($hmacHash, $user->password)) {
                Auth::login($user);
                $request->session()->regenerate();

                $logger->info('User logged in successfully', ['user_id' => $user->id]);

                return redirect()->route('home')->with('success', 'Login successful');
            } else {
                $logger->warning('User login failed - wrong password', ['email' => $request->email]);
                return back()->withErrors(['email' => 'Invalid credentials']);
            }
        } else {
            $logger->warning('User login failed - email not found', ['email' => $request->email]);
            return back()->withErrors(['email' => 'Invalid credentials']);
        }
    } catch (\Exception $e) {
        $logger->error('Login exception', ['error' => $e->getMessage(), 'email' => $request->email]);
        return back()->with('error', 'Something went wrong: ' . $e->getMessage());
    }
    }

    public function logout(Request $request){
        $logger = \App\Services\Logger::getInstance();
        $userId = auth()->id();

    try {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $logger->info('User logged out', [
            'user_id' => $userId,
        ]);
    } catch (\Exception $e) {
        $logger->error('Logout failed', [
            'user_id' => $userId,
            'error' => $e->getMessage(),
        ]);
    }

    return redirect()->route('login');
    }

    public function checkEmail(Request $request)
{
     $logger = \App\Services\Logger::getInstance();
    
    $request->validate([
        'email' => 'required|email',
    ]);

    try {
        $exists = \App\Models\User::where('email', $request->email)->exists();

        $logger->info('Checked if email exists', [
            'email' => $request->email,
            'exists' => $exists,
            'checked_by' => auth()->id() ?? null,
        ]);

        return response()->json(['exists' => $exists]);
    } catch (\Exception $e) {
        $logger->error('Email check failed', [
            'email' => $request->email,
            'error' => $e->getMessage(),
            'checked_by' => auth()->id() ?? null,
        ]);

        return response()->json(['error' => 'Unable to check email'], 500);
    }
}

}
