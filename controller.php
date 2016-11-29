<?php
namespace Concrete\Package\ToessLabExportMembers;

use AssetList;
use Package;
use Page;
use SinglePage;
use Core;
use Asset;
use Config;
use Concrete\Package\ToessLabExportMembers\Help\HelpServiceProvider;

class Controller extends Package {

    /**
     * @var string
     */
    protected $pkgHandle = 'toess_lab_export_members';
    protected $appVersionRequired = '5.7.5.9';
    protected $pkgVersion = '0.9.9';
    protected $pkgAutoloaderMapCoreExtensions = true;

    public function getPackageDescription()
    {
        return t("Export/Import Site members to CSV (Excel).");
    }

    public function getPackageName()
    {
        return t("Export/Import Site members to CSV");
    }


    public function install()
    {
        $pkg = parent::install();
        $this->installOrUpgrade($pkg);
        $this->setCsvSettings();
    }

    public function on_start()
    {
        $app = Core::make('app');
        $pkg = $this;
        $al = AssetList::getInstance();
        $al->register(
            'css', 'toess_lab_export_members', 'css/toesslab-export.css', array('position' => Asset::ASSET_POSITION_HEADER), $pkg
        );
        $al->register(
            'javascript', 'toess_lab_export_members', 'js/toesslab-export.js', array('position' => Asset::ASSET_POSITION_FOOTER), $pkg
        );
        $al->register(
            'css', 'toess_lab_import_members', 'css/toesslab-import.css', array('position' => Asset::ASSET_POSITION_HEADER), $pkg
        );
        $al->register(
            'javascript', 'toess_lab_import_members', 'js/toesslab-import.js', array('position' => Asset::ASSET_POSITION_FOOTER), $pkg
        );
        $al->register(
            'css', 'jquery-ui', '/js/libs/jquery-ui/jquery-ui.css', array('position' => Asset::ASSET_POSITION_HEADER), $pkg
        );
        $al->register(
            'javascript', 'jquery-ui', '/js/libs/jquery-ui/jquery-ui.min.js', array('position' => Asset::ASSET_POSITION_FOOTER), $pkg
        );
        $al->register(
            'css', 'bootstrapswitch', 'js/libs/bootstrap_switch/bootstrap-switch.min.css', array('position' => Asset::ASSET_POSITION_HEADER), $pkg
        );
        $al->register(
            'javascript', 'bootstrapswitch', 'js/libs/bootstrap_switch/bootstrap-switch.min.js', array('position' => Asset::ASSET_POSITION_FOOTER), $pkg
        );
        $al->registerGroup('toess_lab_export_members', array(
            array('javascript', 'toess_lab_export_members'),
            array('css', 'toess_lab_export_members'),
        ));
        $al->registerGroup('toess_lab_import_members', array(
            array('javascript', 'toess_lab_import_members'),
            array('css', 'toess_lab_import_members'),
        ));
        $al->registerGroup('bootstrapswitch', array(
            array('css', 'bootstrapswitch'),
            array('javascript', 'bootstrapswitch'),
        ));
        $al->registerGroup('jquery-ui', array(
            array('css', 'jquery-ui'),
            array('javascript', 'jquery-ui'),
        ));
        $provider = new HelpServiceProvider($app);
        $provider->register();
    }

    private function installOrUpgrade($pkg)
    {
        $this->getOrAddSinglePage($pkg, 'dashboard/users/toess_lab_export_members', t('toesslab - Export Members'));
        $this->getOrAddSinglePage($pkg, 'dashboard/users/toess_lab_import_members', t('toesslab - Import Members'));
    }

    private function setCsvSettings()
    {
        $csvSetttings =  array(
            'csv-delimiter' => ';',
            'csv-enclosure' => '"',
            'csv-escape' => '\\',
            'csv-filename' => 'toesslab'
        );
        foreach($csvSetttings as $k => $c){
            Config::save('toess_lab_export_members.csv-settings.' . $k, $c);
        }

    }

    private function getOrAddSinglePage($pkg, $cPath, $cName = '', $cDescription = '') {

        $sp = SinglePage::add($cPath, $pkg);

        if (is_null($sp)) {
            $sp = Page::getByPath($cPath);
        } else {
            $data = array();
            if (!empty($cName)) {
                $data['cName'] = $cName;
            }
            if (!empty($cDescription)) {
                $data['cDescription'] = $cDescription;
            }

            if (!empty($data)) {
                $sp->update($data);
            }
        }

        return $sp;
    }


}
