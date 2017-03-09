<?php
namespace Concrete\Package\ToessLabExportMembers\ImportExport;
use Concrete\Core\Attribute\Set;
use Concrete\Core\User\User;
use Concrete\Core\User\UserInfo;
use Concrete\Package\ToessLabExportMembers\Helper\Tools;
use Core;
use Config;
use UserAttributeKey;
use \Concrete\Core\Attribute\Type as AttributeType;
use \Concrete\Core\Attribute\Key\Category as AttributeKeyCategory;

/**
 * Created by https://toesslab.ch/
 * User: Daniel Gasser
 * Date: 28.10.16
 * Time: 11:45
 * Project: c57
 * Description:
 * File: ToesslabExportToCSV.php
 */
class ImportFromCSV
{

    protected $csvContent;
    protected $cSVFileObject;
    protected $cSVFileObjectVersion;
    protected $csvMetaData;
    protected $csvPointer;
    protected $csvSettings;
    protected $db;
    protected $columns;

    function __construct($csvFileObject)
    {
        $this->cSVFileObject = $csvFileObject;
        $this->cSVFileObjectVersion = $this->cSVFileObject->getApprovedVersion();
        $this->csvContent = $this->cSVFileObjectVersion->getFileContents();
        $this->csvPointer = fopen(DIR_BASE . $this->cSVFileObject->getRelativePath(), 'r');
        $this->csvMetaData = explode(Config::get('toess_lab_export_members.csv-settings.csv-delimiter'), fgets($this->csvPointer));
        $this->csvSettings = Config::get('toess_lab_export_members.csv-settings');
        $this->db = Core::make('database');
        $this->columns = Tools::getUserAttributeColumns();
    }

    public function checkExistentAttributes()
    {
        $csvFile = file(DIR_BASE . $this->cSVFileObject->getRelativePath());
        $data = array();
        foreach ($csvFile as $line) {
            $data[] = str_getcsv($line);
        }
        $matches = array_filter($data, function($a)   {
            return preg_grep('/\*{4}/', $a);
        });
        for($i = (array_keys($matches)[0] + 1); $i < sizeof($data); ++$i){
            $this->addUserAttribute(Core::make('helper/json')->decode($data[$i][0]));
        }
    }

    public function checkExistentUsers()
    {
        $csvFile = file(DIR_BASE . $this->cSVFileObject->getRelativePath());
        $app = Core::make('app');
        $ui = $app->make('Concrete\Core\User\UserInfo');
        $data = $csvFile;
        unset($data[0]);
        $dh = Core::make('helper/date');
        foreach($data as $k => $d){
            $r = explode(Config::get('toess_lab_export_members.csv-settings.csv-delimiter'), $d);
            for ($i = 0; $i < sizeof($this->columns['baseAttributes']); $i++){
                $e[$this->columns['baseAttributes'][$i]['akHandle']] = trim($r[$i], '"');
            }
            if(!isset($e['uEmail'])) {
                break;
            }
            $u = $ui->getByEmail($e['uEmail']);
            unset($e[0]);
            if(!is_object($u) && strlen($e['uEmail']) > 0){
                print_r('nope');
                print_r($e['uDateAdded']);
                print_r($e);
                $this->createUser($e);
            } else {
                print_r('yepp');
                print_r($e);
                print "<hr>";
            }
        }
        dd('.');
    }

    private function addUserAttribute($args)
    {
        $args = (array)$args;
        $attrCategory = AttributeKeyCategory::getByHandle('user');
        $attr = UserAttributeKey::getByHandle($args['akHandle']);
        if(!is_object($attr)) {
            $attrType = AttributeType::getByHandle($args['atHandle']);
            $uak = UserAttributeKey::add($attrType, $args);
            if ($args['setID'] != null) {
                $atS = Set::getByID($args['setID']);
                if(!is_object($atS)) {
                    $attrSet = $attrCategory->addSet($atS->getAttributeSetHandle(), $atS->getAttributeSetDisplayName());
                    $uak->setAttributeSet($attrSet);
                } else {
                    $uak->setAttributeSet($atS);
                }
            }
        }
    }

    private function createUser($args)
    {
        try {
            $x = $this->db->executeQuery('insert into Users (
              uName,
              uEmail,
              uPassword,
              uIsActive,
              uIsValidated,
              uIsFullRecord,
              uDateAdded,
              uLastPasswordChange,
              uHasAvatar,
              uLastOnline,
              uLastLogin,
              uLastIP,
              uPreviousLogin,
              uNumLogins,
              uLastAuthTypeID,
              uTimezone,
              uDefaultLanguage
              ) values (
              :uName,
              :uEmail,
              :uPassword,
              :uIsActive,
              :uIsValidated,
              :uIsFullRecord,
              :uDateAdded,
              :uLastPasswordChange,
              :uHasAvatar,
              :uLastOnline,
              :uLastLogin,
              :uLastIP,
              :uPreviousLogin,
              :uNumLogins,
              :uLastAuthTypeID,
              :uTimezone,
              :uDefaultLanguage
              )', $args);
        } catch (Exception $e) {
            throw $e->getMessage();
        }
        return $x;
    }

    private function checkUserGroups()
    {

    }
}
