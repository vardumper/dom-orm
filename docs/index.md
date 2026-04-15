---
# https://vitepress.dev/reference/default-theme-home-page
layout: home

hero:
  name: "DOM-ORM"
  text: "Document Object Model Object Relational Mapping"
  tagline: Using a standardized XML tree structure to store data objects in a Doctrine-like fashion into an XML flatfile.
  actions:
    - theme: brand
      text: Quickstart
      link: /quickstart
    - theme: alt
      text: Usage Examples
      link: /usage-examples

features:
  - title: One-to-One Relationships
    details: Lorem ipsum dolor sit amet, consectetur adipiscing elit
  - title: One-to-many Relationships
    details: Lorem ipsum dolor sit amet, consectetur adipiscing elit
  - title: Many-to-one Relationships
    details: Lorem ipsum dolor sit amet, consectetur adipiscing elit
  - title: Many-to-Many Relationships
    details: Lorem ipsum dolor sit amet, consectetur adipiscing elit

---

## Why?

The DOM-ORM project was created to provide a simple, easy-to-use, and lightweight way to store data objects without the need to setup and configure a database, users. That said, the project is not meant to replace databases, but rather to provide an alternative with a focus on tree structures like navigations, categories, tag trees, translations, etc. things that dont necessarily need to be stored in a database.

## Querying Data

XPath is the query language used to query the XML tree structure. So unlike writing SQL queries as you would when using a database, you write XPath queries to query the XML tree structure. But just like Doctrine ORM, that returns an entity object, DOM-ORM does the same and instantiates the object from the node(s) found in the XML file.

## Caveats

In order to have XPath make a query against your data file, PHP needs to read the entire file into memory. This is not a problem for small to medium-sized files. This is something to keep in mind when working with large files.

## Relationships

A standard XML tree structure already allows to represent relationships between nodes by where they are in the tree. For example, a category tree where a category can have subcategories. This is a one-to-many relationship. The parent category has many subcategories. The subcategories have one parent category. This is represented by the parent node having child nodes.
Or similarly, think of an author, that wrote n articles. The author node has n article nodes as children. The article nodes have one author node as parent. This is a many-to-one relationship.


## Installation

```bash
composer require vardumper/dom-orm
```
