
<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // allow everyone to register
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'phone' => 'required|digits_between:10,11',
            'role' => 'required|in:admin,developer,buyer,seller',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female',
            'location' => 'required|string|max:255',
        ];
    }
}
