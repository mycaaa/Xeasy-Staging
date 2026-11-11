<?php

namespace App\Services\ClaimFiles;

/**
 * UAT R-01: a claim file could not be stored (or a file rule fails against the
 * files that were stored). The message is safe to show to the claimant.
 */
class ClaimFileException extends \RuntimeException
{
}
