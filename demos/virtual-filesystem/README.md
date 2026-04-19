# Virtual Filesystem Demo

This demo writes entity data to a sandboxed storage folder under `virtual-filesystem/storage`.

## Run

From `demos/`:

```bash
composer run demo:virtual-filesystem
```

The script resets `virtual-filesystem/storage/data.xml`, persists sample entities,
and prints both entity count and resulting XML.