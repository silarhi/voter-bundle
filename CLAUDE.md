# CLAUDE.md

This file provides guidance for Claude Code when working on the Voter Bundle project.

## Project Overview

Voter Bundle replaces the `supports()` / `voteOnAttribute()` pair of Symfony voters with a `#[Grants]` attribute on
the method that decides each security attribute. The subject comes from the type of the `$subject` parameter, and the
container compiles the rules when it builds.

## Tech Stack

- **Language**: PHP 8.2+
- **Framework**: Symfony 6.4 / 7.x / 8.x (`symfony/security-core`, `symfony/dependency-injection`, `symfony/http-kernel`)
- **Dev**: `symfony/framework-bundle` + `symfony/security-bundle` (functional test), PHPStan (also for the extension)

## Repository Structure

```
src/
├── Attribute/           # Grants (method or class, repeatable), ProvidesArgument (class, repeatable)
├── DependencyInjection/ # RegisterRulesPass: compiles the rules of the security.voter services into setRules() calls
├── Exception/           # ExceptionInterface, InvalidRuleException (build), MissingArgumentException (runtime)
├── PHPStan/             # GrantsMethodExtension: #[Grants] methods are always used (extension.neon);
│                       #   GrantsMemberUsageProvider: the same for shipmonk/dead-code-detector (shipmonk.neon)
├── Rector/              # Grants-aware decorators of the dead-code rules; Set/VoterSetList::DEAD_CODE
├── Rule/                # Rule (plain data, dumpable), RuleLoader (reflection + validation)
├── Voter/               # AbstractRuleVoter: supports*() from the rules, resolveContext(), calls the methods
└── VoterBundle.php      # Registers RegisterRulesPass
config/rector/           # dead-code.php: the Rector set (skips the originals, registers the Grants-aware rules)
extension.neon           # The PHPStan extension, included by phpstan/extension-installer
shipmonk.neon            # The shipmonk usage provider: applications include it by hand (it needs the detector)
tests/                   # PHPUnit tests mirroring src/, Functional/ (TestKernel), Fixtures/ (models, voters),
                         #   PHPStan/Data/ (sources analysed by the PHPStan and shipmonk rule tests, excluded from PHPStan),
                         #   Rector/DeadCodeSet/ (AbstractRectorTestCase, *.php.inc fixtures)
```

## Build & Test Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse                     # level max
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/rector process --dry-run
composer validate --strict && composer normalize --dry-run
```

## CI/CD

- Uses **Laminas CI Matrix Action** (`.github/workflows/continuous-integration.yml`), `pcov` for coverage
- Additional check (`.laminas-ci.json`): `composer validate --strict && composer normalize --dry-run --diff`
- The `main` ruleset requires the `CI passed` job; coverage is published to the `badges` branch
- pnpm only runs the git hooks (husky + lint-staged); its version is pinned by `packageManager` in `package.json`

## Architecture Notes

- **Rules are plain arrays in the container.** `RuleLoader::load()` returns `array<attribute, RuleShape>`;
  `RegisterRulesPass` adds a `setRules()` call per voter, so the dumped container holds array literals. A voter built
  without the container falls back to `RuleLoader` once per class (`AbstractRuleVoter::$loadedRules`).
- **Arguments are markers or classes.** `Rule::$arguments` lists `$subject`, `$attribute`, `$token`, `$vote` or the
  provided class to read from the context; `RuleLoader` resolves a parameter typed with a parent class or an interface
  to the one provided class matching it, so the runtime lookup is an exact key.
- **The subject is matched by name** (`$subject`), never by type: a voter on `User` subjects still asks for the
  logged-in `User`. No `$subject` parameter means a null subject only.
- **Subjects per attribute.** `supportsAttribute()` and `supportsType()` answer the unions for the access decision
  manager's cache; `supports()` checks the pair, so an attribute abstains on a subject its method cannot take.
- **Private methods are called through `ReflectionMethod::invokeArgs()`**, from the class declaring them
  (`Rule::$class`): a rule of an abstract parent must be `protected` (`getMethods()` skips the private methods of
  parents).
- **Resource tracking.** `ReflectionClassResource` only hashes public and protected methods: the pass adds a
  `FileResource` for the voter and each parent below `AbstractRuleVoter`, so editing a private `#[Grants]` method
  rebuilds the debug container.
- **Rector's dead-code set deletes `#[Grants]` methods** and drops unused `$subject` parameters. Rector has no
  skip-by-attribute hook, so `AbstractGrantsAwareRector` decorates the two rules: it makes the private `#[Grants]`
  methods protected, runs the original rule (which only looks at private methods), then restores them. Their bodies stay
  in the class, so the helpers they call are still used. Rector's container registers every rule instance it builds,
  the injected originals included: `config/rector/dead-code.php` (`VoterSetList::DEAD_CODE`) skips them, which is why
  applications use the set, not the rules alone. The bundle's own `rector.php` uses the set too.
- **shipmonk/dead-code-detector has a supported hook**, a `MemberUsageProvider` tagged
  `shipmonk.deadCode.memberUsageProvider`: no decorator needed. Its class extends the detector's, so `extension.neon`
  (auto-included) cannot register it: `shipmonk.neon` does, included by hand. The bundle's own analysis shows no dead
  `#[Grants]` method even without it, because the detector sees `RuleLoader`'s `getMethods()` call: only the rule test,
  which analyses `tests/PHPStan/Data` alone, proves the provider. `phpstan.neon` includes `shipmonk.neon` and ignores the
  Rector rule constructors (built by Rector's container) and the `eraseCredentials()` of the user fixture.
- `config/reference.php` is written by the FrameworkBundle of the test kernel (Symfony 7.4+): it is gitignored and
  out of PHP CS Fixer, PHPStan and Rector, which only read `config/rector`.
- **`Vote` came with Symfony 7.3.** `voteOnAttribute()` declares `?Vote $vote = null` (required by 8.x, accepted as
  an extra optional parameter by 6.4); the loader only compares `Vote::class`, so a `?Vote` parameter loads on 6.4 and
  always gets `null` there. `AbstractRuleVoterTest` skips its reasons test without the class.
- `symfony/polyfill-php85` is a dev dependency: PHPStan's `RuleTestCase` calls `array_last()` on PHP < 8.5.

## Domain Exceptions

| Exception                  | Thrown when                                                                          |
| -------------------------- | ------------------------------------------------------------------------------------ |
| `InvalidRuleException`     | A `#[Grants]` or `#[ProvidesArgument]` cannot be honored (container build)           |
| `MissingArgumentException` | `resolveContext()` returned no value for a class declared with `#[ProvidesArgument]` |

Both implement `ExceptionInterface`.

## Coding Conventions

- `declare(strict_types=1)` everywhere, PSR-4 under `Silarhi\VoterBundle\`
- PHP-CS-Fixer (`@Symfony`, `@Symfony:risky`, header), PHPStan level max, Rector (PHP 8.2 set, deadCode, codeQuality,
  typeDeclarations)
- Keep PHP 8.2 syntax: no `new Foo()->bar()` without parentheses, no typed class constants
- `symfony/*` keep wide ranges (`^6.4 || ^7.0 || ^8.0`): never raise their floors; QA tools are bumped with `composer bump:tools`

## Documentation

Update `README.md` (features, parameters, errors), this file (structure, architecture notes, exceptions) and
`CHANGELOG.md` with every public API change. Changelog entries go in the top section titled with the next release
(`[0.2.0] - Unreleased`), never a bare `[Unreleased]`.

## Releases

Lightweight tag `vX.Y.Z` on `origin/main` once CI is green, with a GitHub release whose notes follow the `CHANGELOG.md`
entry. Packagist updates from the GitHub hook.
