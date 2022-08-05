# Basic site crawler and importer to WordPress

The plugin is a simple crawler implementation to get content from an external website and import them as a WordPress post.

The plugin doesn't have a UI. It needs to be run n a command line like a WP CLI Command.

## Usage
* Run `composer install` to install the PHP dependecies
* Add the post URLs from where you want to get the content to the `posts.csv` file, one URL per line.
* On the `Parser.php` file, update the `$siteUrl` variable using the final site domain.
* Update the CSS selectors on the `Parser.php` file to the site's CSS selector to get the correct values. Ex. Where to get the H1 info, the date, etc.
* Run the WP CLI Command via the command line.

```
wp direct get
```


