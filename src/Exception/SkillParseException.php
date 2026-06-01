<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Exception;

/**
 * Thrown when a SKILL.md file cannot be parsed due to malformed YAML
 * frontmatter, missing required fields, or invalid structure.
 *
 * The file path is available as a separate property for logging:
 *
 *   } catch (SkillParseException $e) {
 *       $logger->error($e->getMessage(), ['file' => $e->filePath]);
 *   }
 *
 * It is intentionally excluded from the exception message to avoid leaking
 * server filesystem paths to API responses or LLM tool-call results.
 */
final class SkillParseException extends ArcanaException
{
    public function __construct(
        string $message,
        public readonly ?string $filePath = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }
}
