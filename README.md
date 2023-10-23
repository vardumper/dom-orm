
# DOM ORM

This is a storage adapter for small web projects. Like any other ORM, it stores enitites (objects) into a flexible XML file. This is for developers who want to start a small project without having to spin up a database.


## Features

- Stores Objects into an XML file.
- Converts XML back into its Objects.
- Search and filter Entities with Repositories.
- Choose your preferred Flysystem Adapter (S3,Azure,Google Cloud,SFTP,Local and more) 
- A headless API to manipulate the DOM
- Objects are normalized to PHP Arrays, which allows you to use other formats such as YAML or JSON with no extra effort.


## Documentation

Read the [Documentation](https://linktodocumentation)


## Example

An instance of this Entity 
```
#[Item(entityType: 'car')]
final class Car extends AbstractEntity {
    #[Fragment]
    private string $make;
    #[Fragment]
    private string $model;
    ...
}
```
is stored as 
```
<item type="car" id="0b58fe63d6da4d7e8c6a12e39adf0bb4">
    <fragment type="make">Opel</fragment>
    <fragment type="model">Tigra</fragment>
    <fragment type="createdAt">2023-11-01 22:00:00</fragment>
</item>
