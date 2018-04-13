.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


What does it do?
================

Mit der Extension können einzelne Dokumente über Solr indexiert werden. Für das Parsen des Dokumentinhaltes wird Apache Tika
verwendet, das über 1000 Dateiformate parsen kann. Die Einstellung für Tika kann in der entsprechenden Extension direkt vorgenommen
werden. Für das Parsen reicht der Solr-Server, der die entsprechende Funktion mitbringt.

Jedes Dokument kann individuell in der Default-Sprache oder für jede Lokalisierung dem Suchindex hinzugefügt werden. Ebenso
kann für jedes Dokument individuell gewählt werden, für welche Siteroots es indexiert werden soll.
