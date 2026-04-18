# DOM ORM

DOM ORM is a lightweight, zero-setup, XML-based persistence layer for small datasets in PHP projects. It stores entities in a single XML document, so you can start without a database server.

## Features

- A very lightweight approach to persisting data into a single XML file.
- Supports exporting to headless-friendly formats such as JSON, YAML, XML
- Supports Versioning in Git or Mercurial out of the box.
- Handles concurrency with flock() when used with local file strage 
- Supports local and remote storage via Flysystem (S3, Azure, Google Cloud, (S)FTP, etc.).
- Supports one-to-one, one-to-many, many-to-one, and many-to-many patterns.
- Supports AES-256-GCM field-level encryption via `#[Sensitive]` with searchable HMAC hashes.
- Supports schema evolution (rename/remove fragments) via `#[FragmentMap]` and CLI commands.
- Fully tested (Unit, Integration)

## Installation

```bash
composer require vardumper/dom-orm
```

## Documenation

Extensive Documentation has been made [available here](https://vardumper.github.io/dom-orm/).

