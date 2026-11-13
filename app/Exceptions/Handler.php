<?php

namespace App\Exceptions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation', 'token'];

    protected $dontReport = [UniqueConstraintViolationException::class];

    public function register(): void
    {
        $this->renderable(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }
            $message = 'Phiên đăng nhập đã hết hạn hoặc biểu mẫu không còn hợp lệ. Vui lòng nhập lại mật khẩu để đăng nhập.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }
            if ($request->routeIs('login.submit')) {
                // Reject the stale POST; show a fresh form without retaining credentials.
                $email = $request->input('email');

                return redirect()->route('login', [], 303)->with('info', $message)
                    ->withInput(is_string($email) ? ['email' => $email] : []);
            }

            return null;
        });
        $this->renderable(function (UniqueConstraintViolationException $exception, $request) {
            $message = 'Dữ liệu hoặc khóa yêu cầu đã tồn tại. Tải lại để kiểm tra kết quả trước khi thử lại.';

            return $request->expectsJson() ? response()->json(['message' => $message], 409) : response()->view('errors.conflict', compact('message'), 409);
        });
        $this->reportable(function (Throwable $exception) {
            //
        });
    }
}
