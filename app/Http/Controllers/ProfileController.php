<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\Logger;

class ProfileController extends Controller
{
    /**
     * Show profile page.
     */
    public function show()
    {
        $logger = Logger::getInstance();

    try {
        $logger->info('Profile page accessed', [
            'user_id' => Auth::id(),
            'email' => Auth::user()->email ?? null,
        ]);
    } catch (\Exception $e) {
        $logger->error('Profile page logging failed', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id(),
        ]);
    }
        return view('myauth.profile', ['user' => Auth::user()]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request)
    {
        $logger = Logger::getInstance();
    $user = Auth::user();

    try {
        // Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required','email','max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'location' => 'nullable|string|max:255',
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:6|confirmed',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:3072',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Update user fields
        $user->fill($request->only(['name','email','phone','birth_date','gender','location']));

        // Password change logic
        if ($request->filled('password')) {
            $secretKey = env('PASSWORD_HMAC_KEY');
            $hashedCurrentInput = hash_hmac('sha256', $request->current_password ?? '', $secretKey);

            if (!$request->filled('current_password') || !Hash::check($hashedCurrentInput, $user->password)) {
                $error = ['current_password' => ['Current password is incorrect.']];
                return $request->ajax()
                    ? response()->json(['errors' => $error], 422)
                    : back()->withErrors($error);
            }

            $newHash = hash_hmac('sha256', $request->password, $secretKey);
            $user->password = Hash::make($newHash);

            // Send email and fire event
            $token = Password::createToken($user);
            $resetUrl = route('password.reset', ['token'=>$token,'email'=>$user->email]);
            Mail::send('emails.password_changed', ['user'=>$user,'resetUrl'=>$resetUrl], function($message) use ($user) {
                $message->to($user->email,$user->name)->subject('Your password has been changed');
            });
            event(new PasswordReset($user));
        }

        // Profile image upload
        $profile = $user->profile ?? new UserProfile(['user_id'=>$user->id]);
        if ($request->hasFile('profile_image')) {
            $destinationPath = public_path('images/profile');
            if (!File::exists($destinationPath)) File::makeDirectory($destinationPath,0755,true);

            if ($profile->profile_image && File::exists($destinationPath.'/'.$profile->profile_image)) {
                File::delete($destinationPath.'/'.$profile->profile_image);
            }

            $filename = uniqid('profile_',true).'.'.$request->file('profile_image')->extension();
            $request->file('profile_image')->move($destinationPath,$filename);
            $profile->profile_image = $filename;
        }

        $user->save();
        if (!$profile->exists) $user->profile()->save($profile);
        elseif ($profile->isDirty()) $profile->save();
        $user->load('profile');

        $logger->info('User profile updated successfully', ['user_id'=>$user->id]);

        if ($request->ajax()) {
            return response()->json([
                'success'=>true,
                'profile_image'=>$user->profile_image_url,
                'has_profile_image'=> (bool) optional($user->profile)->profile_image,
                'message'=>'Profile updated successfully!',
            ]);
        }

        return back()->with('success','Profile updated successfully!');

    } catch (\Exception $e) {
        $logger->error('Profile update failed', ['user_id'=>$user->id, 'error'=>$e->getMessage()]);
        return $request->ajax()
            ? response()->json(['error'=>'Update failed'],500)
            : back()->with('error','Update failed');
    }
    }

    /**
     * Delete the authenticated user's profile picture.
     */
    public function deletePic()
    {
        $logger = Logger::getInstance();
    $user = Auth::user();
    $profile = $user->profile;

    try {
        if ($profile && $profile->profile_image) {
            $destinationPath = public_path('images/profile/'.$profile->profile_image);
            if (File::exists($destinationPath)) {
                File::delete($destinationPath);
            }

            $profile->profile_image = null;
            $profile->save();

            $logger->info('Profile picture deleted', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }
    } catch (\Exception $e) {
        $logger->error('Profile picture deletion failed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'error' => $e->getMessage(),
        ]);
    }

    $user->load('profile');

    return response()->json([
        'success' => true,
        'profile_image' => $user->profile_image_url,
        'has_profile_image' => false,
        'message' => 'Profile picture deleted!',
    ]);
    }

    /**
     * Validate the current password via AJAX.
     */
    public function checkPassword(Request $request)
    {
        $logger = Logger::getInstance();
    $user = Auth::user();
    $currentPassword = $request->input('current_password');
    $secretKey = env('PASSWORD_HMAC_KEY');

    if (!$currentPassword) {
        $logger->warning('Password check failed - empty input', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json([
            'valid' => false,
            'message' => 'Enter your current password'
        ]);
    }

    $hashedInput = $secretKey ? hash_hmac('sha256', $currentPassword, $secretKey) : $currentPassword;

    if (Hash::check($hashedInput, $user->password)) {
        $logger->info('Password check successful', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return response()->json(['valid' => true, 'message' => 'Password is correct']);
    }

    $logger->warning('Password check failed - incorrect password', [
        'user_id' => $user->id,
        'email' => $user->email,
    ]);

    return response()->json(['valid' => false, 'message' => 'Password is incorrect']);
    }
}
