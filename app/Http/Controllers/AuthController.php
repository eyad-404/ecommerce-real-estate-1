<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Logger;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\CheckEmailRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected Logger $logger;

    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }

    public function showRegister()
    {
        try {
            $this->logger->info('Register page accessed', ['accessed_by' => auth()->id() ?? null]);
        } catch (\Exception $e) {
            $this->logger->error('Register page logging failed', ['error' => $e->getMessage()]);
        }

        return view('myauth.register');
    }

    public function register(RegisterRequest $request)
    {
        try {
            $data = $request->validated();
            $user = $this->createUser($data);

            Auth::login($user);
            $this->logger->info('User registered', ['user_id' => $user->id, 'email' => $user->email]);

            return redirect()->route('home')->with('success', 'Account created successfully');
        } catch (\Exception $e) {
            $this->logger->error('User registration failed', ['error' => $e->getMessage(), 'email' => $request->email]);
            return back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function showLogin()
    {
        return view('myauth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        try {
            $user = User::where('email', $credentials['email'])->first();

            if (!$user || !Hash::check($this->hashPassword($credentials['password']), $user->password)) {
                $this->logger->warning('Login failed', ['email' => $credentials['email']]);
                return back()->withErrors(['email' => 'Invalid credentials']);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $this->logger->info('User logged in', ['user_id' => $user->id]);

            return redirect()->route('home')->with('success', 'Login successful');
        } catch (\Exception $e) {
            $this->logger->error('Login exception', ['error' => $e->getMessage(), 'email' => $credentials['email']]);
            return back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function logout(Request $request)
    {
        $userId = auth()->id();

        try {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->logger->info('User logged out', ['user_id' => $userId]);
        } catch (\Exception $e) {
            $this->logger->error('Logout failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        return redirect()->route('login');
    }

   public function emailExists(CheckEmailRequest $request)
{
    try {
        $email = trim($request->email); // remove spaces
        \Log::info('Checking email existence:', ['email' => $email]);

        $exists = User::where('email', $email)->exists();

        $this->logger->info('Checked email existence', [
            'email' => $email,
            'exists' => $exists,
            'checked_by' => auth()->id() ?? null,
        ]);

        return response()->json(['exists' => $exists]);
    } catch (\Exception $e) {
        $this->logger->error('Email check failed', ['email' => $request->email, 'error' => $e->getMessage()]);
        return response()->json(['error' => 'Unable to check email'], 500);
    }
}


    // ----------------- private helpers -----------------
    private function hashPassword(string $password): string
    {
        $secretKey = env('PASSWORD_HMAC_KEY');
        $hmacHash = hash_hmac('sha256', $password, $secretKey);
        return $hmacHash;
    }

    private function createUser(array $data): User
    {
        $data['password'] = Hash::make($this->hashPassword($data['password']));
        return User::create($data);
    }
}
