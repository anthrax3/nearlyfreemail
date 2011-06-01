
CREATE TABLE accounts
(
    id            INTEGER      PRIMARY KEY AUTO_INCREMENT,
    default_alias INTEGER      NOT NULL,
    password      VARCHAR(255) NOT NULL,
    created_time  INTEGER      NOT NULL,
    is_admin      INTEGER      NOT NULL DEFAULT 0
);
CREATE INDEX ix_accounts_created_time ON accounts (created_time);
CREATE INDEX ix_accounts_is_admin     ON accounts (is_admin);

CREATE TABLE aliases
(
    id           INTEGER      PRIMARY KEY AUTO_INCREMENT,
    account_id   INTEGER      NOT NULL REFERENCES accounts (id) ON DELETE CASCADE,
    name         VARCHAR(255) NOT NULL,
    email        VARCHAR(255) NOT NULL UNIQUE,
    incoming_key VARCHAR(255) NOT NULL UNIQUE,
    signature    VARCHAR(255) NOT NULL,
    created_time INTEGER      NOT NULL,
    notes        TEXT         NOT NULL
);
CREATE INDEX ix_aliases_account_id   ON aliases (account_id);
CREATE INDEX ix_aliases_name         ON aliases (name);
CREATE INDEX ix_aliases_email        ON aliases (email);
CREATE INDEX ix_aliases_incoming_key ON aliases (incoming_key);

CREATE TABLE settings
(
    id         INTEGER      PRIMARY KEY AUTO_INCREMENT,
    account_id INTEGER      REFERENCES accounts (id) ON DELETE CASCADE,
    s_key      VARCHAR(255) NOT NULL,
    s_value    TEXT         NOT NULL
);
CREATE INDEX ix_settings_account_id ON settings (account_id);
CREATE INDEX ix_settings_s_key      ON settings (s_key);

CREATE TABLE rules
(
    id         INTEGER PRIMARY KEY AUTO_INCREMENT,
    account_id INTEGER NOT NULL REFERENCES accounts (id) ON DELETE CASCADE,
    priority   INTEGER NOT NULL DEFAULT 0,
    rule       TEXT    NOT NULL,
    action     TEXT    NOT NULL
);
CREATE INDEX ix_rules_account_id ON rules (account_id);
CREATE INDEX ix_rules_priority   ON rules (priority);

CREATE TABLE contacts
(
    id         INTEGER      PRIMARY KEY AUTO_INCREMENT,
    account_id INTEGER      NOT NULL REFERENCES accounts (id) ON DELETE CASCADE,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    notes      VARCHAR(255) NOT NULL,
    last_used  INTEGER      NOT NULL
);
CREATE INDEX ix_contacts_account_id ON contacts (account_id);
CREATE INDEX ix_contacts_name       ON contacts (name);
CREATE INDEX ix_contacts_email      ON contacts (email);
CREATE INDEX ix_contacts_notes      ON contacts (notes);
CREATE INDEX ix_contacts_last_used  ON contacts (last_used);

CREATE TABLE folders
(
    id           INTEGER      PRIMARY KEY AUTO_INCREMENT,
    account_id   INTEGER      NOT NULL REFERENCES accounts (id) ON DELETE CASCADE,
    name         VARCHAR(255) NOT NULL,
    messages_all INTEGER      NOT NULL DEFAULT 0,
    messages_new INTEGER      NOT NULL DEFAULT 0
);
CREATE INDEX ix_folders_account_id ON folders (account_id);
CREATE INDEX ix_folders_name       ON folders (name);

CREATE TABLE messages
(
    id            INTEGER      PRIMARY KEY AUTO_INCREMENT,
    account_id    INTEGER      NOT NULL REFERENCES accounts (id) ON DELETE CASCADE,
    alias_id      INTEGER      REFERENCES aliases (id) ON DELETE SET NULL,
    folder_id     INTEGER      NOT NULL REFERENCES folders (id) ON DELETE CASCADE,
    msgid         VARCHAR(255) NOT NULL,
    sender        TEXT         NOT NULL,
    recipient     MEDIUMTEXT   NOT NULL,
    refs          MEDIUMTEXT   NOT NULL,
    cc            MEDIUMTEXT   NOT NULL,
    bcc           MEDIUMTEXT   NOT NULL,
    reply_to      TEXT         NOT NULL,
    subject       TEXT         NOT NULL,
    content       LONGTEXT     NOT NULL,
    charset       TEXT         NOT NULL,
    sent_time     INTEGER      NOT NULL,
    received_time INTEGER      NOT NULL,
    attachments   INTEGER      NOT NULL,
    spam_score    FLOAT        NOT NULL,
    is_draft      INTEGER      NOT NULL DEFAULT 0,  -- 0: received mail, 1: draft, 2: sent.
    is_read       INTEGER      NOT NULL DEFAULT 0,  -- 0: unread, 1: read.
    is_replied    INTEGER      NOT NULL DEFAULT 0,  -- 0: none, 1: replied, 2: forwarded, 3: replied and forwarded.
    is_starred    INTEGER      NOT NULL DEFAULT 0,  -- 0: none, 1: starred.
    notes         TEXT         NOT NULL
);
CREATE INDEX ix_messages_account_id    ON messages (account_id);
CREATE INDEX ix_messages_alias_id      ON messages (alias_id);
CREATE INDEX ix_messages_folder_id     ON messages (folder_id);
CREATE INDEX ix_messages_msgid         ON messages (msgid);
CREATE INDEX ix_messages_sent_time     ON messages (sent_time);
CREATE INDEX ix_messages_received_time ON messages (received_time);
CREATE INDEX ix_messages_attachments   ON messages (attachments);
CREATE INDEX ix_messages_is_draft      ON messages (is_draft);
CREATE INDEX ix_messages_is_read       ON messages (is_read);
CREATE INDEX ix_messages_is_replied    ON messages (is_replied);
CREATE INDEX ix_messages_is_starred    ON messages (is_starred);

CREATE TABLE attachments
(
    id         INTEGER      PRIMARY KEY AUTO_INCREMENT,
    message_id INTEGER      NOT NULL REFERENCES messages (id) ON DELETE CASCADE,
    filename   VARCHAR(255) NOT NULL,
    filesize   INTEGER      NOT NULL,
    deleted    INTEGER      NOT NULL,
    content    LONGBLOB     NOT NULL
);
CREATE INDEX ix_attachments_message_id ON attachments (message_id);

CREATE TABLE originals
(
    message_id INTEGER  NOT NULL REFERENCES messages (id) ON DELETE CASCADE,
    subject    LONGBLOB NOT NULL,
    content    LONGBLOB NOT NULL,
    source     LONGBLOB NOT NULL
);
CREATE INDEX ix_originals_message_id ON originals (message_id);
