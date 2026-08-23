<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Stateless Bearer-token authenticator for the `/api` firewall (issue #250).
 *
 * A single static token, `APP_API_TOKEN`, guards the JSON API. When the token
 * is empty the API is considered disabled and every request is rejected
 * (fail-closed) — never left open. On success the request runs as the
 * configured single user (`ROLE_USER`), loaded through the shared
 * {@see EnvUserProvider} attached to the firewall.
 */
class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $apiToken,
        private readonly string $username,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // The `api` firewall pattern already scopes this to /api, and the
        // token is mandatory — handle every request so a missing/blank header
        // also yields our JSON 401 (rather than Symfony's default entry point).
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        if ($this->apiToken === '') {
            throw new CustomUserMessageAuthenticationException('API disabled: no token configured.');
        }

        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }
        $provided = substr($header, 7);
        if ($provided === '' || !hash_equals($this->apiToken, $provided)) {
            throw new CustomUserMessageAuthenticationException('Invalid API token.');
        }

        // The firewall's provider (EnvUserProvider) loads the single user.
        return new SelfValidatingPassport(new UserBadge($this->username));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // let the request continue to the controller
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
