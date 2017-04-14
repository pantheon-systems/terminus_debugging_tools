# terminus_cse_tools
A collection of terminus plugins to facilitate managing sites on Pantheon.

## Blob Blotter commands

```
$ terminus blob:columns SITE.ENV
$ terminus blob:cells SITE.ENV TABLE COLUMN --format=table
```

## Analyze Table commands:

```
$ terminus analyze-table:run SITE.ENV TABLE
$ terminus analyze-table:run SITE.ENV TABLE_1,TABLE_2,TABLE_3
$ terminus analyze-table:run SITE.ENV all
```