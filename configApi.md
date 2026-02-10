## Install Laravel Sanctum
composer require laravel/sanctum

php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

## Configure Sanctum Middleware
In your `bootstrap/app.php` file, add the Sanctum middleware to the API middleware group:
```
->withMiddleware(function (Middleware $middleware) {

    $middleware->api([
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ]);

})

```
## Create API Routes
In your `routes/api.php` file, define your API routes. For example:
```phpuse App\Http\Controllers\Api\AuthController;
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
```
## Config pour auth.php
In your `config/auth.php` file, ensure that the `sanctum` guard is configured correctly:
```php'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```
## Configuration pour les cors
In your `config/cors.php` file, configure the allowed origins, methods, and headers for CORS:
```php'paths' => ['api/*'], si on veut que les règles de CORS s'appliquent uniquement aux routes API
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
'allowed_headers' => ['*'],
```
