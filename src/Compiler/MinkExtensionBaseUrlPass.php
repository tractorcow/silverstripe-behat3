<?php

namespace SilverStripe\BehatExtension\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Behat\SilverStripe container compilation pass.
 * Passes Base URL available in MinkExtension config.
 * Used for the {@link \SilverStripe\BehatExtension\MinkExtension} subclass.
 *
 * @author Michał Ochman <ochman.d.michal@gmail.com>
 */
class MinkExtensionBaseUrlPass implements CompilerPassInterface
{
    /**
     * Passes MinkExtension's base_url parameter
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        // Check if URL Is already valid
        if ($baseURL = $container->getParameter('mink.base_url')) {
            // If base_url is already defined, also set it in the SilverStripe mapping
            if (!getenv('SS_HOST')) {
                putenv('SS_HOST=' . parse_url($baseURL, PHP_URL_HOST));
            }
            return;
        }

        // Find new url
        if ($baseURL = $this->guessBaseURL()) {
            $container->setParameter('mink.base_url', $baseURL);
        } else {
            throw new \InvalidArgumentException(
                '"base_url" not configured. Please specify it in your behat.yml configuration, ' .
                'or in your .env config with SS_HOST'
            );
        }

        // The Behat\MinkExtension\Extension class copies configuration into an internal hash,
        // we need to follow this pattern to propagate our changes.
        $parameters = $container->getParameter('mink.parameters');
        $parameters['base_url'] = $container->getParameter('mink.base_url');
        $container->setParameter('mink.parameters', $parameters);
    }

    /**
     * Guess baseurl
     *
     * @return string
     */
    protected function guessBaseURL()
    {
        if (isset($_REQUEST['HTTP_HOST'])) {
            return 'http://'.$_REQUEST['HTTP_HOST'].'/';
        }
        if (getenv('SS_HOST')) {
            return 'http://'.getenv('SS_HOST').'/';
        }
        return null;
    }
}
