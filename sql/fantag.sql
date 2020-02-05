CREATE TABLE /*_*/fantag (
  fantag_id int(11) NOT NULL PRIMARY KEY auto_increment,
  fantag_title varchar(100) NOT NULL,
  fantag_pg_id int(11) NOT NULL,
  fantag_left_text varchar(20) default NULL,
  fantag_left_textcolor varchar(20) default NULL,
  fantag_left_bgcolor varchar(20) default NULL,
  fantag_right_text varchar(90) NOT NULL,
  fantag_right_textcolor varchar(20) default NULL,
  fantag_right_bgcolor varchar(20) default NULL,
  fantag_actor bigint unsigned NOT NULL,
  fantag_date datetime NOT NULL,
  fantag_count int(11) NOT NULL default '1',
  fantag_image_name varchar(255) default NULL,
  fantag_left_textsize varchar(20) default NULL,
  fantag_right_textsize varchar(20) default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/fantag_actor ON /*_*/fantag (fantag_actor);
CREATE INDEX /*i*/fantag_pg_id ON /*_*/fantag (fantag_pg_id);
