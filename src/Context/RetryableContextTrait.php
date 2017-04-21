<?php

namespace SilverStripe\BehatExtension\Context;

trait RetryableContextTrait
{
    /**
     * Invoke $try callback for a non-empty result with a given timeout
     *
     * @param callable $try
     * @param int $timeout Number of seconds to retry for
     * @return mixed Result of invoking $try, or null if timed out
     */
    protected function retry($try, $timeout = 3)
    {
        do {
            $result = $try();
            if ($result) {
                return $result;
            }
            sleep(1);
        } while (--$timeout >= 0);
        return null;
    }
}
