<?php

namespace MageDeX\Magentizer\Console\Command\Create;

use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteFactory;

class MakeSystemConfig extends Command
{
    const COMMAND_MAGENTIZER_CREATE_SYSTEM = 'magentizer:create:system';
    const SYSTEM_TAB = 'tab';
    const SYSTEM_SECTION = 'section';
    const SYSTEM_GROUP = 'group';
    const AUTHOR_NAME_ARGUMENT = "author's name";

    const RED="\033[31m";
    const YELLOW="\033[33m";
    const GREEN="\033[32m";
    const BLUE="\033[34m";
    const WHITE="\033[37m";
    const COLOR_NONE="\e[0m";

    /**
     * @var Reader
     */
    protected $moduleDirectory;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var WriteFactory
     */
    protected $write;

    protected $rootPath;

    public function __construct(
        Reader $moduleDirectory,
        DirectoryList $directoryList,
        WriteFactory $write,
        string $name = null
    ) {
        parent::__construct($name);
        $this->directoryList = $directoryList;
        $this->moduleDirectory = $moduleDirectory;
        $this->write = $write;
        $this->rootPath = $this->directoryList->getRoot();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $tabName = $input->getArgument(self::SYSTEM_TAB);
        $sectionName = $input->getArgument(self::SYSTEM_SECTION);
        $groupName = $input->getArgument(self::SYSTEM_GROUP);

        while (!$tabName) {
            $output->writeln("What is your tab name for this new system.xml?");
            $handle = fopen("php://stdin", "r");
            $tabName = trim(fgets($handle));
        }

        $correctedVendorName = $this->cleanModuleName($tabName);
        if ($correctedVendorName !== $tabName) {
            $output->writeln("Vendor's name has been modified this way to comply with PSR: " . $correctedVendorName);
        }

        while (!$sectionName) {
            $output->writeln("What Module name?");
            $handle = fopen("php://stdin", "r");
            $sectionName = trim(fgets($handle));
        }

        $correctedModuleName = $this->cleanModuleName($sectionName);
        if ($correctedModuleName !== $sectionName) {
            $output->writeln("Vendor's name has been modified this way to comply with PSR: " . $correctedModuleName);
        }

        if (!$groupName) {
            $output->writeln("An author name for the copyright?");
            $handle = fopen("php://stdin", "r");
            $groupName = trim(fgets($handle));
        }

        $output->writeln("Please welcome " . $correctedVendorName . "_" . $correctedModuleName . "!");
        $output->writeln("EEEEEEEEEEEEEEEEEEEEEEE");
        $output->writeln("Please welcome " . $correctedVendorName . "_" . $correctedModuleName . "!");

        if ($this->createSystemXmlFile($authorName, $correctedVendorName, $correctedModuleName, $license, $output)) {
            $output->writeln("Please welcome " . $correctedVendorName . "_" . $correctedModuleName . "!");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_MAGENTIZER_CREATE_SYSTEM);
        $this->setDescription("Quickly create a system.xml, config.xml and config model class");
        $this->setDefinition([
            new InputArgument(self::AUTHOR_NAME_ARGUMENT, InputArgument::OPTIONAL, "Author's Name for copyright data"),
            new InputArgument(self::SYSTEM_TAB, InputArgument::OPTIONAL, "Tab's Name"),
            new InputArgument(self::SYSTEM_SECTION, InputArgument::OPTIONAL, "Section's Name"),
            new InputArgument(self::SYSTEM_GROUP, InputArgument::OPTIONAL, "Group's name"),
        ]);
        parent::configure();
    }

    /**
     * Cleans Module Name
     *
     * @param string $value
     * @return string
     */
    private function cleanModuleName(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['-', '_', '.', ':', '!'], ' ', $value);
        $value = preg_replace('/[^a-zA-Z]/', ' ', $value);
        $value = ucwords($value);
        $value = str_replace(' ', '', $value);
        $value = ucfirst($value);

        return $value;
    }

    /**
     * @param string $vendorName
     * @param string $sectionName
     * @return bool
     */
    private function createSystemXmlFile(
        string $vendorName,
        string $sectionName,
        string $authorName,
        bool $license,
        OutputInterface $output
    ) : bool {

        $vendorPath = $this->rootPath . '/app/code/' . $vendorName;
        $fullPath = $vendorPath . '/' . $sectionName;

        if($this->isSystemXmlFileAlreadyExists(
            $vendorPath,
            $fullPath,
            $output
        )) {
            // etc/module.xml
            $moduleXml = [
                'filename' => 'module.xml',
                'content' => '<!--' . "\n" .
                             '/**' . "\n" .
                             ' * Copyright © '. $authorName .', All rights reserved.' . "\n" .
                             ' * See LICENSE bundled with this library for license details.' . "\n" .
                             ' */' . "\n" .
                             '-->' . "\n" .
                             '<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'. "\n" .
                             '        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Config:etc/system_file.xsd">'. "\n" .
                             '    <module name="'. $vendorName . '_' . $sectionName . '" setup_version="0.0.1"/>'. "\n" .
                             '</config>' . "\n"
            ];

            $this->createFile($fullPath . '/' . \Magento\Framework\Module\Dir::MODULE_ETC_DIR, $moduleXml);
            $composerJson = [
                'filename' => 'composer.json',
                'content' => '{'."\n".
                             '    "name": "'.strtolower($vendorName).'/'. strtolower($sectionName).",',"."\n".
                             '    "description": "",'."\n".
                             '    "type": "magento2-module",'."\n".
                             '    "version": "0.1.0",'."\n".
                             '    "license": ['."\n".
                             ''."\n".
                             '    ],'."\n".
                             '    "autoload": {'."\n".
                             '        "files": ['."\n".
                             '            "registration.php"'."\n".
                             '        ],'."\n".
                             '        "psr-4": {'."\n".
                             '            "'. $vendorName .'\\'.$sectionName.'\\": ""'."\n".
                             '        }'."\n".
                             '    },'."\n".
                             '    "extra": {'."\n".
                             '        "map": ['."\n".
                             '            ['."\n".
                             '                "*",'."\n".
                             '                "'. $vendorName .'/'.$sectionName.'"'."\n".
                             '            ]'."\n".
                             '        ]'."\n".
                             '    }'."\n".
                             '}'
            ];

            $this->createFile($fullPath, $composerJson);

            if ($authorName && $authorName !== '') {
                $copyright = ' * Copyright © '. $authorName .'. All rights reserved.'."\n" .
                             ' * See COPYING.txt for license details.'."\n";
            }
            $registrationPhp = [
                'filename' => 'registration.php',
                'content' => '<?php'."\n".
                             '/**'."\n".
                             $copyright.
                             ' */'."\n".
                             ''."\n".
                             'use Magento\Framework\Component\ComponentRegistrar;'."\n".
                             ''."\n".
                             'ComponentRegistrar::register('."\n".
                             '    ComponentRegistrar::MODULE,'."\n".
                             '    \''. $vendorName .'_'. $sectionName.'\','."\n".
                             '    __DIR__'."\n".
                             ');'."\n".
                             ''."\n"
            ];

            $this->createFile($fullPath, $registrationPhp);

            if ($license) {
                $licenseMd = [
                    'filename' => 'license.md',
                    'content' => "The MIT License\n".
                    "\n".
                    "Copyright (c) " . date("Y") . " " . $authorName . "\n".
                    "\n".
                    "Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the \"Software\"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:\n".
                    "\n".
                    "The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.\n".
                    "\n".
                    "THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.\n".
                    ""
                ];
                $this->createFile($fullPath, $licenseMd);
            }

            $readMeMd = [
                'filename' => 'README.md',
                'content' => "# ". $sectionName."\n".
                             "\n".
                             "## Introductio\n".
                             $sectionName . " is a module for Magento 2. Enjoy !\n"
            ];

            $this->createFile($fullPath, $readMeMd);

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $vendorPath
     * @param string $modulePath
     * @return bool
     */
    private function isSystemXmlFileAlreadyExists(
        string $vendorPath,
        string $fullPath,
        OutputInterface $output
    ) : bool {
        //Test if vendor's directory exists
        if (!file_exists($vendorPath)) {
            $output->writeln($vendorPath);
            mkdir($vendorPath);
        }

        //Tests if module's directory exists
        if (!file_exists($fullPath)) {
            mkdir($fullPath);
            mkdir($fullPath . '/' . \Magento\Framework\Module\Dir::MODULE_ETC_DIR);
        } else {
            $output->writeln('A module with the same name already exists');
            return false;
        }
        return true;
    }

    private function createFile(string $path, array $data) : bool
    {
        try {
            $newFile = $this->write->create($path ,DriverPool::FILE);
            $newFile->writeFile($data['filename'], $data['content']);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        return true;
    }
}
