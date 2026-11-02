<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use App\Support\RateLimiter;
use App\Support\RateLimitExceededException;
use App\Support\Session;
use App\Support\View;
use DomainException;
use InvalidArgumentException;

final class AuthController
{
    private UserService $user_service;
    private RateLimiter $rate_limiter;

    public function __construct(
        ?UserService $user_service = null,
        ?RateLimiter $rate_limiter = null
    )
    {
        $this->user_service = $user_service ?? new UserService();
        $this->rate_limiter = $rate_limiter ?? new RateLimiter();
    }

    public function login(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : null;

        if (!is_array($body)) {
            $body = $_POST;
        }

        $username = is_string($body['username'] ?? null) ? trim($body['username']) : '';
        $password = is_string($body['password'] ?? null) ? trim($body['password']) : '';

        // Check if request is a native browser HTML form submit vs JSON/API/Test
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $is_html_form = str_contains($accept, 'text/html') && isset($_POST['username']);
        $is_json = !$is_html_form;
        $source = is_string($_SERVER['REMOTE_ADDR'] ?? null) && $_SERVER['REMOTE_ADDR'] !== ''
            ? $_SERVER['REMOTE_ADDR']
            : 'unknown';

        try {
            $this->rate_limiter->assertAllowed($username, $source);
            $user = $this->user_service->authenticate($username, $password);
            $this->rate_limiter->record($username, $source, true);

            Session::loginUser(
                user_id: $user->id,
                username: $user->username,
                role_name: $user->role?->name ?? 'user'
            );

            if ($is_json) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Autenticación exitosa',
                    'data' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'full_name' => $user->full_name,
                        'role' => $user->role?->name,
                        'csrf_token' => Session::csrfToken(),
                    ],
                ]);
                return;
            }

            // Web Form Submit -> Redirect to Dashboard
            header('Location: /');
            exit;
        } catch (RateLimitExceededException $e) {
            http_response_code(429);
            header('Retry-After: ' . $e->retry_after_seconds);

            if ($is_json) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
                return;
            }

            View::render('pages/login', [
                'page_title' => 'Iniciar Sesión',
                'error_message' => $e->getMessage(),
            ]);
        } catch (InvalidArgumentException | DomainException $e) {
            $this->rate_limiter->record($username, $source, false);

            if ($is_json) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                return;
            }

            // Render login with error message for native form submit
            http_response_code(401);
            View::render('pages/login', [
                'page_title' => 'Iniciar Sesión',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    public function logout(): void
    {
        Session::destroy();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    public function csrfToken(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'token' => Session::csrfToken(),
        ]);
    }
}
