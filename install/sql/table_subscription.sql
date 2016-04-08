CREATE TABLE /*_*/subscription (
  `sid` int(32) NOT NULL,
  `global_id` int(14) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `begins` binary(14) DEFAULT NULL,
  `expires` binary(14) DEFAULT NULL,
  `plan_id` varbinary(255) NOT NULL DEFAULT '',
  `plan_name` varbinary(255) NOT NULL DEFAULT '',
  `price` float NOT NULL DEFAULT '0',
  `subscription_id` varbinary(255) NOT NULL DEFAULT ''
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/subscription ADD PRIMARY KEY (`sid`), ADD KEY `active` (`active`), ADD KEY `expires_begins` (`expires`,`begins`), ADD KEY `global_id` (`global_id`);

ALTER TABLE /*_*/subscription MODIFY `sid` int(32) NOT NULL AUTO_INCREMENT;