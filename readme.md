Platform
========

Platform is a small and simple PHP framework. It is designed to be a platform to build your PHP applications upon.

Version
---

0.1

Installation
---

```sh
git clone https://@bitbucket.org/kaigoh/platform.git platform
```

Usage
---

```php
require("Platform.php");
$platformApp = new RTS\Platform\Framework\platformApp();
```

Routing
---

The router matches URLs in the following order:

* Request Type (GET, POST etc.) + Page Name (Method / Function) + File Extension
* Page Name (Method / Function) + File Extension
* Request Type (GET, POST etc.) + Page Name (Method / Function)
* Page Name (Method / Function)
* Error (404) Handler

Platform handles URLs slightly differently that most routers.

For example:

```url
http://yourcoolblog.com/blog/is/really/cool/index.php (GET)
```

would be routed to a controller class called "blog" and would attempt to call the index() method.

The remaining URL segments ("really" and "cool") are available to your method as an array:

```php
$this->urlSegments
```

Another example:

```url
http://yourcoolblog.com/blog/posts.json (GET)
```

Platform also routes according to the file extension. In this case, because the request is asking for a file called posts.json, the request would be routed to a controller class called "blog" and would attempt to call the postsJSON() method.

One more example:

```url
http://yourcoolblog.com/blog/posts.json (POST)
```

Platform also routes accoring to the request method. In this case, the request would be routed to a controller class called "blog" and would attempt to call the postPostsJSON() method. So from the above example, when the request is processed for our ```postsJSON()``` method, Platform also looks for and attempts to call a method in your controller class called ```handlerJSON()```. This can be used for a number of things, such as sending appropriate MIME headers to the users web browser. (Note: Platform includes a couple of handlers by default: JavaScript for .js, JSON for .json and PDF for .pdf)

Methods are named to the convention ```[requestMethod][classMethod][fileExtension]()```. For example, suppose you wanted a method called "invoice" that generates PDF files based on some user data submitted via POST, you could use the following as a method name:

```php
postInvoicePDF()
```

The URL for this could be
```url
http://sales.mybusiness.com/customers/invoice.pdf
```

Why route like this? Various reasons, the primary being simplicity. The second being some cool functionality. Platform includes file handlers that are called based on the detected file extension in the request.


.htaccess
---

Included in the repo, but just in case:

```sh
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule (.*) index.php?$1 [L,QSA]
</IfModule>
```

License
----

MIT - (C) Kai Gohegan, 2014.