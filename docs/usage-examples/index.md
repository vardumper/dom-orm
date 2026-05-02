# Examples

## A virtual filesystem

![virtual filesystem](https://dom-orm.erikpoehler.com/virtual-filesystem/assets/images/virtual-filesystem.png)
This example is to illustrate how a filesystem tree can be persisted with DOM-ORM. See the [source code](https://github.com/vardumper/dom-orm/tree/main/demos/virtual-filesystem). 

[Virtual Filesystem Demo](https://dom-orm.erikpoehler.com/virtual-filesystem/)

### Explanations
1. Look at the demo entities [VirtualFolder](https://github.com/vardumper/dom-orm/blob/main/demos/virtual-filesystem/models/VirtualFolder.php) and [VirtualFile](https://github.com/vardumper/dom-orm/blob/main/demos/virtual-filesystem/models/VirtualFile.php). Both define what fields they consist of and how to store them in DOM-ORM by using PHP attributes.
2. In the Demo, you can rename files and folders and create new ones.
3. You wouldn't want to actually store file content in a XML file which is read to memory. This potentially crashes PHP or may consume too much memory. It's an example. 
4. The Demo is reset automatically. It uses XSLT for rendering the XML directly.

## A minmal blog

![blog](https://dom-orm.erikpoehler.com/blog/assets/images/blog.png)

[Blog Demo](https://dom-orm.erikpoehler.com/blog/)

This example illustrates how to store Article, Image and Comment entities into a DOM-ORM XML flatfile database. See the [source code](https://github.com/vardumper/dom-orm/tree/main/demos/blog). 

### Explanation
1. In the Demo, you see a basic blog theme with Twig rendered templates.
2. Head to [Admin](https://dom-orm.erikpoehler.com/blog/admin) in order to add or delete posts and comments.
3. A not on images: in the demo we save base64 encoded binary data in the DOM ORM XML. When DOM ORM reads the XML it stores it into memory, so artificially bloating the XML is an anti-pattern. In production you would simply store a path to the file. 
4. The Demo is reset automatically.