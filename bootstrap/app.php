<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('customer.login'));
        // $middleware->preventRequestForgery(except: [
        //     'stripe/*',
        //     'webhook/handler',
        //     'webhooks/midtrans',
        // ]);
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
        $middleware->preventRequestForgery(except: [
            'webhooks/midtrans',
            'webhooks/biteship',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $e) {
            if (! auth('customer')->check()) {
                return redirect()
                    ->route('customer.login')
                    ->with('flash.type', 'error')
                    ->with('flash.title', 'Link verifikasi kedaluwarsa')
                    ->with('flash.message', 'Silakan login dulu, lalu kirim ulang email verifikasi.');
            }

            return redirect()
                ->route('verification.notice')
                ->with('flash.type', 'error')
                ->with('flash.title', 'Link verifikasi kedaluwarsa')
                ->with('flash.message', 'Link verifikasi sudah tidak berlaku. Silakan kirim ulang email verifikasi.');
        });
    })->create();
