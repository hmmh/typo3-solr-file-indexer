.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _users-manual:

For Editors
===========

Adding files to the search index
--------------------------------

Files are added to the search index via file collections (list module, record type "File Collection"):

#. Create a file collection and choose the type:

   *  **Folder**: all files in the selected folder (optionally including subfolders)
   *  **Static**: individually selected files
   *  **Category**: all files with the selected category

#. Select the language of the collection: "All" to index the files in all languages, or a specific language to index
   them only in this language.
#. In the "Search" tab, select the site roots the files should be indexed for.

Only file types allowed by the integrator (e.g. pdf, docx) are indexed. The files are added to the search index with the
next run of the scheduler tasks.

Translations
------------

Translate the file metadata (title, description, ...) in the file list to show translated titles in the search result.
Without a translation, the metadata of the default language is used.

Changes and removal
-------------------

*  Changes to the file metadata are indexed with the next run of the scheduler tasks, the file stays in the search
   index in the meantime.
*  Deleted files are removed from the search index immediately.
*  Files that are no longer part of a file collection (moved to another folder, removed from a static collection, the
   collection is hidden or deleted, or no longer enabled for the site) are removed with the next run of the scheduler
   tasks.
