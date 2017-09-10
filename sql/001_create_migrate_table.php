<?php
/**
 * i-MSCP Migrator plugin
 * Copyright (C) 2017 Cambell Prince <cambell.prince@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

return array(
    'up'   => '
        CREATE TABLE IF NOT EXISTS `migrator_sgw` (
            `migrate_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) unsigned NOT NULL,
            `resource_type` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
            `resource_id` int(11) unsigned NOT NULL,
            `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `info` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            PRIMARY KEY (migrate_id),
            KEY migrate_id (migrate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ',
    'down' => '
        DROP TABLE IF EXISTS migrator_sgw
    '
);
