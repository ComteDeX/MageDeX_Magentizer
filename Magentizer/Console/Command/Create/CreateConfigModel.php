<?php

namespace MageDeX\Magentizer\Console\Command\Create;

use DOMDocument;
use MageDeX\Magentizer\Console\FinalClasses\SharedConstants;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteFactory;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateConfigModel extends Command
{
    private const COMMAND_MAGENTIZER_CREATE_CONTROLLER = 'magentizer:create:config-model';

    private const MODULE_TEMPLATES_FILE = 'Templates/CreateConfigModel/CreateConfigModel.php.tpl';
    private const MODULE_TEMPLATES_METHODS_IS_METHOD = 'Templates/CreateConfigModel/MethodsTemplates/isMethod.php.tpl';
    private const MODULE_TEMPLATES_METHODS_GET_METHOD = 'Templates/CreateConfigModel/MethodsTemplates/getMethod.php.tpl';

    private const SYSTEM_XML = "system.xml";
    private const MODEL_CONFIG_PATH = "Model/Config";
    private const MODEL_CONFIG_FILENAME = "Config.php";

    private const SYSTEM_CONFIG_PATH = "config_path";

    protected DirectoryList $directoryList;
    protected Dir $directory;
    protected WriteFactory $write;
    protected DriverInterface $driver;
    protected DOMDocument $domDocument;

    protected $rootPath;
    private $moduleSelfPath;
    private $templatesClass;
    private $templatesMethodIsMethod;
    private $templatesMethodGetMethod;

    /**
     * CreateConfigModel constructor.
     *
     * @param DirectoryList   $directoryList
     * @param Dir             $directory
     * @param WriteFactory    $write
     * @param DriverInterface $driver
     * @param DOMDocument     $domDocument
     * @param string|null     $name
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     */
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
        $this->directory     = $directory;
        $this->write         = $write;
        $this->driver        = $driver;
        $this->domDocument   = $domDocument;
        $this->rootPath      = $this->directoryList->getRoot();

        // TODO: refactor those 4 next operations
        $this->moduleSelfPath           = $this->directory->getDir(
            SharedConstants::MODULE_SELF_FULLNAME
        );
        $this->templatesClass           = $this->driver->fileGetContents(
            $this->moduleSelfPath . DIRECTORY_SEPARATOR . self::MODULE_TEMPLATES_FILE
        );
        $this->templatesMethodIsMethod  = $this->driver->fileGetContents(
            $this->moduleSelfPath . DIRECTORY_SEPARATOR . self::MODULE_TEMPLATES_METHODS_IS_METHOD
        );
        $this->templatesMethodGetMethod = $this->driver->fileGetContents(
            $this->moduleSelfPath . DIRECTORY_SEPARATOR . self::MODULE_TEMPLATES_METHODS_GET_METHOD
        );
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
        if ($vendorName
            && !$moduleName
            && $vendorNameContainModuleName = $this->isVendorNameContainModuleName($vendorName)
        ) {
            [$vendorName, $moduleName] = $vendorNameContainModuleName;
        }

        if ($input->getOption('all')) {
            if ($moduleName) {
                $output->writeln(
                    "<fg=red>\"all\" option is incomptible with a specific module. Please make a choice.</>"
                );
                exit;
            }
            $answer = false;
            while (!$answer) {
                $path = 'app/code';
                $path .= $vendorName ? '/' . $vendorName : '';
                $output->writeln(
                    "<fg=yellow>Do you really want to create config files for all {$path} modules ?</> [yes/NO]");
                $handle = fopen("php://stdin", "r");
                $answer = trim(fgets($handle));
                if (!$answer) {
                    $output->writeln("<fg=blue>Safety exit. Nothing done.</>");
                    break;
                }
            }
            if (in_array($answer, ['y', 'Y', 'yes', 'YES'])) {
                // Get all app code module
                $list = $this->getAppCodeModuleList($vendorName);
                // Foreach execute creation
                foreach ($list as $vendorName => $moduleList) {
                    foreach ($moduleList as $moduleName) {
                        $this->createConfigClass(
                            $vendorName,
                            $moduleName,
                            $output,
                            $input->getOptions()
                        );
                    }
                }
            }
        } else {

            // TODO : load all app/code module list to preemptive hint choice if not present is arguments
            while (!$vendorName) {
                $output->writeln("<fg=yellow>What Vendor name for this new module?</>");
                $handle     = fopen("php://stdin", "r");
                $vendorName = trim(fgets($handle));
            }

            while (!$moduleName) {
                $output->writeln("<fg=yellow>What Module name?</>");
                $handle     = fopen("php://stdin", "r");
                $moduleName = trim(fgets($handle));
            }

            $this->createConfigClass($vendorName, $moduleName, $output, $input->getOptions());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_MAGENTIZER_CREATE_CONTROLLER);
        $this->setDescription("Create a Config Model Class for system.xml");
        $this->setDefinition([
                                 new InputArgument(
                                     SharedConstants::VENDOR_NAME_ARGUMENT,
                                     InputArgument::OPTIONAL, "Vendor's Name"),
                                 new InputArgument(
                                     SharedConstants::MODULE_NAME_ARGUMENT,
                                     InputArgument::OPTIONAL, "Module's Name"),
                             ]);
        $this->addOption(
            SharedConstants::OPTION_ALL_ARGUMENT,
            SharedConstants::OPTION_ALL_ARGUMENT_SHORT,
            null,
            "Create config files for all module in app/code[/<vendor>]"
        )->addOption(
            SharedConstants::OPTION_OVERWRITE_FILE_ARGUMENT,
            SharedConstants::OPTION_OVERWRITE_FILE_ARGUMENT_SHORT,
            null,
            "Overwrite file if existing"
        );
        parent::configure();
    }

    /**
     * @param string          $vendorName
     * @param string          $moduleName
     * @param OutputInterface $output
     * @param array           $options
     *
     * @return bool
     * @throws \JsonException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function createConfigClass(
        string $vendorName,
        string $moduleName,
        OutputInterface $output,
        array $options = []
    ): bool {
        $vendorPath        = $this->rootPath . '/app/code/' . $vendorName;
        $systemXMLFullPath = implode('/', [
            $vendorPath,
            $moduleName,
            DirectoryList::CONFIG,
            Area::AREA_ADMINHTML,
            self::SYSTEM_XML
        ]);

        if (!$this->driver->isExists($systemXMLFullPath)) {
            $output->writeln(
                "<fg=red>{$vendorName}"
                . DIRECTORY_SEPARATOR . "{$moduleName}"
                . DIRECTORY_SEPARATOR . "system.xml not found. Exiting without writing anything.</>"
            );

            return false;
        }

        $configClassFullPath = implode('/', [
            $vendorPath,
            $moduleName,
            self::MODEL_CONFIG_PATH
        ]);
        $configClassPath     = implode('/', [
            $vendorName,
            $moduleName,
            self::MODEL_CONFIG_PATH
        ]);

        $overwritten = false;
        if ($this->driver->isExists($configClassFullPath)) {
            $overwritten = true;
            if (!$options['overwrite']) {
                $output->writeln(
                    "<fg=red>" . $configClassPath .
                    ".php file already exists. Exiting without writing anything.</>");

                return false;
            }
        }

        $configPaths = $this->getConfigPaths($this->getSystemXMLData($systemXMLFullPath));

        if (!count($configPaths)) {
            echo "File does not seems to be correct. Exiting without writing anything.\n";

            return false;
        }

        if (!$this->createFile($configPaths, $vendorPath, $vendorName, $moduleName)) {
            echo "Something went wrong. Exiting without writing anything.\n";

            return false;
        }
        $writeMethod = ($overwritten) ? "overwritten" : "created";
        $output->writeln("<fg=green>Config file {$configClassPath} has been correctly {$writeMethod}.</>");

        return true;
    }

    /**
     * @param array  $configPaths
     * @param string $vendorPath
     * @param string $vendorName
     * @param string $moduleName
     *
     * @return bool
     */
    private function createFile(
        array $configPaths,
        string $vendorPath,
        string $vendorName,
        string $moduleName
    ): bool {
        $properties = "\n";
        $methods    = "\n";

        foreach ($configPaths as $configPath => $isBool) {
            $constName  = mb_strtoupper(str_replace('/', '_', $configPath));
            $properties .= "    public const " . $constName . " ='" . $configPath . "';\n";

            $configPathArray = explode('/', $configPath);
            $methods         .= str_replace(
                ['{{{config}}}', '{{{config_const}}}'],
                [$this->toCamelCase(end($configPathArray)), $constName],
                ($isBool) ? $this->templatesMethodIsMethod : $this->templatesMethodGetMethod
            );
        }

        $filePath = implode("/", [
            $vendorPath,
            $moduleName,
            self::MODEL_CONFIG_PATH,
            ''
        ]);
        $template = $this->getFilledTemplate($vendorName, $moduleName, $properties, trim($methods));

        try {
            $newFile = $this->write->create($filePath, DriverPool::FILE);
            $newFile->writeFile(self::MODEL_CONFIG_FILENAME, $template);
        } catch (\Exception $e) {
            echo $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * @param string $vendorName
     * @param string $moduleName
     * @param        $properties
     * @param        $methods
     *
     * @return string
     */
    private function getFilledTemplate(
        string $vendorName,
        string $moduleName,
        $properties,
        $methods
    ): string {
        return str_replace(
            ['{{{vendor}}}', '{{{properties}}}', '{{{methods}}}'],
            [$vendorName . "\\" . $moduleName, $properties, $methods],
            $this->templatesClass
        );
    }

    /**
     * @param string $stringToCamelCase
     *
     * @return string
     */
    private function toCamelCase(
        string $stringToCamelCase
    ): string {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $stringToCamelCase)));
    }

    /**
     * @param string $systemXMLFullPath
     *
     * @return array
     * @throws \JsonException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function getSystemXMLData(string $systemXMLFullPath): array
    {
        return json_decode(
            json_encode(
                simplexml_load_string(
                    $this->driver->fileGetContents($systemXMLFullPath)
                )
            ),
            true
        );
    }

    /**
     * @param $systemXML
     *
     * @return array
     */
    private function getConfigPaths($systemXML): array
    {
        $configPaths = [];

        if (!isset($systemXML['system']['section'])) {
            return $configPaths;
        }

        // If there’s only one child
        if (isset($systemXML['system']['section']['@attributes'])) {
            $sections = [$systemXML['system']['section']];
        } else {
            $sections = $systemXML['system']['section'];
        }

        foreach ($sections as $section) {
            // If there’s only one child
            if (isset($section['group']['@attributes'])) {
                $groups = [$section['group']];
            } else {
                $groups = $section['group'];
            }
            foreach ($groups as $group) {
                // If there’s only one child
                if (isset($group['field']['@attributes'])) {
                    $fields = [$group['field']];
                } else {
                    $fields = $group['field'];
                }
                foreach ($fields as $field) {
                    $key               = isset($field[self::SYSTEM_CONFIG_PATH])
                        ? $field[self::SYSTEM_CONFIG_PATH]
                        : implode('/', [
                            $section["@attributes"]['id'],
                            $group["@attributes"]['id'],
                            $field["@attributes"]['id']
                        ]);
                    $configPaths[$key] = (isset($field["source_model"])
                                          && $field["source_model"]
                                             === 'Magento\Config\Model\Config\Source\Yesno') ? 1 : 0;

                }
            }
        }

        return $configPaths;
    }

    /**
     * @return array
     */
    private function getAppCodeModuleList(?string $vendorName): array
    {
        $vendors     = [];
        $directories = glob($this->rootPath . '/app/code/*', GLOB_ONLYDIR);
        foreach ($directories as $directory) {
            if ($vendorName && !strpos($directory, $vendorName)) {
                continue;
            }
            $modules = glob($directory . '/*', GLOB_ONLYDIR);

            $arrayDirectory = explode(DIRECTORY_SEPARATOR, $directory);
            foreach ($modules as $module) {
                if ($this->moduleSelfPath === $module) {
                    continue;
                }

                $arrayModules                    = explode(DIRECTORY_SEPARATOR, $module);
                $vendors[end($arrayDirectory)][] = end($arrayModules);
            }
        }

        return $vendors;
    }

    private function isVendorNameContainModuleName(string $vendorName): ?array
    {
        $possibleSeparationChars = ['_','/','::'];

        foreach ($possibleSeparationChars as $possibleSeparationChar) {
            if (strpos($vendorName, $possibleSeparationChar)) {
                return explode($possibleSeparationChar, $vendorName);
            }
        }
        return null;
    }
}
