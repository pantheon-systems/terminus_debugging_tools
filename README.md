# terminus_cse_tools
A collection of terminus plugins to facilitate managing sites on Pantheon.

For installation help, see [Terminus Plugins Wiki](https://github.com/pantheon-systems/terminus/wiki/Plugins)

# Tools

## Blob

This command looks for large blobs in your database.  Large blobs can break replication and otherwise cause issue.

Example:

```
terminus blob columns --site=SITE_NAME --env=ENV
terminus blob cells --site=SITE_NAME --env=ENV --table=TABLE_NAME --column=COLUMNA_NAME
```
