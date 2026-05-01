<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * @implements UserProviderInterface<InMemoryUser>
 */
class EnvUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private ?InMemoryUser $cached = null;

    public function __construct(
        private readonly string $username,
        #[\SensitiveParameter] private readonly string $plainPassword,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ($identifier !== $this->username || $this->username === '') {
            throw new UserNotFoundException(sprintf('Unknown user "%s".', $identifier));
        }

        if ($this->cached === null) {
            $hasher = $this->hasherFactory->getPasswordHasher(InMemoryUser::class);
            $hashed = $hasher->hash($this->plainPassword);
            $this->cached = new InMemoryUser($this->username, $hashed, ['ROLE_USER']);
        }

        return $this->cached;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === InMemoryUser::class || is_subclass_of($class, InMemoryUser::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // No-op: credentials live in env, not persisted.
    }
}
