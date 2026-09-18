#
# Site configuration (backend module)
#
CREATE TABLE tx_casablancabooking_configuration (
    uid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid INT(11) UNSIGNED DEFAULT '0' NOT NULL,
    tstamp INT(11) UNSIGNED DEFAULT '0' NOT NULL,
    crdate INT(11) UNSIGNED DEFAULT '0' NOT NULL,

    site_identifier VARCHAR(100) DEFAULT '' NOT NULL,
    tenant_id VARCHAR(64) DEFAULT '' NOT NULL,
    url_friendly_ibe_context_id VARCHAR(100) DEFAULT 'bookingengine' NOT NULL,
    api_key_encrypted TEXT,
    api_base_url VARCHAR(255) DEFAULT 'https://api.casablanca.at' NOT NULL,
    ibe_base_url VARCHAR(255) DEFAULT 'https://bookingengine.casablanca.at' NOT NULL,
    ibe_link_style VARCHAR(30) DEFAULT 'full_path' NOT NULL,
    use_custom_ibe_domain TINYINT(1) DEFAULT 0 NOT NULL,
    service_path VARCHAR(50) DEFAULT 'ibe' NOT NULL,
    default_culture VARCHAR(10) DEFAULT 'de' NOT NULL,
    sync_range_days INT(11) DEFAULT 365 NOT NULL,
    sync_chunk_days INT(11) DEFAULT 31 NOT NULL,
    pagination_top INT(11) DEFAULT 100 NOT NULL,
    default_adults INT(4) DEFAULT 2 NOT NULL,
    default_children_ages TEXT,
    connection_status VARCHAR(20) DEFAULT 'unknown' NOT NULL,
    connection_checked_at INT(11) UNSIGNED DEFAULT 0 NOT NULL,
    connection_message TEXT,

    PRIMARY KEY (uid),
    UNIQUE KEY site_identity (site_identifier)
) ENGINE=InnoDB;

#
# Availability cache
#
CREATE TABLE tx_casablancabooking_domain_model_availability (
    site_identifier VARCHAR(100) DEFAULT '' NOT NULL,
    tenant_id VARCHAR(64) DEFAULT '' NOT NULL,
    ibe_context_id VARCHAR(100) DEFAULT '' NOT NULL,
    room_type_id VARCHAR(100) DEFAULT '' NOT NULL,
    rate_id VARCHAR(100) DEFAULT '' NOT NULL,
    effective_date DATE DEFAULT '1970-01-01' NOT NULL,
    from_price DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT 'EUR' NOT NULL,
    is_available TINYINT(1) DEFAULT 0 NOT NULL,
    is_arrival_allowed TINYINT(1) DEFAULT 0 NOT NULL,
    is_departure_allowed TINYINT(1) DEFAULT 0 NOT NULL,
    min_length_of_stay INT(4) DEFAULT 0 NOT NULL,
    max_length_of_stay INT(4) DEFAULT 0 NOT NULL,
    bookable_nights TEXT,
    bookable_nights_with_packages TEXT,
    restrictions TEXT,
    previous_day_blocked TINYINT(1) DEFAULT 0 NOT NULL,
    next_day_blocked TINYINT(1) DEFAULT 0 NOT NULL,
    data_hash VARCHAR(64) DEFAULT '' NOT NULL,

    KEY ari_lookup (site_identifier, room_type_id, effective_date),
    KEY ari_date (effective_date),
    PRIMARY KEY (site_identifier, room_type_id, rate_id, effective_date)
) ENGINE=InnoDB;

#
# Room types mirror
#
CREATE TABLE tx_casablancabooking_domain_model_roomtype (
    uid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid INT(11) UNSIGNED DEFAULT '0' NOT NULL,
    tstamp INT(11) UNSIGNED DEFAULT '0' NOT NULL,
    crdate INT(11) UNSIGNED DEFAULT '0' NOT NULL,

    site_identifier VARCHAR(100) DEFAULT '' NOT NULL,
    tenant_id VARCHAR(64) DEFAULT '' NOT NULL,
    ibe_context_id VARCHAR(100) DEFAULT '' NOT NULL,
    room_type_id VARCHAR(100) DEFAULT '' NOT NULL,
    company_id VARCHAR(64) DEFAULT '' NOT NULL,
    name VARCHAR(255) DEFAULT '' NOT NULL,
    slug VARCHAR(255) DEFAULT '' NOT NULL,
    description TEXT,
    short_description TEXT,
    image_url VARCHAR(255) DEFAULT '' NOT NULL,
    images TEXT,
    standard_occupancy INT(4) DEFAULT 2 NOT NULL,
    min_occupancy INT(4) DEFAULT 1 NOT NULL,
    max_occupancy INT(4) DEFAULT 4 NOT NULL,
    sort_order INT(11) DEFAULT 0 NOT NULL,

    PRIMARY KEY (uid),
    UNIQUE KEY room_type_identity (site_identifier, room_type_id),
    KEY site_lookup (site_identifier),
    KEY site_slug_lookup (site_identifier, slug)
) ENGINE=InnoDB;

#
# Rates / packages mirror
#
CREATE TABLE tx_casablancabooking_domain_model_rate (
    uid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pid INT(11) UNSIGNED DEFAULT '0' NOT NULL,
    tstamp INT(11) UNSIGNED DEFAULT '0' NOT NULL,
    crdate INT(11) UNSIGNED DEFAULT '0' NOT NULL,

    site_identifier VARCHAR(100) DEFAULT '' NOT NULL,
    tenant_id VARCHAR(64) DEFAULT '' NOT NULL,
    ibe_context_id VARCHAR(100) DEFAULT '' NOT NULL,
    rate_id VARCHAR(100) DEFAULT '' NOT NULL,
    name VARCHAR(255) DEFAULT '' NOT NULL,
    slug VARCHAR(255) DEFAULT '' NOT NULL,
    description TEXT,
    short_description TEXT,
    image_url VARCHAR(255) DEFAULT '' NOT NULL,
    images TEXT,
    is_package TINYINT(1) DEFAULT 0 NOT NULL,
    catering_type VARCHAR(50) DEFAULT '' NOT NULL,
    sort_order INT(11) DEFAULT 0 NOT NULL,

    PRIMARY KEY (uid),
    UNIQUE KEY rate_identity (site_identifier, rate_id),
    KEY site_package (site_identifier, is_package),
    KEY package_slug (site_identifier, slug)
) ENGINE=InnoDB;

#
# Sync audit log
#
CREATE TABLE tx_casablancabooking_domain_model_synclog (
    uid INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    site_identifier VARCHAR(100) DEFAULT '' NOT NULL,
    started_at INT(11) UNSIGNED DEFAULT 0 NOT NULL,
    finished_at INT(11) UNSIGNED DEFAULT 0 NOT NULL,
    status VARCHAR(20) DEFAULT '' NOT NULL,
    rows_written INT(11) DEFAULT 0 NOT NULL,
    rows_changed INT(11) DEFAULT 0 NOT NULL,
    cache_tags_flushed INT(11) DEFAULT 0 NOT NULL,
    message TEXT,

    PRIMARY KEY (uid),
    KEY site_started (site_identifier, started_at)
) ENGINE=InnoDB;
