<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/license/silarhi/voter-bundle?style=for-the-badge&color=6f42c1&labelColor=1a1a2e">
        <img src="https://img.shields.io/github/license/silarhi/voter-bundle?style=for-the-badge&color=6f42c1" alt="License">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/php-%3E%3D8.2-777bb4?style=for-the-badge&labelColor=1a1a2e">
        <img src="https://img.shields.io/badge/php-%3E%3D8.2-777bb4?style=for-the-badge" alt="PHP Version">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/actions/workflow/status/silarhi/voter-bundle/continuous-integration.yml?style=for-the-badge&label=CI&color=20c997&labelColor=1a1a2e">
        <img src="https://img.shields.io/github/actions/workflow/status/silarhi/voter-bundle/continuous-integration.yml?style=for-the-badge&label=CI&color=20c997"
            alt="CI Status">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsilarhi%2Fvoter-bundle%2Fbadges%2Fcoverage.json&style=for-the-badge&labelColor=1a1a2e">
        <img src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsilarhi%2Fvoter-bundle%2Fbadges%2Fcoverage.json&style=for-the-badge" alt="Coverage">
    </picture>
</p>

<h1 align="center">Voter Bundle</h1>

<p align="center">
    <strong>Symfony voters whose rules are the methods that decide them.</strong><br>
    A <code>#[Grants]</code> attribute on the method, the subject from its parameter type, the rules compiled with the container.
</p>

---

## Features

- **One place per rule**: no `supports()`, no `match ($attribute)`, no list of attributes and subjects to keep in sync
- **Subjects per attribute**: the type of `$subject` tells what each attribute votes on; any other subject abstains,
  instead of reaching a method that cannot take it
- **Static analysis and refactoring aware**: PHPStan, shipmonk/dead-code-detector and Rector's dead-code set keep the
  `#[Grants]` methods, which only the voter calls
- **Context layers**: abstract voters deny before any rule, or hand the methods what they need (the logged-in user,
  the active profile…) by type
- **Compiled**: the container reads the attributes once, and fails to build on a rule it cannot honor, with a message
  that says what to change

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x (`symfony/security-core`)

## Installation

```bash
composer require silarhi/voter-bundle
```

Without Symfony Flex, enable the bundle:

```php
// config/bundles.php
return [
    // ...
    Silarhi\VoterBundle\VoterBundle::class => ['all' => true],
];
```

## Quick start

```php
use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[Grants(self::EXPORT, subjects: [Post::class, null])]
final class PostVoter extends AbstractRuleVoter
{
    public const LIST = 'post.list';
    public const SHOW = 'post.show';
    public const EDIT = 'post.edit';
    public const DELETE = 'post.delete';
    public const EXPORT = 'post.export';

    #[Grants(self::LIST)]               // no $subject: votes on a null subject only
    private function canList(): bool
    {
        return true;
    }

    #[Grants(self::SHOW)]               // votes on a Post (or a subclass) only
    private function canShow(Post $subject): bool
    {
        return $subject->isPublished();
    }

    #[Grants(self::EDIT)]
    #[Grants(self::DELETE)]             // one method, several attributes
    private function canEdit(Post $subject, TokenInterface $token): bool
    {
        return $subject->getAuthor() === $token->getUser();
    }
}
```

`$this->isGranted(PostVoter::SHOW, $post)` calls `canShow()`. `isGranted(PostVoter::SHOW)` without a post abstains: no
method is called with a subject it cannot take. `EXPORT` is a class-level grant: it has no method and is granted as soon
as the context of the voter resolves (see below).

## The parameters of a `#[Grants]` method

The method returns `bool`: `true` grants, `false` denies. Its parameters, in any order, are:

| Parameter                   | Gets                                                          |
| --------------------------- | ------------------------------------------------------------- |
| named `$subject`            | the subject; its type tells which subjects the method takes   |
| named `$attribute`          | the security attribute (`string`)                             |
| typed `TokenInterface`      | the token                                                     |
| typed `?Vote`               | the vote, to `addReason()` (always `null` before Symfony 7.3) |
| typed with a provided class | the value `resolveContext()` returns for it                   |

The subject is matched by name, not by type, so a voter on `User` subjects can still ask for the logged-in `User`.

| `$subject` type              | Subjects                         |
| ---------------------------- | -------------------------------- |
| none (no `$subject`)         | `null`                           |
| `Post`                       | a `Post`, subclasses and proxies |
| `?Post`                      | a `Post` or `null`               |
| `Post\|Comment`              | either                           |
| `object` / `mixed` / untyped | any object / anything            |

## Context layers

`resolveContext()` runs before any rule: it returns `null` to deny, or the values the methods ask for, keyed by the
classes declared with `#[ProvidesArgument]`. Abstract voters stack them:

```php
use Silarhi\VoterBundle\Attribute\ProvidesArgument;

#[ProvidesArgument(User::class)]
abstract class AbstractLoggedInVoter extends AbstractRuleVoter
{
    protected function resolveContext(TokenInterface $token): ?array
    {
        $user = $token->getUser();
        $context = parent::resolveContext($token);

        return $user instanceof User && null !== $context ? [User::class => $user] + $context : null;
    }
}

#[ProvidesArgument(Organization::class)]
abstract class AbstractLandlordVoter extends AbstractLoggedInVoter
{
    protected function resolveContext(TokenInterface $token): ?array
    {
        $context = parent::resolveContext($token);
        if (null === $context || !$this->appContext->hasLandlord()) {
            return null;                    // no active landlord profile: deny every attribute
        }

        return [Organization::class => $this->appContext->getLandlord()] + $context;
    }
}

final class LeaseVoter extends AbstractLandlordVoter
{
    #[Grants(self::SHOW)]
    private function canShow(Lease $subject, User $user, Organization $landlord): bool
    {
        return $subject->getLandlord() === $landlord && $this->acl->canRead($user, $subject);
    }
}
```

A method asking for a class no layer declares fails the container build. A parameter typed with an interface or a parent
class gets the provided class that implements it. Rules declared in an abstract voter are inherited when their method
is `protected`.

## Errors at build time

The container refuses, with a message naming the method and what to change: a method that is static or does not return
`bool`; a parameter it cannot fill (a scalar other than `$attribute`, a token implementation instead of
`TokenInterface`, a non-nullable `Vote`, a class no layer provides, an ambiguous one); a `$subject` typed with an
intersection, `iterable` or an unknown class; an attribute granted twice; a class-level grant without `subjects`; a
voter that grants nothing.

## Static analysis and refactoring

- **PHPStan**: with [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the bundle's
  extension keeps the private `#[Grants]` methods from being reported as unused. Otherwise, include
  `vendor/silarhi/voter-bundle/extension.neon`.
- **[shipmonk/dead-code-detector](https://github.com/shipmonk-rnd/dead-code-detector)**: it reports the `#[Grants]`
  methods as dead too, private and protected ones alike. Include the bundle's usage provider (not automatic: it needs
  the detector installed):

    ```neon
    # phpstan.neon
    includes:
        - vendor/silarhi/voter-bundle/shipmonk.neon
    ```

- **Rector**: the dead-code set cannot see that the voter calls its `#[Grants]` methods. It would delete them, and drop
  a `$subject` parameter the method does not read, which changes its subjects. Add the bundle's set next to it:

    ```php
    use Silarhi\VoterBundle\Rector\Set\VoterSetList;

    return RectorConfig::configure()
        ->withPreparedSets(deadCode: true)
        ->withSets([VoterSetList::DEAD_CODE]);
    ```

    It swaps `RemoveUnusedPrivateMethodRector` and `RemoveUnusedPrivateMethodParameterRector` for Grants-aware versions,
    which run the original rules everywhere but on the `#[Grants]` methods: an unused private helper is still removed,
    a helper a `#[Grants]` method calls is kept. Do not register the Grants-aware rules without the set: it also skips
    the originals, which would run on their own otherwise.

## Performance

The rules are read when the container builds and dumped into it as plain arrays: a request reads no attribute, and
answers `supportsAttribute()` / `supportsType()` from those arrays. Calling a rule's private method is one
`ReflectionMethod::invokeArgs()`. In debug, editing a voter rebuilds the container, private methods included. A voter
built without the container (`new PostVoter()` in a unit test) reads its rules by reflection once per class.

## License

Released under the [MIT License](LICENSE).
