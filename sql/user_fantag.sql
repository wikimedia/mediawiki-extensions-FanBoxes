CREATE TABLE /*_*/user_fantag (
  userft_id int(11) NOT NULL PRIMARY KEY auto_increment,
  userft_fantag_id int(11) NOT NULL,
  userft_actor bigint unsigned NOT NULL,
  userft_date datetime NOT NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/userft_fantag_id ON /*_*/user_fantag (userft_fantag_id);
CREATE INDEX /*i*/userft_actor ON /*_*/user_fantag (userft_actor);
