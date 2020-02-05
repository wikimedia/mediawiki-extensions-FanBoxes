DROP SEQUENCE IF EXISTS user_fantag_userft_id_seq CASCADE;
CREATE SEQUENCE user_fantag_userft_id_seq;

CREATE TABLE user_fantag (
  userft_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('user_fantag_userft_id_seq'),
  userft_fantag_id INTEGER NOT NULL,
  userft_actor INTEGER NOT NULL,
  userft_date TIMESTAMPTZ NOT NULL
);

ALTER SEQUENCE user_fantag_userft_id_seq OWNED BY user_fantag.userft_id;

CREATE INDEX userft_fantag_id ON user_fantag (userft_fantag_id);
CREATE INDEX userft_actor ON user_fantag (userft_actor);
