---
# https://vitepress.dev/reference/default-theme-home-page
layout: home

hero:
  name: "DOM-ORM"
  text: "XML-based Object Relational Mapping"
  tagline: Using a standardized XML tree structure to store data objects in a Doctrine-like fashion into an XML file.
  actions:
    - theme: brand
      text: Getting started
      link: /get-started
    - theme: alt
      text: Usage Examples
      link: /usage-examples

---

## Why?

The DOM-ORM project was created to provide a simple, easy-to-use way to store data objects without the need to install and configure a database, access roles and users.

## Who is it for?
DOM-ORM is not meant to replace relational databases. Finding nodes in an XML file, requires PHP to load the entire XML file into memory. 100.000 records will take up 30MB. That said, it ideal for smaller datasets such as tree structures like navigations, catgeories, virtual filesystems, taxonomies, etc.   

## Performance
### Hash Maps
Under the hood, DOM-ORM makes use of in-memory PHP hash maps. These arrays are serialized to a .php file that PHP's opcache can precompile. 
This greatly improves lookup performance because costly XPath queries are highly reduced. 
### Batch Inserts
Persisting many entities can be slow, as the XML file has to be rewritten each time `persist($entity)` is called. To address this, there is a `persistBatch($entities)` method, which writes to the XML file only once.
### No overhead
DOM-ORM can actually be faster than a regular database because it operates as an in-memory data structure for read operations. We are talking microseconds instead of milliseconds. This is achieved by eliminating network latency and disk I/O.

## Installation

```bash
composer require vardumper/dom-orm
```
