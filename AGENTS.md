Project stack

This is a standalone PHP library
Demos live in demos/
Profiling in .profile/
There's extensive documentation available at docs/
A CHANGELOG.md is to be updated when changes are made

Follow these Coding Constraints:

Standards & Static Analysis
Enforce: declare(strict_types=1);
Compliance: Strict adherence to project ruleset.xml (PHPMD), phpstan.neon (PHPStan) and ECS (EasyCodingStandards).
Sorting: Alphabetize use statements and associative array keys. Group use statements by namespace using Foo\{Bar, Baz} syntax.
Any public method should always have a return type.
Make necessary (minimal) changes to interfaces, if needed.
Type Safety & Methods
Typing: Mandatory scalar/object type hints for all parameters and return types (including getters/setters/public methods).
Setters: Implement Fluent Interface (return $this).
Compatibility: PHP 8.4 strict. Do not use PHP 8.5+ only features.
Dependency Injection
Constructor: Use Constructor Property Promotion with private readonly modifiers. No manual property declarations or assignments.
Attributes: Replace annotations with PHP Attributes.
Refactoring __invoke / Methods:
i prefer constructor dependencies over method arguments.
Threshold: If config parameters > 3, encapsulate into an injected ConfigResolver instead of individual arguments.
Comments
Dont use // comments, use end of line /** */ comments
Dont add docblocks for @params - we prefer typed class properties (except for when PHPStan rules dictate otherwise)
Make comment as short and concise as possible. if not describing a special scenario, remove it altogether
Verify changes made
To verify the changes made, run php -l, phpstan analyze, ecs (easy coding standards) and phpmd on modified file(s).
If there are Pest Unit tests, run them prior to making changes, and after making changes. Tests shall not break, code coverage shall be kept or improved.
Performance
Identify N+1 Problems and solve them
Detect other possible performance issues and ask me if I want them fixed
PHP functions should be escaped (array_values() BAD, \array_values() GOOD) in order to speed up namespace resolution