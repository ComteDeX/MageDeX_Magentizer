<?php

declare (strict_types=1);

namespace MageDeX\Magentizer\Console\Command\Create;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use MageDeX\Magentizer\Console\FinalClasses\SharedConstants;

class CreateNewModule extends Command
{
    public const COMMAND_MAGENTIZER_CREATE_CONTROLLER = 'magentizer:create:module';
    public const APP_CODE = '/app/code/';

    private const MODULE_TEMPLATES_COMPOSER  = 'Templates/CreateModule/composer.json.tpl';
    private const MODULE_TEMPLATES_LICENSE = 'Templates/CreateModule/LICENSE.tpl';
    private const MODULE_TEMPLATES_README = 'Templates/CreateModule/readme.md.tpl';
    private const MODULE_TEMPLATES_REGISTRATION = 'Templates/CreateModule/registration.php.tpl';

    const MODULE_XML = 'module.xml';

    protected Reader            $moduleDirectory;
    protected DirectoryList     $directoryList;
    protected Dir               $directory;
    protected DriverInterface   $driver;
    protected WriteFactory      $write;


    protected $rootPath;
    private   $templatesModuleTemplatesComposer;
    private   $templatesModuleTemplatesLicense;
    private   $templatesModuleTemplatesReadme;
    private   $templatesModuleTemplatesRegistration;

    public function __construct(
        Reader $moduleDirectory,
        DirectoryList $directoryList,
        Dir $directory,
        DriverInterface $driver,
        WriteFactory $write,
        string $name = null
    ) {
        parent::__construct($name);
        $this->directoryList = $directoryList;
        $this->moduleDirectory = $moduleDirectory;
        $this->directory = $directory;
        $this->write = $write;
        $this->driver = $driver;
        $this->rootPath = $this->directoryList->getRoot();
        $this->moduleSelfPath = $this->directory->getDir(SharedConstants::MODULE_SELF_NAME);
        $this->templatesModuleTemplatesComposer = $this->driver->fileGetContents($this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_COMPOSER);;
        $this->templatesModuleTemplatesLicense = $this->driver->fileGetContents($this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_LICENSE);;
        $this->templatesModuleTemplatesReadme = $this->driver->fileGetContents($this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_README);;
        $this->templatesModuleTemplatesRegistration = $this->driver->fileGetContents($this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_REGISTRATION);;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $vendorName = $input->getArgument(SharedConstants::VENDOR_NAME_ARGUMENT);
        $moduleName = $input->getArgument(SharedConstants::MODULE_NAME_ARGUMENT);
        $authorName = $input->getArgument(SharedConstants::AUTHOR_NAME_ARGUMENT);

        while (!$vendorName) {
            $output->writeln(SharedConstants::GREEN ."What Vendor name for this new module?". SharedConstants::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $vendorName = trim(fgets($handle));
        }

        $correctedVendorName = $this->cleanModuleName($vendorName);
        if ($correctedVendorName !== $vendorName) {
            $output->writeln(SharedConstants::RED . "Vendor's name has been modified this way to comply with PSR: ". SharedConstants::COLOR_NONE . $correctedVendorName);
        }

        while (!$moduleName) {
            $output->writeln(SharedConstants::GREEN . "What Module name?" . SharedConstants::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $moduleName = trim(fgets($handle));
        }

        $correctedModuleName = $this->cleanModuleName($moduleName);
        if ($correctedModuleName !== $moduleName) {
            $output->writeln(SharedConstants::RED . "Vendor's name has been modified this way to comply with PSR: " . $correctedModuleName . SharedConstants::COLOR_NONE);
        }

        $vendorPath = $this->rootPath . self::APP_CODE . $vendorName;
        $fullPath = $vendorPath . '/' . $moduleName;

        if($this->isModuleAlreadyExisting(
            $vendorName,
            $fullPath
        )) {
            $output->writeln(SharedConstants::RED . 'A module with the same name already exists!' . SharedConstants::COLOR_NONE);

            return false;
        }


        if (!$authorName) {
            $output->writeln(SharedConstants::GREEN . "An author name for the copyright?" . SharedConstants::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $authorName = trim(fgets($handle));
        }

        $license = false;
        if ($authorName !== '') {
            $output->writeln(SharedConstants::GREEN . "Add a MIT license file? [Y/n]" . SharedConstants::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $handle = trim(fgets($handle));
            switch (strtolower($handle)) {
                case "":
                case "y":
                case "yes":
                    $license = true;
                    break;
            }
        }

        if ($this->createModule($correctedVendorName, $correctedModuleName, $authorName, $license, $output)) {
            $output->writeln("Please welcome " . $correctedVendorName . "_" . $correctedModuleName . "!");
            $output->writeln(SharedConstants::BLUE . "Don’t forget to execute ". SharedConstants::COLOR_NONE . "bin/magento setup:upgrade" . self::BLUE . " to make it work properly.");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_MAGENTIZER_CREATE_CONTROLLER);
        $this->setDescription("Quickly create a module without messing with bothering stuffs");
        $this->setDefinition([
            new InputArgument(SharedConstants::VENDOR_NAME_ARGUMENT, InputArgument::OPTIONAL, "Vendor's Name"),
            new InputArgument(SharedConstants::MODULE_NAME_ARGUMENT, InputArgument::OPTIONAL, "Module's Name"),
            new InputArgument(SharedConstants::AUTHOR_NAME_ARGUMENT, InputArgument::OPTIONAL, "author's name"),
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
     * @param string $moduleName
     * @return bool
     */
    private function createModule(
        string $vendorName,
        string $moduleName,
        string $authorName,
        bool $license,
        OutputInterface $output
    ) : bool {

        $vendorPath = $this->rootPath . self::APP_CODE . $vendorName;
        $fullPath = $vendorPath . '/' . $moduleName;

        // etc/module.xml
            $moduleXml = [
                'filename' => self::MODULE_XML,
                'content' => '<?xml version="1.0"?>' . "\n" .
                             '<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'. "\n" .
                             '        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">'. "\n" .
                             '    <module name="'. $vendorName . '_' . $moduleName . '" setup_version="0.0.1"/>'. "\n" .
                             '</config>' . "\n"
            ];

            $this->createFile($fullPath . '/' . \Magento\Framework\Module\Dir::MODULE_ETC_DIR, $moduleXml);
            $composerJson = [
                'filename' => 'composer.json',
                'content' => '{'."\n".
                             '    "name": "'.strtolower($vendorName).'/'. strtolower($moduleName).",',"."\n".
                             '    "description": "",'."\n".
                             '    "type": "magento2-module",'."\n".
                             '    "version": "0.1.0",'."\n".
                             '    "type": "magento2-module",'."\n".
                             '    "license": ['."\n".
                             ($license) ? 'MIT' : '' ."\n".
                             '    ],'."\n".
                             '    "autoload": {'."\n".
                             '        "files": ['."\n".
                             '            "registration.php"'."\n".
                             '        ],'."\n".
                             '        "psr-4": {'."\n".
                             '            "'. $vendorName .'\\'.$moduleName.'\\": ""'."\n".
                             '        }'."\n".
                             '    },'."\n".
                             '    "extra": {'."\n".
                             '        "map": ['."\n".
                             '            ['."\n".
                             '                "*",'."\n".
                             '                "'. $vendorName .'/'.$moduleName.'"'."\n".
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
                             '    \''. $vendorName .'_'. $moduleName.'\','."\n".
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
                'content' => "# ". $moduleName."\n".
                             "\n".
                             "## Introductio\n".
                             $moduleName . " is a module for Magento 2. Enjoy !\n"
            ];

            $this->createFile($fullPath, $readMeMd);

            return true;
    }

    /**
     * @param string $vendorPath
     * @param string $modulePath
     * @return bool
     */
    private function isModuleAlreadyExisting(
        string $vendorPath,
        string $fullPath
    ) : bool {
        //Test if vendor's directory exists
        if (!file_exists($vendorPath)) {
            mkdir($vendorPath);
        }

        //Tests if module's directory exists
        if (!file_exists($fullPath)) {
            if (!mkdir($fullPath) && !is_dir($fullPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $fullPath));
            }
            if (!mkdir($concurrentDirectory = $fullPath . '/' . \Magento\Framework\Module\Dir::MODULE_ETC_DIR) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        } else {
            return true;
        }
        return false;
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
