<?php

namespace App\Services\Auth;

use Illuminate\Routing\Route;

class StaffRouteAccess
{
    /**
     * Routes with public, customer, token or provider authentication contracts.
     * Match the registered URI and method, never a request-controlled prefix.
     * New web/API routes require staff access until explicitly reviewed here.
     *
     * @var array<string, list<string>>
     */
    public const EXCEPTIONS = [
        // Storefront, public documents and customer account.
        '/' => ['GET', 'HEAD'],
        'g' => ['GET', 'HEAD'],
        'g/{good}' => ['GET', 'HEAD'],
        'g/{good}/stock-alerts' => ['POST'],
        'g/{good}/inquiries' => ['POST'],
        'p/{product}' => ['GET', 'HEAD'],
        'товар/{good}' => ['GET', 'HEAD'],
        'категория/{category}' => ['GET', 'HEAD'],
        'подборки/{field}' => ['GET', 'HEAD'],
        'goods/published' => ['GET', 'HEAD'],
        'Seaprom' => ['GET', 'HEAD'],
        'кунжут' => ['GET', 'HEAD'],
        'location/cities' => ['GET', 'HEAD'],
        'location/city' => ['POST'],
        'web/entities/lookup-by-inn' => ['GET', 'HEAD'],
        'privacy-policy' => ['GET', 'HEAD'],
        'terms' => ['GET', 'HEAD'],
        'personal-data-consent' => ['GET', 'HEAD'],
        'robots.txt' => ['GET', 'HEAD'],
        'sitemap.xml' => ['GET', 'HEAD'],
        'indexnow-key.txt' => ['GET', 'HEAD'],
        'yandex-feed.xml' => ['GET', 'HEAD'],
        'dashboard' => ['GET', 'HEAD'],
        'dashboard/profile' => ['GET', 'HEAD', 'POST'],
        'orders' => ['POST'],

        // Fortify/Jetstream keep their existing session, password and 2FA checks.
        'login' => ['GET', 'HEAD', 'POST'],
        'Ameise/login' => ['GET', 'HEAD', 'POST'],
        'logout' => ['POST'],
        'register' => ['GET', 'HEAD', 'POST'],
        'forgot-password' => ['GET', 'HEAD', 'POST'],
        'reset-password/{token}' => ['GET', 'HEAD'],
        'reset-password' => ['POST'],
        'two-factor-challenge' => ['GET', 'HEAD', 'POST'],
        'email/verify' => ['GET', 'HEAD'],
        'email/verify/{id}/{hash}' => ['GET', 'HEAD'],
        'email/verification-notification' => ['POST'],
        'user' => ['DELETE'],
        'user/profile' => ['GET', 'HEAD'],
        'user/profile-information' => ['PUT'],
        'user/profile-photo' => ['DELETE'],
        'user/password' => ['PUT'],
        'user/confirm-password' => ['GET', 'HEAD', 'POST'],
        'user/confirmed-password-status' => ['GET', 'HEAD'],
        'user/other-browser-sessions' => ['DELETE'],
        'user/two-factor-authentication' => ['POST', 'DELETE'],
        'user/confirmed-two-factor-authentication' => ['POST'],
        'user/two-factor-qr-code' => ['GET', 'HEAD'],
        'user/two-factor-secret-key' => ['GET', 'HEAD'],
        'user/two-factor-recovery-codes' => ['GET', 'HEAD', 'POST'],
        'sanctum/csrf-cookie' => ['GET', 'HEAD'],
        'api/user' => ['GET', 'HEAD'],

        // Public links and provider ingress keep token/signature/state validation.
        'email/open/{token}' => ['GET', 'HEAD'],
        'email/click/{token}' => ['GET', 'HEAD'],
        'mailings/unsubscribe/{token}' => ['GET', 'HEAD', 'POST'],
        'avito/autoload/feeds/{feed}/{token}.xml' => ['GET', 'HEAD'],
        'avito/autoload/feeds/{feed}/{token}/revisions/{revision}/media/{media}' => ['GET', 'HEAD'],
        'banking/sber/oauth/callback' => ['GET', 'HEAD'],
        'api/marketing/yandex/oauth/callback' => ['GET', 'HEAD'],
        'api/avito/oauth/callback' => ['GET', 'HEAD'],
        'api/avito/webhook' => ['POST'],
        'api/max/webhook' => ['POST'],
        'api/webhook' => ['POST'],

        // Mobile access is independently restricted to scoped, expiring tokens.
        'api/mobile/v1/auth/login' => ['POST'],
        'api/mobile/v1/auth/me' => ['GET', 'HEAD'],
        'api/mobile/v1/auth/token' => ['DELETE'],
        'api/mobile/v1/delivery-map/config' => ['GET', 'HEAD'],
        'api/mobile/v1/delivery-map/orders' => ['GET', 'HEAD'],
        'api/mobile/v1/orders' => ['GET', 'HEAD'],
        'api/mobile/v1/orders/{order}' => ['GET', 'HEAD'],
        'api/mobile/v1/orders/{order}/delivery-date' => ['PATCH'],
        'api/mobile/v1/orders/{order}/prepare' => ['PATCH'],
        'api/mobile/v1/orders/{order}/ship' => ['POST'],
    ];

    public function requiresStaff(Route $route, string $method): bool
    {
        return ! in_array($method, self::EXCEPTIONS[$route->uri()] ?? [], true);
    }
}
