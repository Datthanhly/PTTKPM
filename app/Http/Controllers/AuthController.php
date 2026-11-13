<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\LocalAccountSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function separateSession(Request $request)
    {
        abort_unless(LocalAccountSession::available($request), 404);

        return redirect()->away(LocalAccountSession::loginUrl($request), 303)
            ->header('Cache-Control', 'no-store, private');
    }

    public function showLogin()
    {
        return response()->view('auth.login')->header('Cache-Control', 'no-store, private, max-age=0');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:Nam,Nữ,Khác'],
            'address' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'role_id' => Role::where('code', 'patient')->value('id'),
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
            ]);
            Patient::create([
                'user_id' => $user->id, 'patient_code' => 'BN'.strtoupper(bin2hex(random_bytes(7))),
                'full_name' => $data['name'], 'birth_date' => $data['birth_date'],
                'gender' => $data['gender'], 'phone' => $data['phone'], 'email' => $data['email'],
                'address' => $data['address'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Đăng ký thành công.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (Auth::attempt([...$credentials, 'is_active' => true, 'status' => 'ACTIVE'], $request->boolean('remember'))) {
            if (Hash::needsRehash(auth()->user()->password)) {
                auth()->user()->forceFill(['password' => Hash::make($credentials['password'])])->save();
            }
            $request->session()->regenerate();
            AuditLog::record('auth.login', auth()->user());

            return redirect()->intended(route('dashboard'))->with('success', 'Đăng nhập thành công.');
        }

        AuditLog::create(['action' => 'auth.login_failed', 'ip_address' => $request->ip(), 'request_id' => $request->attributes->get('request_id')]);

        return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', auth()->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function passwordForm()
    {
        return view('auth.password');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'min:8', 'different:current_password']]);
        $request->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        Auth::logoutOtherDevices($data['password']);
        AuditLog::record('auth.password_changed', $request->user());
        $request->session()->regenerate();

        return back()->with('success', 'Đã đổi mật khẩu.');
    }

    public function forgotForm(Request $request)
    {
        $retryAfter = max(0, (int) $request->session()->get('password_reset_retry_at', 0) - now()->timestamp);

        return response()->view('auth.forgot', compact('retryAfter'))->header('Cache-Control', 'no-store, private');
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink($data);
        // Show the same cooldown for existing and unknown emails.
        $cooldown = (int) config('auth.passwords.users.throttle', 60);
        $request->session()->put('password_reset_retry_at', now()->timestamp + $cooldown);

        $message = "Nếu email đã được đăng ký, hướng dẫn đặt lại mật khẩu sẽ được gửi. Vui lòng kiểm tra hộp thư và thư rác; chờ {$cooldown} giây trước khi gửi lại.";
        if (app()->environment('local') && config('mail.default') === 'log') {
            $message .= ' Máy đang dùng email demo: liên kết được ghi trong storage/logs/laravel.log, chưa gửi ra hộp thư thật.';
        }

        return redirect()->route('password.request')->with('success', $message)->onlyInput('email');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', 'min:8']]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditLog::record('auth.password_reset', $user);
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Liên kết không hợp lệ hoặc đã hết hạn.']);
        }

        return redirect()->route('login')->with('success', 'Đã đặt lại mật khẩu.');
    }
}
