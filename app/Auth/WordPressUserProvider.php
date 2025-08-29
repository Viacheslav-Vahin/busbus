<?php
// app/Auth/WordPressUserProvider.php
namespace App\Auth;

use App\Support\WordPressHasher;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class WordPressUserProvider extends EloquentUserProvider
{
    protected WordPressHasher $wp;

    public function __construct(HasherContract $hasher, $model)
    {
        parent::__construct($hasher, $model);
        $this->wp = new WordPressHasher();
    }

    public function validateCredentials(UserContract $user, array $credentials)
    {
        $plain = $credentials['password'] ?? '';

        // 1) спершу пробуємо звичайний Laravel пароль
        if (!empty($user->password) && $this->hasher->check($plain, $user->password)) {
            return true;
        }

        // 2) якщо є wp_password — перевіряємо WP-алгоритмом
        if (!empty($user->wp_password) && $this->wp->check($plain, $user->wp_password)) {
            // auto-migrate: зберігаємо bcrypt і очищаємо wp_password
            $user->password = $this->hasher->make($plain);
            $user->wp_password = null;
            $user->save();
            return true;
        }

        return false;
    }
}
