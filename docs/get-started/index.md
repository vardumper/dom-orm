# Getting started
## Assumptions
You already know what an ORM is. You have worked with Eloquent or Doctrine.

 - [Object Relational Mapper](https://en.wikipedia.org/wiki/Object-relational_mapping) on Wikipedia
 - [What is Doctrine?](https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/getting-started.html#what-is-doctrine)
 - [What is Eloquent?](https://laravel.com/docs/13.x/eloquent)

## Entities
In an ORM, entities are PHP objects that map directly to database tables. Furthermore, entities are used to interact with the database table. For example to save or update an entity. 

## Respositories
Respositories are also used to interact with the database, mainly to find entities `findAll()`, `find()`, `findBy()`, `findOneBy()` but also to `remove()`