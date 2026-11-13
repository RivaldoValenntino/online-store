<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpBasicAuthMiddleware
{
    /**
     * Handle an incoming request for HTTP Basic Authentication on admin routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = filter_var(config('services.admin_basic_auth.enabled', false), FILTER_VALIDATE_BOOLEAN);

        // Jika dinonaktifkan melalui environment/config, lewati tanpa autentikasi tambahan
        if (!$enabled) {
            return $next($request);
        }

        // Keamanan: Wajib menggunakan HTTPS pada environment production
        if (app()->isProduction() && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri());
        }

        $expectedUser = (string) config('services.admin_basic_auth.username', '');
        $expectedPassword = (string) config('services.admin_basic_auth.password', '');

        // Keamanan: jika fitur aktif namun kredensial belum dikonfigurasi, tolak akses
        if ($expectedUser === '' || $expectedPassword === '') {
            return $this->unauthorizedResponse();
        }

        $expectedHash = hash('sha256', $expectedUser . ':' . $expectedPassword . ':' . config('app.key'));

        // 1. Jika sesi yang sama sudah pernah berhasil melewati verifikasi basic auth, izinkan langsung
        // Ini mencegah glitch pada request AJAX/Livewire internal di mana browser tidak melampirkan header Authorization
        if ($request->hasSession() && $request->session()->get('admin_basic_auth_hash') === $expectedHash) {
            return $next($request);
        }

        [$inputUser, $inputPassword] = $this->extractCredentials($request);

        // Jika kredensial tidak disediakan
        if ($inputUser === null || $inputPassword === null) {
            return $this->unauthorizedResponse();
        }

        // Perbandingan timing-safe untuk mencegah timing attacks
        $isUserValid = hash_equals($expectedUser, $inputUser);
        $isPasswordValid = hash_equals($expectedPassword, $inputPassword);

        if (!$isUserValid || ! $isPasswordValid) {
            return $this->unauthorizedResponse();
        }

        // Simpan hash ke session agar interaksi panel berikutnya (Livewire, AJAX, navigasi) tetap sinkron
        if ($request->hasSession()) {
            $request->session()->put('admin_basic_auth_hash', $expectedHash);
        }

        return $next($request);
    }

    /**
     * Ekstraksi kredensial HTTP Basic Auth dari request secara aman dan kompatibel
     * dengan berbagai konfigurasi web server (Apache, Nginx, PHP-FPM, dll).
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function extractCredentials(Request $request): array
    {
        $user = $request->getUser();
        $password = $request->getPassword();

        if ($user !== null || $password !== null) {
            return [$user, $password];
        }

        // Fallback untuk web server/proxy yang tidak meneruskan PHP_AUTH_USER / PHP_AUTH_PW
        $authHeader = $request->header('Authorization')
            ?? $request->server('HTTP_AUTHORIZATION')
            ?? $request->server('REDIRECT_HTTP_AUTHORIZATION');

        if ($authHeader && str_starts_with(strtolower($authHeader), 'basic ')) {
            $decoded = base64_decode(substr($authHeader, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$u, $p] = explode(':', $decoded, 2);
                return [$u, $p];
            }
        }

        return [null, null];
    }

    /**
     * Kembalikan respons HTTP 401 Unauthorized dengan header WWW-Authenticate.
     */
    protected function unauthorizedResponse(): Response
    {
        $realm = (string) config('services.admin_basic_auth.realm', 'Podo Raos Admin Area');
        $headers = [
            'WWW-Authenticate' => sprintf('Basic realm="%s", charset="UTF-8"', addslashes($realm)),
            'Content-Type' => 'text/plain',
        ];

        // Jika request berasal dari Livewire update, gunakan abort agar menghentikan siklus Livewire
        if (request()->hasHeader('X-Livewire')) {
            abort(401, 'Unauthorized.', $headers);
        }

        return response('Unauthorized.', 401, $headers);
    }
}
