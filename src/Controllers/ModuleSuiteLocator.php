<?php

namespace SilverStripe\BehatExtension\Controllers;

use Behat\Testwork\Cli\Controller;
use Behat\Testwork\Suite\Cli\SuiteController;
use Behat\Testwork\Suite\ServiceContainer\SuiteExtension;
use Behat\Testwork\Suite\SuiteRegistry;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\Core\Manifest\ModuleLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SilverStripe\Core\Manifest\ClassLoader;

/**
 * Locates test suite configuration based on module name.
 *
 * @see SuiteController for similar core behat controller
 */
class ModuleSuiteLocator implements Controller
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var SuiteRegistry
     */
    private $registry;

    /**
     * Cache of configured suites
     *
     * @see SuiteExtension Which registers these
     * @var array
     */
    private $suiteConfigurations = array();

    /**
     * Init suite locator
     *
     * @param ContainerInterface $container
     * @param SuiteRegistry $registry
     */
    public function __construct(
        ContainerInterface $container,
        SuiteRegistry $registry
    ) {
        $this->container = $container;
        $this->registry = $registry;
        $this->suiteConfigurations = $container->getParameter('suite.configurations');
    }

    /**
     * Configures command to be able to process it later.
     *
     * @param Command $command
     */
    public function configure(Command $command)
    {
        $command->addArgument(
            'module',
            InputArgument::OPTIONAL,
            "Specific module suite to load. "
                . "Must be in @modulename format. Supports @vendor/name syntax for vendor installed modules. "
                . "Ensure that a modulename/behat.yml exists containing a behat suite of the same name."
        );
    }

    /**
     * Processes data from container and console input.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \RuntimeException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->hasArgument('module')) {
            return;
        }

        // Get module
        $moduleName = $input->getArgument('module');
        if (strpos($moduleName, '@') === 0) {
            $moduleName = substr($moduleName, 1);
        }
        $module = $this->getModule($moduleName);

        // If suite is already configured in the root, switch to it and return
        if (isset($this->suiteConfigurations[$moduleName])) {
            $config = $this->suiteConfigurations[$moduleName];
            $this->registry->registerSuiteConfiguration(
                $moduleName, $config['type'], $config['settings']
            );
            return;
        }

        // Suite doesn't exist, so load dynamically from nested `behat.yml`
        $moduleConfig = $this->findModuleConfig($module);
        var_dump($moduleConfig);

die;

        // get module from short notation if path starts from @
        $currentModulePath = null;
        if ($module && preg_match('/^\@([^\/\\\\]+)(.*)$/', $module, $matches)) {
            $currentModuleName = $matches[1];
            // TODO Replace with proper module loader once AJShort's changes are merged into core
            $currentModulePath = $modules[$currentModuleName];
            $module = str_replace(
                '@'.$currentModuleName,
                $currentModulePath.DIRECTORY_SEPARATOR.$pathSuffix,
                $module
            );
        // get module from provided features path
        } elseif (!$currentModuleName && $module) {
            $path = realpath(preg_replace('/\.feature\:.*$/', '.feature', $module));
            foreach ($modules as $moduleName => $modulePath) {
                if (false !== strpos($path, realpath($modulePath))) {
                    $currentModuleName = $moduleName;
                    $currentModulePath = realpath($modulePath);
                    break;
                }
            }
            $module = $currentModulePath.DIRECTORY_SEPARATOR.$pathSuffix.DIRECTORY_SEPARATOR.$module;
        // if module is configured for profile and feature provided
        } elseif ($currentModuleName && $module) {
            $currentModulePath = $modules[$currentModuleName];
            $module = $currentModulePath.DIRECTORY_SEPARATOR.$pathSuffix.DIRECTORY_SEPARATOR.$module;
        }

        if ($input->getOption('namespace')) {
            $namespace = $input->getOption('namespace');
        } else {
            $namespace = ucfirst($currentModuleName);
        }

        if ($currentModuleName) {
            $this->container
                ->get('behat.silverstripe_extension.context.class_guesser')
                // TODO Improve once modules can declare their own namespaces consistently
                ->setNamespaceBase($namespace);
        }

        // todo: Probably what we want to do is get the default suite from
        // SuiteRegistry, and add this context to it

        /** @var \Behat\Behat\Console\Command\BehatCommand $command */
        $command = $this->container
            ->get('behat.console.command');
        $command->setFeaturesPaths($module ? array($module) : array());
        return null;
    }

    /**
     * Find target module being tested
     *
     * @param $input
     * @return Module
     */
    protected function getModule($input)
    {
        $module = ModuleLoader::instance()->getManifest()->getModule($input);
        if (!$module) {
            throw new \InvalidArgumentException("No module $input installed");
        }
        return $module;
    }

    /**
     * Get behat.yml configured for this module
     *
     * @param Module $module
     * @return string Path to config
     */
    protected function findModuleConfig(Module $module)
    {
        $pathSuffix = $this->container->getParameter('silverstripe_extension.context.path_suffix');
        $path = $module->getPath();

        // Find all candidate paths
        foreach ([ $path . '/', $path . '/' . $pathSuffix] as $parent) {
            foreach ([$parent.'behat.yml', $parent.'.behat.yml'] as $candidate) {
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }
        throw new \InvalidArgumentException("No behat.yml found for module " . $module->getName());
    }



    /**
     * {@inheritdoc}
     */
    public function executeSuite(InputInterface $input, OutputInterface $output)
    {
        $exerciseSuiteName = $input->getOption('suite');

        if (null !== $exerciseSuiteName && !isset($this->suiteConfigurations[$exerciseSuiteName])) {
            throw new SuiteNotFoundException(sprintf(
                '`%s` suite is not found or has not been properly registered.',
                $exerciseSuiteName
            ), $exerciseSuiteName);
        }

        foreach ($this->suiteConfigurations as $name => $config) {
            if (null !== $exerciseSuiteName && $exerciseSuiteName !== $name) {
                continue;
            }

            $this->registry->registerSuiteConfiguration(
                $name, $config['type'], $config['settings']
            );
        }
    }
}
