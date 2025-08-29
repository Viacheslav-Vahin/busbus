<?php
// app/Providers/WordPressAuthServiceProvider.php
namespace App\Providers;

use App\Auth\WordPressUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class WordPressAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::provider('wp_users', function ($app, array $config) {
            return new WordPressUserProvider($app['hash'], $config['model']);
        });
    }
}
