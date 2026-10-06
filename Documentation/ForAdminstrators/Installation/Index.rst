.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _admin-installation:

Installation
============

Import
------

Install the extension with composer:

.. code-block:: bash

   composer require hmmh/solr-file-indexer

To index the content of the files, EXT:tika and a Tika server are required as well (see below). Without EXT:tika only
the file metadata is indexed.

.. code-block:: bash

   composer require apache-solr-for-typo3/tika

Install
-------

Set up the database tables of the extension:

.. code-block:: bash

   vendor/bin/typo3 extension:setup -e solr_file_indexer

Extension Configuration
-----------------------

Admin Tools > Settings > Extension Configuration > solr_file_indexer

================================ ===============================================================================================
**Use Tika Extension**           Extract the file content with EXT:tika. If disabled, only the file metadata is indexed
**Local path prefix**            Prefix for the URL of files in a local storage (Default: /)
================================ ===============================================================================================

Tika
----

Since Solr 10, Solr no longer extracts the content of files itself. Configure EXT:tika to use a Tika server
(Admin Tools > Settings > Extension Configuration > tika):

================================ ===============================================================================================
**Extractor**                    Tika Server
**Host / Port**                  Host and port of the Tika server (e.g. tika / 9998)
================================ ===============================================================================================

Static Template
---------------

To set the base configuration for the index queue, add the static template "Solr file indexing"
(EXT:solr_file_indexer/Configuration/TypoScript) or import it in your site package:

.. code-block:: typoscript

   @import 'EXT:solr_file_indexer/Configuration/TypoScript/setup.typoscript'

Configure ``plugin.tx_solr.index.queue.sys_file_metadata.allowedFileTypes`` with a comma separated list of permitted
file types. The ``fields`` parameter maps the ``sys_file_metadata`` fields to the Solr fields.

File collections and languages
------------------------------

Files are added to the search index via file collections (type folder, static or category). Enable the collection for
the desired site roots in the "Search" tab (field "use_for_solr").

The language of the file collection controls the language cores the files are indexed in:

================================ ===============================================================================================
**Language "All"**               The files are indexed in all languages of the site
**A specific language**          The files are only indexed in this language. Use one collection per language (or a localized
                                 collection) to index different files per language.
================================ ===============================================================================================

If the file metadata is translated, the translated metadata (title, description, ...) is indexed in the language core.
Otherwise the metadata of the default language is used.

The console command ``solr_file_indexer:item-queue-worker`` adds the files of the collections to the index queue, see
:doc:`Scheduler <../Scheduler/Index>`.
