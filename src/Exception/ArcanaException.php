<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Exception;

/**
 * Base exception for all Arcana errors.
 *
 * Catch this to handle any Arcana-related error generically:
 *
 *   try {
 *       $skill = $library->loadSkill('my-skill');
 *   } catch (ArcanaException $e) {
 *       // handle any Arcana error
 *   }
 */
class ArcanaException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
