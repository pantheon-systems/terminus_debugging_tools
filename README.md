# terminus_cse_tools
A collection of terminus plugins to facilitate managing sites on Pantheon.

For installation help, see [Terminus Plugins Wiki](https://github.com/pantheon-systems/terminus/wiki/Plugins)

# Tools

## Blob

This command looks for large blobs in your database.  Large blobs can break replication and otherwise cause issue.

Example:

```
terminus blob --site MY_SITE_UUID --env MY_ENV
```
