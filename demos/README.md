# Demos

This folder is an isolated Composer workspace for runnable DOM ORM demos.

## Install

Run from this folder:

```bash
composer install
```

## Run

Execute the virtual filesystem demo:

```bash
composer run demo:virtual-filesystem
```

## Notes

- This workspace has its own dependency graph and lockfile.
- `vardumper/dom-orm` is loaded from the local repository via a Composer path repository.
- LeafPHP is provided by `leafs/leaf`.