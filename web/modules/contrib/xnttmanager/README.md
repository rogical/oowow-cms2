External Entities Manager
*************************

Provides External Entities management features like field discovery and Drupal
content synchronization.

===============================

CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Troubleshooting
 * FAQ
 * Maintainers

INTRODUCTION
------------

This modules provides a way to automatically save and synchronize external
entities content with a given Drupal node content. While External Entities
module provides a way to annotate external entity contents with Drupal content,
the annotation must be managed manually for each node and does not contain
external entity data. External Entities also provides cache for its external
content but the cache serves a different purpose as well (ie. created for loaded
entities and valid for a given amount of time) and is not designed for
edition.

With this module, a Drupal content type can be automatically generated and
synchronized (using a cron) with an external entities content type. Missing
fields are also added and modified field settings are updated as well.

The main purpose of this module is to import into a local Drupal, data
aggregated and mixed for multiple external sources using the External Entities
Multiple Storage plugin (xnttmulti) module. Then, many other application are
possible: import content from another site or database, convert data, publish
data through a reviewing process, use external tools with Drupal content and
more.

REQUIREMENTS
------------

This module requires the following modules:

 * [External Entities](https://www.drupal.org/project/external_entities)

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------

The module provides an administrator interface that enables synchronization for
external entity contents at /xntt/manage and /xntt/sync. Menu entries are added
to the "Tools" menu.

MAINTAINERS
-----------

Current maintainers:
 * Valentin Guignon (guignonv) - https://www.drupal.org/u/guignonv
