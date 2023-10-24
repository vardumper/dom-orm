
# DOM ORM

This is a storage adapter for small web projects. Like any other ORM, it stores enitites (objects) into a flexible XML file. This is for developers who want to start a small project without having to spin up a database.


## Features

- Stores Objects into an XML file.
- Converts XML back into its Objects.
- Unlimited nesting (One to Many) and a hirarchical tree
- Search and filter Entities with Repositories.
- Choose your preferred Flysystem Adapter (S3,Azure,Google Cloud,SFTP,Local and more) 
- A headless API to manipulate the DOM
- Objects are normalized to PHP Arrays, which allows you to use other formats such as YAML or JSON with no extra effort.


## Documentation

Read the [Documentation](https://linktodocumentation)


## Basic Example

A basic entity is declared with these Attributes 
```
#[Item(entityType: 'car')]
final class Car extends AbstractEntity implements JsonSerializable {
    #[Fragment]
    public string $make;
    #[Fragment]
    public string $model;
    public function __construct(string $make, string $model)
    {
        parent::__construct();
        $this->make = $make;
        $this->model = $model;
    }
}
```
It's then instntiated like this:
```
$car = new Car('Opel', 'Tigra');
$repository->persist($car);
```
The resulting XML looks like this
```
<item type="car" id="0b58fe63d6da4d7e8c6a12e39adf0bb4">
    <fragment type="make">Opel</fragment>
    <fragment type="model">Tigra</fragment>
    <fragment type="createdAt">2023-11-01 22:00:00</fragment>
</item>
```
