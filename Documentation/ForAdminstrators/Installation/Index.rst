.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _admin-manual:

Installation
============

Import
------

Import Extension from the TYPO3 Extension Repository to your server or use composer.

Install
-------

Install the extension in the Extension Manager.

Extension Manager Configuration
-------------------------------

.. figure:: ../../Images/Extsettings.png
	:width: 350px
	:alt: Extension configuration

	Extension Manager Configuration


================================ ===============================================================================================
**Use Tika Extension**           A connection to the Tika server can be use when EXT:tika is enabled
**Solr connection from page id** Root page ID with a Solr connection
**Ignore localization**          Default file metadata will be added to all language cores if no translation exists
================================ ===============================================================================================

Static Template
---------------

To set the base configuration for the index queue, add solr_file_indexer as a static template.
