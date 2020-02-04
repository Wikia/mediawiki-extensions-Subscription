CREATE TABLE /*_*/subscription_comp (
  `scid` int(11) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT '0',
  `expires` int(14) NOT NULL DEFAULT '0',
  `user_id` int(11) DEFAULT NULL
) /*$wgDBTableOptions*/;

ALTER TABLE /*_*/subscription_comp
  ADD PRIMARY KEY (`scid`),
  ADD UNIQUE KEY `user_id` (`user_id`);

ALTER TABLE /*_*/subscription_comp
  MODIFY `scid` int(11) NOT NULL AUTO_INCREMENT;