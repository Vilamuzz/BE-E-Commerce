<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Illuminate\Auth\Notifications\ResetPassword;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            })
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
                // Remove security for public auth routes
                $publicRoutes = [
                    'auth.register',
                    'auth.login',
                    'register',
                    'login',
                    'password.email',
                    'password.store'
                ];

                $routePath = $routeInfo->route->uri() ?? $routeInfo->path ?? '';

                if (
                    in_array($routeInfo->route->getName(), $publicRoutes) ||
                    str_contains($routePath, '/register') ||
                    str_contains($routePath, '/login') ||
                    str_contains($routePath, '/forgot-password') ||
                    str_contains($routePath, '/reset-password')
                ) {
                    $operation->security = [];
                }

                return $operation;
            });
    }
}
