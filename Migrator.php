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

/**
 * Class iMSCP_Plugin_Migrator
 */
class iMSCP_Plugin_Migrator extends iMSCP_Plugin_Action
{
    /**
     * Plugin initialization
     *
     * @return void
     */
    public function init()
    {
        l10n_addTranslations(__DIR__ . '/l10n', 'Array', $this->getName());
    }

    /**
     * Register event listeners
     *
     * @param iMSCP_Events_Manager_Interface $eventsManager
     * @return void
     */
    public function register(iMSCP_Events_Manager_Interface $eventsManager)
    {
        // TODO Also sub domains? CP 2017-06
        $eventsManager->registerListener(
            array(
                iMSCP_Events::onResellerScriptStart // Don't think we need this except to enable it at all maybe CP 2017-06
                // iMSCP_Events::onClientScriptStart,
                // iMSCP_Events::onAfterDeleteDomainAlias, // Cleanup Migrator for the domain alias
                // iMSCP_Events::onAfterDeleteCustomer     // Cleanup Migrator for this domain / customer
            ),
            $this
        );
    }

    /**
     * Plugin installation
     *
     * @throws iMSCP_Plugin_Exception
     * @param iMSCP_Plugin_Manager $pluginManager
     * @return void
     */
    public function install(iMSCP_Plugin_Manager $pluginManager)
    {
        try {
            $this->migrateDb('up');
        } catch (iMSCP_Plugin_Exception $e) {
            throw new iMSCP_Plugin_Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Plugin update
     *
     * @throws iMSCP_Plugin_Exception When update fail
     * @param iMSCP_Plugin_Manager $pluginManager
     * @param string $fromVersion Version from which plugin update is initiated
     * @param string $toVersion Version to which plugin is updated
     * @return void
     */
    public function update(iMSCP_Plugin_Manager $pluginManager, $fromVersion, $toVersion)
    {
        try {
            $this->migrateDb('up');
            $this->clearTranslations();
            // iMSCP_Registry::get('dbConfig')->del('PORT_Migrator'); // REVIEW What does this do? CP 2017-06
        } catch (iMSCP_Plugin_Exception $e) {
            throw new iMSCP_Plugin_Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Plugin uninstallation
     *
     * @throws iMSCP_Plugin_Exception
     * @param iMSCP_Plugin_Manager $pluginManager
     * @return void
     */
    public function uninstall(iMSCP_Plugin_Manager $pluginManager)
    {
        try {
            $this->migrateDb('down');
            $this->clearTranslations();
        } catch (iMSCP_Plugin_Exception $e) {
            throw new iMSCP_Plugin_Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * onResellerScriptStart event listener
     *
     * @return void
     */
    public function onResellerScriptStart()
    {
        $this->setupNavigation('reseller');
    }

    /**
     * onClientScriptStart event listener
     *
     * @return void
     */
    public function onClientScriptStart()
    {
        // if (self::customerHasMigrator($_SESSION['user_id'])) { // TODO CP 2017-06
            // $this->setupNavigation('client');
        // }
    }

    /**
     * onAfterDeleteCustomer event listener
     *
     * @param iMSCP_Events_Event $event
     * @return void
     */
    public function onAfterDeleteCustomer(iMSCP_Events_Event $event)
    {
        // exec_query(
        //     'UPDATE migrator SET migrator_status = ? WHERE customer_id = ?',
        //     array('todelete', $event->getParam('customerId'))
        // );
    }

    /**
     * onAfterDeleteDomainAlias event listener
     *
     * @param iMSCP_Events_Event $event
     * @return void
     */
    public function onAfterDeleteDomainAlias(iMSCP_Events_Event $event)
    {
        // exec_query('UPDATE migrator SET migrator_status = ? WHERE alias_id = ?', array(
        //     'todelete', $event->getParam('domainAliasId')
        // ));
    }

    /**
     * Get routes
     *
     * @return array
     */
    public function getRoutes()
    {
        $pluginDir = $this->getPluginManager()->pluginGetDirectory() . '/' . $this->getName();
        return array(
            '/reseller/migrator.php'     => $pluginDir . '/frontend/reseller/migrator.php',
            '/reseller/migrate_user.php' => $pluginDir . '/frontend/reseller/migrate_user.php'
            // '/client/migrator.php'      => $pluginDir . '/frontend/client/migrator.php',
            // '/client/migrator_edit.php' => $pluginDir . '/frontend/client/migrator_edit.php',
            // '/client/test.php' => $pluginDir . '/frontend/client/test.php'
        );
    }

    /**
     * Get status of item with errors
     *
     * @return array
     */
    public function getItemWithErrorStatus()
    {
        $stmt = exec_query(
            "
                SELECT migrator_id AS item_id, migrator_status AS status, domain_name AS item_name,
                    'migrator' AS `table`, 'migrator_status' AS field
                FROM migrator WHERE migrator_status NOT IN(?, ?, ?, ?, ?, ?, ?)
            ",
            array('ok', 'disabled', 'toadd', 'tochange', 'toenable', 'todisable', 'todelete')
        );

        if ($stmt->rowCount()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return array();
    }

    /**
     * Set status of the given plugin item to 'tochange'
     *
     * @param string $table Table name
     * @param string $field Status field name
     * @param int $itemId Migrator item unique identifier
     * @return void
     */
    public function changeItemStatus($table, $field, $itemId)
    {
        if ($table == 'migrator_sgw' && $field == 'migrator_status') {
            exec_query('UPDATE migrator_sgw SET status = ? WHERE migrator_id = ?', array('tochange', $itemId));
        }
    }

    /**
     * Return count of request in progress
     *
     * @return int
     */
    public function getCountRequests()
    {
        $stmt = exec_query(
            'SELECT COUNT(migrator_id) AS cnt FROM migrator_sgw WHERE status IN (?, ?, ?, ?, ?)',
            array('toadd', 'tochange', 'toenable', 'todisable', 'todelete')
        );

        $row = $stmt->fetchRow(PDO::FETCH_ASSOC);

        return $row['cnt'];
    }

    /**
     * Does the given customer has Migrator feature activated?
     *
     * @param int $customerId Customer unique identifier
     * @return bool
     */
    public static function customerHasMigrator($customerId)
    {
        static $hasAccess = NULL;

        return true; // TODO Currently everyone has Migrator available on their account.

        // if (NULL === $hasAccess) {
        //     $stmt = exec_query(
        //         '
        //             SELECT COUNT(admin_id) as cnt FROM migrator INNER JOIN admin USING(admin_id)
        //             WHERE admin_id = ? AND admin_status = ?
        //         ',
        //         array($customerId, 'ok')
        //     );

        //     $row = $stmt->fetchRow(PDO::FETCH_ASSOC);
        //     $hasAccess = (bool)$row['cnt'];
        // }

        // return $hasAccess;
    }

    /**
     * Inject Migrator links into the navigation object
     *
     * @param string $level UI level
     */
    protected function setupNavigation($level)
    {
        if (iMSCP_Registry::isRegistered('navigation')) {
            /** @var Zend_Navigation $navigation */
            $navigation = iMSCP_Registry::get('navigation');

            if ($level == 'reseller') {
                if (($page = $navigation->findOneBy('uri', '/reseller/users.php'))) {
                    $page->addPage(array(
                        'label'              => tr('Migrator'),
                        'uri'                => '/reseller/migrator.php',
                        'title_class'        => 'users',
                        'privilege_callback' => array(
                            'name' => 'resellerHasCustomers'
                        ),
                        'pages'              => array(
                            'migrate_user'   => array(
                                'label'        => tr('Migrate User'),
                                'uri'          => '/reseller/migrate_user.php',
                                'title_class'  => '',
                                'visible'      => false
                            )
                        )
                    ));
                }
            }
        }
    }

    /**
     * Clear translations if any
     *
     * @return void
     */
    protected function clearTranslations()
    {
        /** @var Zend_Translate $translator */
        $translator = iMSCP_Registry::get('translator');

        if ($translator->hasCache()) {
            $translator->clearCache($this->getName());
        }
    }
}
