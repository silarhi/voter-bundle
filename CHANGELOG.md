# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - Unreleased

### Added

- `AbstractRuleVoter`: a voter whose rules are the `#[Grants]` methods that decide them. Each security attribute votes only on the subjects its method takes, from the type of its `$subject` parameter, and abstains on the others.
- `#[Grants]` on a method (repeatable, several attributes per method) or on the voter class (granted once the context resolves, with the `subjects` it lists).
- `resolveContext()` and `#[ProvidesArgument]`: abstract voters deny before any rule, or hand the methods values by type. The methods also take the token, the `$attribute` and the `?Vote`.
- `RegisterRulesPass` compiles the rules into the container, and fails the build on a rule the voter cannot honor. In debug, editing a voter rebuilds the container.
- A PHPStan extension (`extension.neon`) keeps the private `#[Grants]` methods from being reported as unused.

[0.1.0]: https://github.com/silarhi/voter-bundle/commits/main
