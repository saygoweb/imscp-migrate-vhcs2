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

namespace Migrator;

use iMSCP_Database as Database;
use iMSCP_Events as Events;
use iMSCP_Events_Aggregator as EventManager;
use iMSCP_Exception_Database as DatabaseException;
use iMSCP_pTemplate as TemplateEngine;
use iMSCP_Registry as Registry;
use PDO;

require_once(__DIR__ . '/../migrator_database.php');

/***********************************************************************************************************************
 * Functions
 */


/**
 * Generate customer list for which Migrator can be activated
 *
 * @param $tpl TemplateEngine
 * @return void
 */
function _migrator_generateRemoteUserList($tpl) {
    $rdb = migrator_remoteDB();
    $stmt = $rdb->prepare(
        '
            SELECT * FROM users
        '
    );
    $result = $stmt->execute();
    if ($result) {
        foreach ($result as $row) {
            $tpl->assign(array(
    
            ));
            $tpl->parse('ITEM', '.item');
        }
    } else {
        $tpl->assign('SELECT_LIST', '');        
    }
}

/**
 * Generate page
 *
 * @param TemplateEngine $tpl
 * @return void
 */
function migrator_generatePage($tpl)
{
    // _migrator_generateRemoteUserList($tpl);

    $cfg = Registry::get('config');
    $rowsPerPage = $cfg['DOMAIN_ROWS_PER_PAGE'];

    if (isset($_GET['psi']) && $_GET['psi'] == 'last') {
        unset($_GET['psi']);
    }

    $startIndex = isset($_GET['psi']) ? (int)$_GET['psi'] : 0;

    $rdb = migrator_remoteDB();

    $stmt = $rdb->prepare(
        '
            SELECT COUNT(domain_id) AS cnt FROM domain
        '
    );
    $result = $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $rowCount = $row['cnt'];

    if ($rowCount) {
        $stmt = $rdb->prepare(
            "
                SELECT domain_id, domain_name FROM domain
                ORDER BY domain_name ASC LIMIT $startIndex, $rowsPerPage
            "
        );
        $result = $stmt->execute();
        foreach ($stmt as $row) {
            // $tpl->assign(array(
            //     'KEY_STATUS'  => translate_dmn_status($row['migrator_status']),
            //     'STATUS_ICON' => $statusIcon,
            //     'DOMAIN_NAME' => tohtml(decode_idna($row['domain_name'])),
            //     'DOMAIN_KEY'  => ($row['domain_text'])
            //         ? tohtml($row['domain_text']) : tr('Generation in progress.'),
            //     'DNS_NAME'    => ($dnsName) ? tohtml($dnsName) : tr('n/a'),
            //     'LETSENCRYPT_ID' => tohtml($row['migrator_id'])
            // ));
            $tpl->assign(array(
                'DOMAIN_NAME'   => tohtml(decode_idna($row['domain_name'])),
                'MIGRATE'       => tr('Migrate...'),
                'MIGRATE_INFO'  => tr('Click to begin migrating this user from VHCS2 to i-MSCP.'),
                'MIGRATE_LINK'  => 'migrate_user.php?id=' . $row['domain_id'],
                'DOMAIN_ID' => tohtml($row['domain_id'])
            ));

            $tpl->parse('KEY_ITEM', '.key_item');

        }

        $prevSi = $startIndex - $rowsPerPage;

        if ($startIndex == 0) {
            $tpl->assign('SCROLL_PREV', '');
        } else {
            $tpl->assign(array(
                'SCROLL_PREV_GRAY' => '',
                'PREV_PSI'         => $prevSi
            ));
        }

        $nextSi = $startIndex + $rowsPerPage;

        if ($nextSi + 1 > $rowCount) {
            $tpl->assign('SCROLL_NEXT', '');
        } else {
            $tpl->assign(array(
                'SCROLL_NEXT_GRAY' => '',
                'NEXT_PSI'         => $nextSi
            ));
        }
    } else {
        $tpl->assign('MIGRATION_LIST', '');
        set_page_message(tr('No customer with Migrator support has been found.'), 'static_info');
    }
}

/***********************************************************************************************************************
 * Main
 */

EventManager::getInstance()->dispatch(Events::onResellerScriptStart);
check_login('reseller');

if (!resellerHasCustomers()) {
    showBadRequestErrorPage();
}

if (isset($_REQUEST['action'])) {
    $action = clean_input($_REQUEST['action']);

    if (isset($_REQUEST['admin_id']) && $_REQUEST['admin_id'] != '') {
        $customerId = intval($_REQUEST['admin_id']);

        switch ($action) {
            case 'activate':
                migrator_activate($customerId);
                break;
            case 'deactivate';
                migrator_deactivate($customerId);
                break;
            default:
                showBadRequestErrorPage();
        }

        redirectTo('migrator.php');
    } else {
        showBadRequestErrorPage();
    }
}

$tpl = new TemplateEngine();
$tpl->define_dynamic(array(
    'layout'           => 'shared/layouts/ui.tpl',
    'page'             => '../../plugins/Migrator/themes/default/view/reseller/migrator.tpl',
    'page_message'     => 'layout',
    'select_list'      => 'page',
    'select_item'      => 'select_list',
    'migration_list'    => 'page',
    // 'customer_item'    => 'customer_list',
    'key_item'         => 'migration_list', // was customer_item not list
    'scroll_prev_gray' => 'migration_list',
    'scroll_prev'      => 'migration_list',
    'scroll_next_gray', 'migration_list',
    'scroll_next'      => 'migration_list'
));

$tpl->assign(array(
    'TR_PAGE_TITLE'           => tr('Customers / Migrator'),
    'TR_DOMAIN_NAME'          => tr('User / Domain Name'),
    'TR_STATUS'               => tr('Status'),
    'TR_ACTION'               => tr('Action'),
    'TR_PREVIOUS'             => tr('Previous'),
    'TR_NEXT'                 => tr('Next')
));

generateNavigation($tpl);
migrator_generatePage($tpl);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');
EventManager::getInstance()->dispatch(Events::onResellerScriptEnd, array('templateEngine' => $tpl));
$tpl->prnt();

