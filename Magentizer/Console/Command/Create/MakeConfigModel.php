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
    private const MODULE_TEMPLATES_METHODS_IS_METHOD = 'Templates/MethodsTemplates/isMethod.phptpl';
    private const MODULE_TEMPLATES_METHODS_GET_METHOD = 'Templates/MethodsTemplates/getMethod.phptpl';
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
    private $templatesClass;
    private $templatesMethodIsMethod;
    private $templatesMethodGetMethod;

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
        $this->templatesClass = $this->driver->fileGetContents($this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_FILE);
        $this->templatesMethodIsMethod = $this->driver->fileGetContents($this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_METHODS_IS_METHOD);
        $this->templatesMethodGetMethod = $this->driver->fileGetContents($this->moduleSelfPath . '/' . self::MODULE_TEMPLATES_METHODS_GET_METHOD);

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
        $systemXMLFullPath = implode('/', [
            $vendorPath,
            $moduleName,
            DirectoryList::CONFIG,
            Area::AREA_ADMINHTML,
            self::SYSTEM_XML
        ]);

        if(!$this->driver->isExists($systemXMLFullPath)) {
            echo "system.xml not found. Exiting without writing anything.\n";
            return false;
        }

        $configClassFullPath = implode('/', [
            $vendorPath,
            $moduleName,
            self::MODEL_CONFIG_PATH
        ]);
        $configClassPath = implode('/', [
            $vendorName,
            $moduleName,
            self::MODEL_CONFIG_PATH
        ]);

        if($this->driver->isExists($configClassFullPath)) {
            echo $configClassPath. ".php file already exists. Exiting without writing anything.\n";
            return false;
        }

        $systemXML = json_decode(json_encode(simplexml_load_string($this->driver->fileGetContents($systemXMLFullPath))), true);

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

        return true;
    }

    private function createConfigClass(array $configPaths, string $vendorPath,  string $vendorName, string $moduleName) : bool
    {
        $properties = "\n";
        $methods = "\n";

        foreach ($configPaths as $configPath => $isBool) {
            $constName = mb_strtoupper(str_replace('/','_',$configPath));
            $properties .= "    public const " . $constName . " ='". $configPath ."';\n";

            $configPathArray = explode('/', $configPath);
            $methods .= str_replace(
                ['{{config}}','{{config_const}}'], [$this->toCamelCase(end($configPathArray)), $constName],
                ($isBool) ? $this->templatesMethodIsMethod : $this->templatesMethodGetMethod
            );
        }

        $filePath = implode("/", [
            $vendorPath,
            $moduleName,
            self::MODEL_CONFIG_PATH,
            ''
        ]);
        $template = $this->getTemplate($vendorName, $moduleName, $properties, trim($methods));

        try {
            $newFile = $this->write->create($filePath,DriverPool::FILE);
            $newFile->writeFile(self::MODEL_CONFIG_FILENAME, $template);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        return true;
    }

    private function getTemplate(string $vendorName, string $moduleName,  $properties, $methods) : string
    {
        return str_replace(['{{namespace}}', '{{properties}}', '{{methods}}'],
            [$vendorName . "\\" . $moduleName, $properties, $methods], $this->templatesClass);
    }

    private function toCamelCase(string $stringToCamelCase) : string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $stringToCamelCase)));
    }
}
