<h1 align="center">DOM ORM</h1>

<p align="center" dir="auto">
    <a href="https://packagist.org/packages/vardumper/dom-orm" rel="nofollow">
        <img src="https://poser.pugx.org/vardumper/dom-orm/v/stable" alt="Latest Stable Version" />
    </a>
    <img src="https://img.shields.io/packagist/dt/vardumper/dom-orm" alt="Total Downloads" />
    <img src="https://img.shields.io/badge/license-mit-red" alt="License" />
    <img src="https://img.shields.io/badge/unit%20tests-passing-green?style=flat&amp;color=%234c1" style="max-width: 100%;">
    <img src="https://raw.githubusercontent.com/vardumper/dom-orm/refs/heads/main/coverage.svg">
    <img src="https://dtrack.erikpoehler.us/api/v1/badge/vulns/project/4e028df9-0be3-4c3d-b383-7b1468262c27?apiKey=odt_nG83W_EAcQZkk6b5KqknIVoK8nfNjSz38Ompnn" >
</p>

DOM ORM is a lightweight, zero-setup, XML-based persistence layer for small datasets in PHP projects. It stores entities in a single XML document, so you can start without a database server.

## Features

- A very lightweight approach to persisting data into a single XML file.
- Supports exporting to headless-friendly formats such as JSON, YAML, XML
- Supports Versioning in Git or Mercurial out of the box.
- Handles concurrency with flock() when used with local file strage 
- Supports local and remote storage via Flysystem (S3, Azure, Google Cloud, (S)FTP, etc.).
- Ships with a built-in in-memory Flysystem adapter for process-local XML storage.
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

## Demos
 - [Virtual Filesystem](https://dom-orm.erikpoehler.com/virtual-filesystem/)
