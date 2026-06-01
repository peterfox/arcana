<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Contract\SkillResourceLoaderInterface;
use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\Exception\ValidationException;

/**
 * Immutable value object representing a fully-loaded AI agent skill.
 *
 * Returned by {@see Contract\SkillLibraryInterface::loadSkill()}.
 * Contains both the lightweight metadata (frontmatter) and the full
 * Markdown body content.
 *
 * Resources bundled with the skill can be loaded on demand via
 * {@see self::loadResource()}, which enforces path-traversal protection.
 */
final class Skill
{
    public function __construct(
        /** Parsed frontmatter metadata. */
        public readonly SkillMetadata $metadata,
        /** Full Markdown body of the skill (everything after the closing ---). */
        public readonly string $body,
        /** Resource loader used by loadResource(). Defaults to the native PHP filesystem loader. */
        private readonly SkillResourceLoaderInterface $resourceLoader = new NativeResourceLoader(),
    ) {}

    /**
     * Returns the full skill content, optionally appending all resource files.
     *
     * When $includeResources is true and the skill declares resources,
     * each resource's content is appended with a heading separator.
     * Unreadable resources are skipped silently to avoid breaking
     * the agent loop.
     */
    public function fullContent(bool $includeResources = true): string
    {
        if (!$includeResources || $this->metadata->resources === []) {
            return $this->body;
        }

        $content = $this->body;

        foreach ($this->metadata->resources as $resource) {
            try {
                $resourceContent = $this->loadResource($resource->name);
                $content .= "\n\n---\n\n### Resource: {$resource->name}";

                if ($resource->description !== '') {
                    $content .= "\n\n_{$resource->description}_";
                }

                $content .= "\n\n{$resourceContent}";
            } catch (SecurityException $e) {
                // A security guard fired — re-throw so the caller is aware
                // that a path traversal violation was detected, not a routine
                // I/O error. Swallowing this would allow probing without signal.
                throw $e;
            } catch (SkillParseException|ValidationException) {
                // Non-fatal I/O or validation errors: skip the unloadable resource
                // so a single bad resource does not prevent the skill from loading.
            }
        }

        return $content;
    }

    /**
     * Load the raw content of a named resource file bundled with this skill.
     *
     * Path traversal is strictly enforced by the injected resource loader.
     * The default {@see NativeResourceLoader} applies three filesystem guards;
     * {@see \PeterFox\Arcana\Flysystem\FlysystemResourceLoader} applies the same string-level guards and
     * delegates I/O to the configured Flysystem adapter.
     *
     * @throws ValidationException When the resource name is unknown.
     * @throws SkillParseException When the file cannot be found or read.
     * @throws SecurityException When the resource path attempts to escape the skill directory.
     */
    public function loadResource(string $name): string
    {
        $resource = null;

        foreach ($this->metadata->resources as $r) {
            if ($r->name === $name) {
                $resource = $r;
                break;
            }
        }

        if ($resource === null) {
            throw new ValidationException(
                "Resource '{$name}' is not declared in skill '{$this->metadata->name}'.",
            );
        }

        return $this->resourceLoader->load($resource, $this->metadata->directory());
    }

    /**
     * Return a copy of this skill with a new body, preserving the resource loader.
     *
     * Prefer this over constructing a bare `new Skill(...)` in preprocessors so
     * that the injected resource loader (e.g. {@see \PeterFox\Arcana\Flysystem\FlysystemResourceLoader})
     * is carried forward to the returned instance.
     */
    public function withBody(string $body): self
    {
        return new self(
            metadata: $this->metadata,
            body: $body,
            resourceLoader: $this->resourceLoader,
        );
    }

    /**
     * Convenience accessor for the skill name.
     */
    public function name(): string
    {
        return $this->metadata->name;
    }
}
