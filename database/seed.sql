-- =====================================================================
-- CampusFind seed data: lookup lists only.
-- Safe to re-run (INSERT IGNORE skips rows that already exist).
--
-- No user accounts are seeded here, so no password ever lives in a SQL
-- file. The first admin account will be created with a command-line
-- script when the Authentication module is built.
-- =====================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO categories (name, slug, icon) VALUES
    ('Electronics',        'electronics', 'bi-laptop'),
    ('ID Cards & Documents','id-cards',   'bi-person-vcard'),
    ('Books & Stationery', 'books',       'bi-book'),
    ('Keys',               'keys',        'bi-key'),
    ('Clothing',           'clothing',    'bi-handbag'),
    ('Bags & Wallets',     'bags',        'bi-backpack'),
    ('Jewellery & Watches','jewellery',   'bi-smartwatch'),
    ('Water Bottles',      'bottles',     'bi-cup-straw'),
    ('Sports Equipment',   'sports',      'bi-dribbble'),
    ('Other',              'other',       'bi-question-circle');

INSERT IGNORE INTO locations (name, building, description) VALUES
    ('Main Library',        'Library Block',  NULL),
    ('Cafeteria',           'Student Centre', NULL),
    ('Academic Block A',    'Block A',        NULL),
    ('Academic Block B',    'Block B',        NULL),
    ('Computer Lab',        'Block A',        NULL),
    ('Auditorium',          'Main Building',  NULL),
    ('Sports Complex',      'Sports Complex', NULL),
    ('Hostel Area',         'Hostels',        NULL),
    ('Parking Lot',         NULL,             NULL),
    ('Main Gate',           NULL,             NULL),
    ('Other / Not Sure',    NULL,             'Use the detail field to describe the place');
