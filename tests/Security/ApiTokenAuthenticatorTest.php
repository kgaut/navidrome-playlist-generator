<?php

namespace App\Tests\Security;

use App\Security\ApiTokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticatorTest extends TestCase
{
    public function testSupportsEveryApiRequest(): void
    {
        // The firewall pattern scopes to /api; the authenticator handles every
        // request so a missing header also produces the JSON 401.
        $auth = new ApiTokenAuthenticator('secret', 'admin');

        $this->assertTrue($auth->supports($this->request('Bearer secret')));
        $this->assertTrue($auth->supports($this->request(null)));
    }

    public function testMissingHeaderIsRejected(): void
    {
        $auth = new ApiTokenAuthenticator('secret', 'admin');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $auth->authenticate($this->request(null));
    }

    public function testEmptyConfiguredTokenFailsClosed(): void
    {
        $auth = new ApiTokenAuthenticator('', 'admin');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $auth->authenticate($this->request('Bearer anything'));
    }

    public function testWrongTokenIsRejected(): void
    {
        $auth = new ApiTokenAuthenticator('secret', 'admin');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $auth->authenticate($this->request('Bearer nope'));
    }

    public function testCorrectTokenYieldsPassportForConfiguredUser(): void
    {
        $auth = new ApiTokenAuthenticator('secret', 'admin');

        $passport = $auth->authenticate($this->request('Bearer secret'));

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $badge = $passport->getBadge(UserBadge::class);
        $this->assertInstanceOf(UserBadge::class, $badge);
        $this->assertSame('admin', $badge->getUserIdentifier());
    }

    public function testFailureReturns401Json(): void
    {
        $auth = new ApiTokenAuthenticator('secret', 'admin');

        $response = $auth->onAuthenticationFailure(
            $this->request(null),
            new CustomUserMessageAuthenticationException('nope'),
        );

        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['error' => 'Unauthorized'], json_decode((string) $response->getContent(), true));
    }

    private function request(?string $authorization): Request
    {
        $req = Request::create('/api/stats/daily');
        if ($authorization !== null) {
            $req->headers->set('Authorization', $authorization);
        }

        return $req;
    }
}
