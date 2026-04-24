<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillMetadata;

/**
 * Laravel Facade for the Arcana skill library.
 *
 * Proxies to the SkillLibraryInterface binding in the container.
 *
 * @method static array<SkillMetadata> listSkills(?string $filter = null)
 * @method static Skill loadSkill(string $name)
 * @method static bool hasSkill(string $name)
 *
 * @see \PeterFox\Arcana\SkillLibrary
 */
final class Arcana extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SkillLibraryInterface::class;
    }
}
