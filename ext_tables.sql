#
# Table structure for table 'sys_file_metadata'
#
CREATE TABLE sys_file_metadata (
    enable_indexing tinytext
);

#
# Table structure for table 'sys_file_collection'
#
CREATE TABLE sys_file_collection (
    use_for_solr tinytext
);

#
# Table structure for table 'tx_solrfileindexer_items'
#
CREATE TABLE tx_solrfileindexer_items (
  root int(11) DEFAULT '0' NOT NULL,
  item_type varchar(255) DEFAULT '' NOT NULL,
	item_uid int(11) DEFAULT '0' NOT NULL,
	indexing_configuration varchar(255) DEFAULT '' NOT NULL,
	changed int(11) DEFAULT '0' NOT NULL,
	localized_uid int(11) DEFAULT '0' NOT NULL,
);
