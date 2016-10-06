block_access_records
--------------------

Blocks are super powerful in D8. The page title can be a block, messages are a block, contact forms can be a block… This means your site may have dozens or even hundreds of blocks!

Unfortunately, checking which blocks should be visible on a page gets really slow when you have a lot of blocks. Drupal insists on loading all the blocks, then checking each and every one individually. I've seen it take up to 130 ms!

This module implements a fast way of checking, inspired by the node_access system. It generates a single query that does most of the work, and then only loads the blocks that are likely to be visible. On the site mentioned above, it cut over 90 ms off the time taken.

It currently works with all the visibility conditions in Drupal core: path, language, user role, and node type. It's also extensible, so you can add plugins that handle other types of conditions.

Caveats:
* If you have custom conditions, you'll have to extend this module. This module does at least ensure that you don't add conditions that aren't supported.
* Entity or block access hooks are only invoked on blocks whose conditions seem to pass, since we don't want to load other blocks. There's a new hook_block_visibility_alter() so you can add blocks whose conditions failed.
* This is not well tested, so beware of bugs.

There is a core issue about the slowness of core's block visibility checking: https://www.drupal.org/node/2479459 . I'm not sure the solutions there are ideal, because blocks may vary by path, which changes all the time. So this is my attempt at a better solution.
