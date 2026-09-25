<?php

declare(strict_types=1);

final class Auth
{
    private JsonStore $store;

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
        $this->ensureDefaultAdmin();
    }

    public function ensureDefaultAdmin(): void
    {
        $operators = $this->store->read('operators', []);
        if (!empty($operators)) {
            return;
        }

        $defaults = config('default_admin');
        $operators[] = [
            'id' => uuid(),
            'username' => $defaults['username'],
            'password_hash' => password_hash($defaults['password'], PASSWORD_DEFAULT),
            'role' => 'admin',
            'domain_access' => 'all',
            'allowed_zone_ids' => [],
            'active' => true,
            'created_at' => now_iso(),
        ];
        $this->store->write('operators', $operators);
    }

    public function attempt(string $username, string $password): bool
    {
        $this->assertNotLocked();

        $operators = $this->store->read('operators', []);
        foreach ($operators as $operator) {
            if (
                strcasecmp((string) ($operator['username'] ?? ''), $username) === 0
                && !empty($operator['active'])
                && password_verify($password, (string) ($operator['password_hash'] ?? ''))
            ) {
                $_SESSION['operator_id'] = $operator['id'];
                unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                return true;
            }
        }

        $this->registerFailedLogin();
        return false;
    }

    public function logout(): void
    {
        unset($_SESSION['operator_id']);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            redirect('r=login');
        }
    }

    public function user(): ?array
    {
        $id = $_SESSION['operator_id'] ?? null;
        if (!$id) {
            return null;
        }

        $operators = $this->store->read('operators', []);
        foreach ($operators as $operator) {
            if (($operator['id'] ?? null) === $id && !empty($operator['active'])) {
                return $operator;
            }
        }

        unset($_SESSION['operator_id']);
        return null;
    }

    public function id(): ?string
    {
        $user = $this->user();
        return $user['id'] ?? null;
    }

    private function assertNotLocked(): void
    {
        $until = (int) ($_SESSION['login_locked_until'] ?? 0);
        if ($until > time()) {
            $wait = $until - time();
            throw new RuntimeException('Too many failed login attempts. Try again in ' . $wait . ' seconds.');
        }
    }

    private function registerFailedLogin(): void
    {
        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;
        $max = (int) config('login_max_attempts', 8);
        if ($attempts >= $max) {
            $_SESSION['login_locked_until'] = time() + (int) config('login_lock_seconds', 300);
            $_SESSION['login_attempts'] = 0;
        }
    }
}
