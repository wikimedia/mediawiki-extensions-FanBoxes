DROP SEQUENCE IF EXISTS fantag_fantag_id_seq CASCADE;
CREATE SEQUENCE fantag_fantag_id_seq;

CREATE TABLE fantag (
  fantag_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('fantag_fantag_id_seq'),
  fantag_title TEXT NOT NULL,
  fantag_pg_id INTEGER NOT NULL,
  fantag_left_text TEXT default NULL,
  fantag_left_textcolor TEXT default NULL,
  fantag_left_bgcolor TEXT default NULL,
  fantag_right_text TEXT NOT NULL,
  fantag_right_textcolor TEXT default NULL,
  fantag_right_bgcolor TEXT default NULL,
  fantag_actor INTEGER NOT NULL,
  fantag_date TIMESTAMPTZ NOT NULL,
  fantag_count INTEGER NOT NULL default 1,
  fantag_image_name TEXT default NULL,
  fantag_left_textsize TEXT default NULL,
  fantag_right_textsize TEXT default NULL
);

ALTER SEQUENCE fantag_fantag_id_seq OWNED BY fantag.fantag_id;

CREATE INDEX fantag_actor ON fantag (fantag_actor);
CREATE INDEX fantag_pg_id ON fantag (fantag_pg_id);

DROP SEQUENCE IF EXISTS user_fantag_userft_id_seq CASCADE;
CREATE SEQUENCE user_fantag_userft_id_seq;

CREATE TABLE user_fantag (
  userft_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('user_fantag_userft_id_seq'),
  userft_fantag_id INTEGER NOT NULL,
  userft_actor INTEGER NOT NULL,
  userft_date TIMESTAMPTZ NOT NULL,
  -- @todo FIXME: Drop this column, see https://phabricator.wikimedia.org/T242868
  userft_order INTEGER NOT NULL
);

ALTER SEQUENCE user_fantag_userft_id_seq OWNED BY user_fantag.userft_id;

CREATE INDEX userft_fantag_id ON user_fantag (userft_fantag_id);
CREATE INDEX userft_actor ON user_fantag (userft_actor);