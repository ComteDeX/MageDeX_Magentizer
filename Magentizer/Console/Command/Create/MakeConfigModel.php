<?php

namespace MageDeX\Magentizer\Console\Command\Create;

use Magento\Framework\Filesystem\DriverPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Module\Dir;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Magento\Framework\App\Area;
use Magento\Framework\Filesystem\DriverInterface;
use DOMDocument;

class MakeConfigModel extends Command
{
    private const MODULE_SELF_NAME = 'MageDeX_Magentizer';
    private const MODULE_TEMPLATES_FILE = 'Templates/modelConfigClass.phptpl';
    private const COMMAND_MAGENTIZER_CREATE_CONTROLLER = 'magentizer:create:config-model';

    private const VENDOR_NAME_ARGUMENT = "vendor's name";
    private const MODULE_NAME_ARGUMENT = "module's name";
    private const SYSTEM_XML = "system.xml";
    private const MODEL_CONFIG_PATH = "Model/Config";
    private const MODEL_CONFIG_FILENAME = "Config.php";

    private const SYSTEM_CONFIG_PATH = "config_path";

    public const RED="\033[31m";
    public const YELLOW="\033[33m";
    public const GREEN="\033[32m";
    public const BLUE="\033[34m";
    public const WHITE="\033[37m";
    public const COLOR_NONE="\e[0m";

    protected DirectoryList     $directoryList;
    protected Dir               $directory;
    protected WriteFactory      $write;
    protected DriverInterface   $driver;
    protected DOMDocument       $domDocument;

    protected $rootPath;
    private $moduleSelfPath;
    private $templatesPath;

    public function __construct(
        DirectoryList $directoryList,
        Dir $directory,
        WriteFactory $write,
        DriverInterface $driver,
        DOMDocument $domDocument,
        string $name = null
    ) {
        parent::__construct($name);
        $this->directoryList = $directoryList;
        $this->directory = $directory;
        $this->write = $write;
        $this->driver = $driver;
        $this->domDocument = $domDocument;
        $this->rootPath = $this->directoryList->getRoot();
        $this->moduleSelfPath = $this->directory->getDir(self::MODULE_SELF_NAME);
        $this->templatesPath = $this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_FILE;

    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $vendorName = $input->getArgument(self::VENDOR_NAME_ARGUMENT);
        $moduleName = $input->getArgument(self::MODULE_NAME_ARGUMENT);
;
        while (!$vendorName) {
            $output->writeln(self::GREEN ."What Vendor name for this new module?". self::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $vendorName = trim(fgets($handle));
        }

        while (!$moduleName) {
            $output->writeln(self::GREEN . "What Module name?" . self::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $moduleName = trim(fgets($handle));
        }

        $this->createModule($vendorName, $moduleName, $output);
//        if ($this->createModule($vendorName, $moduleName, $output)) {
//            $output->writeln("Please welcome " . $correctedVendorName . "_" . $moduleName . "!");
//            $output->writeln(self::BLUE . "Don’t forget to execute ". self::COLOR_NONE . "bin/magento setup:upgrade" . self::BLUE . " to make it work properly.");
//        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_MAGENTIZER_CREATE_CONTROLLER);
        $this->setDescription("Create a Config Model Class for system.xml");
        $this->setDefinition([
            new InputArgument(self::VENDOR_NAME_ARGUMENT, InputArgument::OPTIONAL, "Vendor's Name"),
            new InputArgument(self::MODULE_NAME_ARGUMENT, InputArgument::OPTIONAL, "Module's Name"),
        ]);
        parent::configure();
    }

    /**
     * @param string $vendorName
     * @param string $moduleName
     * @return bool
     */
    private function createModule(
        string $vendorName,
        string $moduleName,
        OutputInterface $output
    ) : bool {

        $vendorPath = $this->rootPath . '/app/code/' . $vendorName;
        $fullPath = implode('/', [
            $vendorPath,
            $moduleName,
            DirectoryList::CONFIG,
            Area::AREA_ADMINHTML,
            self::SYSTEM_XML
        ]);

        if(!$this->driver->isFile($fullPath)) {
            echo "does not exist\n";
            return false;
        }

        $systemXML = json_decode(json_encode(simplexml_load_string($this->driver->fileGetContents($fullPath))), true);

        $configPaths = [];
            foreach ($systemXML['system']['section'] as $sectionKey => $section) {
                if ($sectionKey !== "group") { continue; }
                foreach ($section as $groupsKey => $groups) {
                    unset($groups['label']);
                    foreach ($groups as $groupKey => $group) {
                        if ($groupKey !== "field") { continue; }
                        foreach ($group as $fieldKey => $field) {
                            $key = isset($field["config_path"]) ? $field["config_path"] : implode('/',[
                                $systemXML['system']['section']["@attributes"]['id'],
                                $systemXML['system']['section']['group'][$groupsKey]["@attributes"]['id'],
                                $field["@attributes"]['id']
                            ]);

                            // 1 for bool, 0 for text and anything else
                            $configPaths[$key] = (isset($field["source_model"]) && $field["source_model"] === 'Magento\Config\Model\Config\Source\Yesno') ? 1 : 0;
                        }
                    }
                }
            }

            if (!count($configPaths)) {
                echo "File does not seems to be correct.\n";
                return false;
            }

            $this->createConfigClass($configPaths, $vendorPath, $vendorName, $moduleName);
// etc/module.xml
//            $moduleXml = [
//                'filename' => 'module.xml',
/*                'content' => '<?xml version="1.0"?>' . "\n" .*/
//                             '<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'. "\n" .
//                             '        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">'. "\n" .
//                             '    <module name="'. $vendorName . '_' . $moduleName . '" setup_version="0.0.1"/>'. "\n" .
//                             '</config>' . "\n"
//            ];
//
//            $this->createFile($fullPath . '/' . \Magento\Framework\Module\Dir::MODULE_ETC_DIR, $moduleXml);
//            $composerJson = [
//                'filename' => 'composer.json',
//                'content' => '{'."\n".
//                             '    "name": "'.strtolower($vendorName).'/'. strtolower($moduleName).",',"."\n".
//                             '    "description": "",'."\n".
//                             '    "type": "magento2-module",'."\n".
//                             '    "version": "0.1.0",'."\n".
//                             '    "type": "magento2-module",'."\n".
//                             '    "license": ['."\n".
//                             ($license) ? 'MIT' : '' ."\n".
//                             '    ],'."\n".
//                             '    "autoload": {'."\n".
//                             '        "files": ['."\n".
//                             '            "registration.php"'."\n".
//                             '        ],'."\n".
//                             '        "psr-4": {'."\n".
//                             '            "'. $vendorName .'\\'.$moduleName.'\\": ""'."\n".
//                             '        }'."\n".
//                             '    },'."\n".
//                             '    "extra": {'."\n".
//                             '        "map": ['."\n".
//                             '            ['."\n".
//                             '                "*",'."\n".
//                             '                "'. $vendorName .'/'.$moduleName.'"'."\n".
//                             '            ]'."\n".
//                             '        ]'."\n".
//                             '    }'."\n".
//                             '}'
//            ];
//
//            $this->createFile($fullPath, $composerJson);
//
//            if ($authorName && $authorName !== '') {
//                $copyright = ' * Copyright © '. $authorName .'. All rights reserved.'."\n" .
//                             ' * See COPYING.txt for license details.'."\n";
//            }
//            $registrationPhp = [
//                'filename' => 'registration.php',
//                'content' => '<?php'."\n".
//                             '/**'."\n".
//                             $copyright.
//                             ' */'."\n".
//                             ''."\n".
//                             'use Magento\Framework\Component\ComponentRegistrar;'."\n".
//                             ''."\n".
//                             'ComponentRegistrar::register('."\n".
//                             '    ComponentRegistrar::MODULE,'."\n".
//                             '    \''. $vendorName .'_'. $moduleName.'\','."\n".
//                             '    __DIR__'."\n".
//                             ');'."\n".
//                             ''."\n"
//            ];
//
//            $this->createFile($fullPath, $registrationPhp);
//
//            if ($license) {
//                $licenseMd = [
//                    'filename' => 'license.md',
//                    'content' => "The MIT License\n".
//                    "\n".
//                    "Copyright (c) " . date("Y") . " " . $authorName . "\n".
//                    "\n".
//                    "Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the \"Software\"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:\n".
//                    "\n".
//                    "The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.\n".
//                    "\n".
//                    "THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.\n".
//                    ""
//                ];
//                $this->createFile($fullPath, $licenseMd);
//            }
//
//            $readMeMd = [
//                'filename' => 'README.md',
//                'content' => "# ". $moduleName."\n".
//                             "\n".
//                             "## Introductio\n".
//                             $moduleName . " is a module for Magento 2. Enjoy !\n"
//            ];
//
//            $this->createFile($fullPath, $readMeMd);
        return true;
    }

    private function createConfigClass(array $configPaths, string $vendorPath,  string $vendorName, string $moduleName) : bool
    {
        $properties = "\n";

        foreach ($configPaths as $configPath => $isBool) {
            $properties .= "    public const " .mb_strtoupper(str_replace('/','_',$configPath)) . " ='". $configPath ."';\n";
        }
        $filePath = implode("/", [
            $vendorPath,
            $moduleName,
            self::MODEL_CONFIG_PATH,
            ''
        ]);
        $template = $this->getTemplate($vendorName, $moduleName, $properties);

        try {
            $newFile = $this->write->create($filePath,DriverPool::FILE);
            $newFile->writeFile(self::MODEL_CONFIG_FILENAME, $template);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        return true;
    }

    private function getTemplate(string $vendorName, string $moduleName,  $properties) : string
    {
        $template = $this->driver->fileGetContents($this->templatesPath);
        $template = str_replace(['{{namespace}}', '{{properties}}'],
            [$vendorName . "\\" . $moduleName, $properties], $template);

        return $template;
    }
}
