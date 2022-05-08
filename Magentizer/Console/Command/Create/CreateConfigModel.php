<?php

namespace MageDeX\Magentizer\Console\Command\Create;

use DOMDocument;
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
use MageDeX\Magentizer\Console\FinalClasses\SharedConstants;

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

    /**
     * CreateConfigModel constructor.
     * @param DirectoryList $directoryList
     * @param Dir $directory
     * @param WriteFactory $write
     * @param DriverInterface $driver
     * @param DOMDocument $domDocument
     * @param string|null $name
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
        $this->directory = $directory;
        $this->write = $write;
        $this->driver = $driver;
        $this->domDocument = $domDocument;
        $this->rootPath = $this->directoryList->getRoot();
        $this->moduleSelfPath = $this->directory->getDir(SharedConstants::MODULE_SELF_NAME);
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
        $vendorName = $input->getArgument(SharedConstants::VENDOR_NAME_ARGUMENT);
        $moduleName = $input->getArgument(SharedConstants::MODULE_NAME_ARGUMENT);
;
        while (!$vendorName) {
            $output->writeln(self::GREEN ."What Vendor name for this new module?". SharedConstants::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $vendorName = trim(fgets($handle));
        }

        while (!$moduleName) {
            $output->writeln(self::GREEN . "What Module name?" . SharedConstants::COLOR_NONE);
            $handle = fopen("php://stdin", "r");
            $moduleName = trim(fgets($handle));
        }

        $this->createModule($vendorName, $moduleName, $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_MAGENTIZER_CREATE_CONTROLLER);
        $this->setDescription("Create a Config Model Class for system.xml");
        $this->setDefinition([
            new InputArgument(SharedConstants::VENDOR_NAME_ARGUMENT, InputArgument::OPTIONAL, "Vendor's Name"),
            new InputArgument(SharedConstants::MODULE_NAME_ARGUMENT, InputArgument::OPTIONAL, "Module's Name"),
        ]);
        parent::configure();
    }

    /**
     * @param string $vendorName
     * @param string $moduleName
     * @return bool
     * @throws \Magento\Framework\Exception\FileSystemException
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
            echo SharedConstants::RED . $configClassPath .
                ".php file already exists. Exiting without writing anything." .
                SharedConstants::NEW_LINE_NO_STYLE;
            return false;
        }

        $systemXML = json_decode(json_encode(simplexml_load_string($this->driver->fileGetContents($systemXMLFullPath))), true);

        $configPaths = $this->getConfigPaths($systemXML);

        if (!count($configPaths)) {
            echo "File does not seems to be correct. Exiting without writing anything.\n";
            return false;
        }

        if (!$this->createConfigClass($configPaths, $vendorPath, $vendorName, $moduleName)) {
            echo "Something went wrong. Exiting without writing anything.\n";
            return false;
        }
        echo SharedConstants::GREEN . "Config file ". $configClassPath ." has been correctly created."
            . sharedConstants::NEW_LINE_NO_STYLE;
        return true;
    }

    /**
     * @param array $configPaths
     * @param string $vendorPath
     * @param string $vendorName
     * @param string $moduleName
     * @return bool
     */
    private function createConfigClass(
        array $configPaths,
        string $vendorPath,
        string $vendorName,
        string $moduleName
    ) : bool {
        $properties = "\n";
        $methods = "\n";

        foreach ($configPaths as $configPath => $isBool) {
            $constName = mb_strtoupper(str_replace('/','_',$configPath));
            $properties .= "    public const " . $constName . " ='". $configPath ."';\n";

            $configPathArray = explode('/', $configPath);
            $methods .= str_replace(
                ['{{{config}}}','{{{config_const}}}'], [$this->toCamelCase(end($configPathArray)), $constName],
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

    /**
     * @param string $vendorName
     * @param string $moduleName
     * @param $properties
     * @param $methods
     * @return string
     */
    private function getTemplate(
        string $vendorName,
        string $moduleName,
        $properties,
        $methods
    ) : string {
        return str_replace(['{{{vendor}}}', '{{{properties}}}', '{{{methods}}}'],
            [$vendorName . "\\" . $moduleName, $properties, $methods], $this->templatesClass);
    }

    /**
     * @param string $stringToCamelCase
     * @return string
     */
    private function toCamelCase(
        string $stringToCamelCase
    ) : string {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $stringToCamelCase)));
    }

    /**
     * @param string $systemXMLFullPath
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
            ), true
        );
    }

    /**
     * @param $systemXML
     * @return array
     */
    private function getConfigPaths($systemXML): array
    {
        $configPaths = [];
        if (!isset($systemXML['system']['section'])) { return $configPaths; }

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

        return $configPaths;
    }
}

