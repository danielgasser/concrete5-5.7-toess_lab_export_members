<?php
namespace Concrete\Package\ToessLabExportMembers\Helper;
use \Concrete\Core\Attribute\Key\Category as AttributeKeyCategory;
use Concrete\Core\Attribute\Set;
use UserAttributeKey;
use Core;
use Concrete\Core\User\Group\Group;

/**
 * Created by https://toesslab.ch/
 * User: Daniel Gasser
 * Date: 30.10.16
 * Time: 10:06
 * Project: c57
 * Description:
 * File: Settings.php
 */
class Tools
{
    /**
     *
     * Gets all basic User Attributes
     *
     * @param bool $includeAllProps includes all public properties for import
     * @return array
     */
    public static function getUserAttributeColumns($basic = false)
    {
        $attr = UserAttributeKey::getList();
        $attributes = array();
        $baseAttributes = array();
        $i = 0;
        foreach ($attr as $k => $a) {
            $attributes[$i]['akHandle'] = $a->akHandle;
            $attributes[$i]['akName'] = $a->akName;
            $i++;
        }
        $baseAttributes[] = array(
            'akHandle' => 'uID',
            'akName' => t('User ID')
        );

        $baseAttributes[] = array(
            'akHandle' => 'uName',
            'akName' => t('Username')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uEmail',
            'akName' => t('Email Address')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uPassword',
            'akName' => t('Password')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uIsActive',
            'akName' => t('User active')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uIsValidated',
            'akName' => t('User validated')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uIsFullRecord',
            'akName' => t('Full Record')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uDateAdded',
            'akName' => t('Date created')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastPasswordChange',
            'akName' => t('Last Password Change')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uHasAvatar',
            'akName' => t('Profile Picture')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastOnline',
            'akName' => t('Last Online')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastLogin',
            'akName' => t('Last Login')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastIP',
            'akName' => t('Last IP Address')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uPreviousLogin',
            'akName' => t('Previous Login')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uNumLogins',
            'akName' => t('N° Logins')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uLastAuthTypeID',
            'akName' => t('Last Authorization Type')
        );
        $baseAttributes[] = array(
            'akHandle' => 'uTimezone',
            'akName' => t('Timezone')
        );
        $baseAttributes[] = array(
            'akHandle' =>  'uDefaultLanguage',
            'akName' => t('Language')
        );
        return array('attributes' => $attributes, 'baseAttributes' => $baseAttributes);
    }

    /**
     *
     * Sets user groups allowed to use
     *
     * @param array $key Not like $key
     * @return array
     *
     */
    public static function setPossibleUserGroup($key = array('Guest'))
    {
        $db = Core::make('database');
        $arr = array();
        $key_str = implode('" and gName not like "', $key);
        $query = 'select * from  Groups where gName not like "' . $key_str . '"';
        $res = $db->executeQuery($query);
        while ($row = $res->fetchRow()) {
            $arr[$row['gID']] = $row['gName'];
        }
        return $arr;
    }

    /**
     * @param $groupID
     * @param $adminInc
     * @param $checked
     * @return array
     */
    public static function changeUserGroup($groupID, $adminInc, $checked)
    {
        $db = self::getDBInstance();
        $allUsers = array();
        $superuser = array();
        $users = array();
        foreach ($groupID as $gi) {
            $userGroup = Group::getByID($gi);
            $users = $userGroup->getGroupMembers();
            if ($gi == '2') {
                $res = $db->executeQuery('select uID, uName, uEmail,uDateAdded, uNumLogins from Users');
                while ($row = $res->fetch(\PDO::FETCH_OBJ)) {
                    $row->isChecked = in_array($row->uID, $groupID);;
                    $users[] = $row;
                }
            }
        }
        if ($adminInc == 'true') {
            $res = $db->executeQuery('select uID, uName, uEmail, uDateAdded, uNumLogins from Users where uID = 1');
            while ($row = $res->fetch(\PDO::FETCH_OBJ)) {
                $row->isChecked = in_array($row->uID, $checked);
                $superuser = $row;
            }
            $users[] = $superuser;
        }
        $i = 0;
        foreach ($users as $u) {
            $allUsers[$i]['isChecked'] = in_array($u->uID, $checked);
            $allUsers[$i]['uID'] = $u->uID;
            $allUsers[$i]['uName'] = $u->uName;
            $allUsers[$i]['uEmail'] = $u->uEmail;
            $allUsers[$i]['uDateAdded'] = $u->uDateAdded;
            $allUsers[$i]['uNumLogins'] = $u->uNumLogins;
            $i++;
        }
        return $allUsers;
    }

    private function getDBInstance()
    {
        return Core::make('database');
    }

    /**
     * @param $filename
     * @param $mode
     * @return bool|resource
     */
    public static function createFilePointer($filename, $mode)
    {
        if (!is_writable($filename) && file_exists($filename)) {
            return false;
        }
        $csvDirName = dirname($filename);
        if (!is_dir($csvDirName)) {
            if (!mkdir($csvDirName)) {
                return false;
            }
        }
        $csvFile = fopen($filename, $mode);
        if (!$csvFile) {
            return false;
        }
        return $csvFile;
    }


}