<?php

/**
 * Copyright (C) 2009-2016 by the Barcode plugin Development Team.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

class PluginBarcodeProfile extends Profile
{
    public static string $rightname = "config";

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getID() > 0
                && $item->fields['interface'] == 'central') {
            return self::createTabEntry(__('Barcode', 'barcode'));
        }
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $pfProfile = new self();
        $pfProfile->showForm($item->getID());
        return true;
    }

    /**
    * Show profile form
    *
    * @param $items_id integer id of the profile
    * @param $target value url of target
    *
    * @return nothing
    **/
    public function showForm($ID, array $options = [])
    {

        echo "<div class='firstbloc'>";
        if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]))) {
            $profile = new Profile();
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        $profile = new Profile();
        $profile->getFromDB($ID);

        $rights = $this->getAllRights();
        $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Barcode', 'barcode')
        ]);
        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $ID]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }
        echo "</div>";
    }

    public static function uninstallProfile()
    {
        $pfProfile = new self();
        $a_rights = $pfProfile->getAllRights();
        foreach ($a_rights as $data) {
            ProfileRight::deleteProfileRights([$data['field']]);
        }
    }

    public function getAllRights()
    {
        $a_rights = [
            ['rights'    => [UPDATE  => __('Update')],
                'label'     => __('Manage configuration', 'barcode'),
                'field'     => 'plugin_barcode_config'
            ],
            ['rights'    => [CREATE  => __('Create')],
                'label'     => __('Generation of barcode', 'barcode'),
                'field'     => 'plugin_barcode_barcode'
            ]
        ];
        return $a_rights;
    }

    public static function addDefaultProfileInfos($profiles_id, $rights)
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (!countElementsInTable(
                'glpi_profilerights',
                ['profiles_id' => $profiles_id, 'name' => $right]
            )) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    /**
     * @param $profiles_id  integer
     */
    public static function createFirstAccess($profiles_id)
    {
        include_once(Plugin::getPhpDir('barcode') . "/inc/profile.class.php");
        $profile = new self();
        foreach ($profile->getAllRights() as $right) {
            self::addDefaultProfileInfos(
                $profiles_id,
                [$right['field'] => ALLSTANDARDRIGHT]
            );
        }
    }

    public static function removeRights()
    {
        $profile = new self();
        foreach ($profile->getAllRights() as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
            ProfileRight::deleteProfileRights([$right['field']]);
        }
    }

    /**
    * Init profiles during installation :
    * - add rights in profile table for the current user's profile
    * - current profile has all rights on the plugin
    */
    public static function initProfile()
    {
        $pfProfile = new self();
        $profile   = new Profile();
        $a_rights  = $pfProfile->getAllRights();

        foreach ($a_rights as $data) {
            if (!countElementsInTable("glpi_profilerights", ['name' => $data['field']])) {
                ProfileRight::addProfileRights([$data['field']]);
                $_SESSION['glpiactiveprofile'][$data['field']] = 0;
            }
        }

        // Add all rights to current profile of the user
        if (isset($_SESSION['glpiactiveprofile'])) {
            $dataprofile       = [];
            $dataprofile['id'] = $_SESSION['glpiactiveprofile']['id'];
            $profile->getFromDB($_SESSION['glpiactiveprofile']['id']);
            foreach ($a_rights as $info) {
                if (is_array($info)
                    && ((!empty($info['itemtype'])) || (!empty($info['rights'])))
                      && (!empty($info['label'])) && (!empty($info['field']))) {

                    if (isset($info['rights'])) {
                        $rights = $info['rights'];
                    } else {
                        $rights = $profile->getRightsFor($info['itemtype']);
                    }
                    foreach (array_keys($rights) as $right) {
                        $dataprofile['_' . $info['field']][$right] = 1;
                        $_SESSION['glpiactiveprofile'][$data['field']] = $right;
                    }
                }
            }
            $profile->update($dataprofile);
        }
    }
}
