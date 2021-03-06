CREATE TABLE dewdrop_mail_log (
    dewdrop_mail_log_id INTEGER AUTO_INCREMENT PRIMARY KEY,
    to_address VARCHAR(128) NOT NULL,
    from_address VARCHAR(128) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT,
    body_plaintext TEXT,
    date_sent DATETIME NOT NULL,
    sent_successfully BOOLEAN NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

CREATE TABLE dewdrop_notification_frequencies (
    dewdrop_notification_frequency_id INTEGER AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

INSERT INTO dewdrop_notification_frequencies (name) VALUES
    ('Immediately'),
    ('Daily'),
    ('Weekly');

CREATE TABLE dewdrop_notification_subscriptions (
    dewdrop_notification_subscription_id INTEGER AUTO_INCREMENT PRIMARY KEY,
    component VARCHAR(128) NOT NULL,
    dewdrop_notification_frequency_id INTEGER NOT NULL,
    when_added BOOLEAN DEFAULT true NOT NULL,
    when_edited BOOLEAN DEFAULT true NOT NULL,
    preferred_time_of_day TIME,
    preferred_day_of_week INTEGER,
    date_created DATETIME NOT NULL,
    date_updated DATETIME NOT NULL,
    FOREIGN KEY (dewdrop_notification_frequency_id) REFERENCES dewdrop_notification_frequencies (dewdrop_notification_frequency_id)
) ENGINE=InnoDB CHARSET=utf8;

CREATE TABLE dewdrop_notification_subscription_recipients (
    dewdrop_notification_subscription_recipient_id INTEGER AUTO_INCREMENT PRIMARY KEY,
    dewdrop_notification_subscription_id INTEGER NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    FOREIGN KEY (dewdrop_notification_subscription_id) REFERENCES dewdrop_notification_subscriptions (dewdrop_notification_subscription_id)
) ENGINE=InnoDB CHARSET=utf8;

CREATE TABLE dewdrop_notification_subscription_fields (
    dewdrop_notification_subscription_id INTEGER NOT NULL,
    field_id VARCHAR(128) NOT NULL,
    PRIMARY KEY (dewdrop_notification_subscription_id, field_id),
    FOREIGN KEY (dewdrop_notification_subscription_id) REFERENCES dewdrop_notification_subscriptions (dewdrop_notification_subscription_id)
) ENGINE=InnoDB CHARSET=utf8;

CREATE TABLE dewdrop_notification_subscription_log (
    dewdrop_notification_subscription_id INTEGER NOT NULL,
    dewdrop_mail_log_id INTEGER NOT NULL,
    PRIMARY KEY (dewdrop_notification_subscription_id, dewdrop_mail_log_id),
    FOREIGN KEY (dewdrop_notification_subscription_id) REFERENCES dewdrop_notification_subscriptions (dewdrop_notification_subscription_id),
    FOREIGN KEY (dewdrop_mail_log_id) REFERENCES dewdrop_mail_log (dewdrop_mail_log_id)
) ENGINE=InnoDB CHARSET=utf8;
