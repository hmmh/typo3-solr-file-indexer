.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _admin-manual:

Scheduler
=========

The extension provides two console commands. Both can be run on the command line or added in the scheduler module
as task "Execute console commands".

Item queue worker
-----------------

``solr_file_indexer:item-queue-worker`` adds the files of all file collections enabled for Solr to the Solr index
queue, and removes files that are no longer part of a collection (moved, collection deleted, hidden or no longer
enabled for the site). The index queue worker of EXT:solr then indexes the files with its next run.

Add this command as a recurring task, e.g. every 10 minutes, and run it before the index queue worker of EXT:solr.

================================ ===============================================================================================
**collections** (``-c``)         Comma separated list of file collection uids. Leave empty to process all collections. Localized
                                 file collections have to be specified with their own uid.
================================ ===============================================================================================

.. code-block:: bash

   vendor/bin/typo3 solr_file_indexer:item-queue-worker
   vendor/bin/typo3 solr_file_indexer:item-queue-worker --collections=3,4

Delete by type
--------------

``solr_file_indexer:delete-by-type`` deletes all documents of a type (default: ``sys_file_metadata``) of one site from the
Solr index, in all languages. Documents of other sites in the same Solr core are not affected.

================================ ===============================================================================================
**root-page** (``-p``)           Required. Uid of the site root page (or any page of the site)
**type** (``-t``)                Type of the documents to delete (Default: sys_file_metadata)
**reindex** (``-r``)             Re-initialize the index queue for this type after deleting, so the documents are indexed again
                                 with the next index queue worker run. ``--reindex`` and ``--reindex=1`` enable it.
================================ ===============================================================================================

Without ``--reindex`` the index queue still marks the documents as indexed, so they are only indexed again when the
records change.

.. code-block:: bash

   vendor/bin/typo3 solr_file_indexer:delete-by-type --root-page=1 --reindex
