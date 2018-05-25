.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


What does it do?
================

This extension gives you the capability to index individual documents using Solr.

Apache Tika, which is capable of detecting and extracting metadata from approx. 1200 different file types is used for document content analysis. The configuration for Tika can be implemented directly within the extension, Solr server functionality is then used for parsing.

Individual documents can be added to the search index for the default language or any localisation, likewise site roots can be selected for which the document is to indexed.
