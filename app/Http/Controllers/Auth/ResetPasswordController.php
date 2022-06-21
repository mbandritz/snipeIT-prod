<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;


class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/';

    protected $username = 'username';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function rules()
    {
        return [
            'token' => 'required',
            'username' => 'required',
            'password' => 'confirmed|'.Setting::passwordComplexityRulesSaving('store'),
        ];
    }

    protected function credentials(Request $request)
    {
        return $request->only(
            'username', 'password', 'password_confirmation', 'token'
        );
    }

    public function showResetForm(Request $request, $token = null)
    {
        return view('auth.passwords.reset')->with(
            [
                'token' => $token,
                'username' => $request->input('username'),
            ]
        );
    }

    public function reset(Request $request)
    {

        $messages = [
            'password.not_in' => trans('validation.disallow_same_pwd_as_user_fields'),
        ];

        $request->validate($this->rules(), $request->all(), $this->validationErrorMessages());

        // Check to see if the user even exists
        if ($user = User::where('username', '=', $request->input('username'))->whereNotNull('email')->first()) {
            $broker = $this->broker();

            // handle the password validation rules set by the admin settings
            if (strpos(Setting::passwordComplexityRulesSaving('store'), 'disallow_same_pwd_as_user_fields') !== false) {
                $request->validate(
                    [
                        'password' => 'required|notIn:["'.$user->email.'","'.$user->username.'","'.$user->first_name.'","'.$user->last_name.'"',
                    ], $messages);
            }

            // send the reset
            $response = $broker->reset(
                $this->credentials($request), function ($user, $password) {
                $this->resetPassword($user, $password);
            });

        }
        // This is laravel magic - we override the sendResetFailedResponse further down to send a success message even if it failed
        return $response == \Password::PASSWORD_RESET
            ? $this->sendResetResponse($request, $response)
            : $this->sendResetFailedResponse($request, $response);

    }


    protected function sendResetFailedResponse(Request $request, $response)
    {
        return redirect()->back()
            ->withInput(['username'=> $request->input('username')])
            ->with('success', trans('passwords.sent'));
    }
}
