# Security

Arcana loads skill definitions from the filesystem and serves their content to AI agents. Because skill files may come from third-party packages, user-generated content, or untrusted directories, the library is designed with the assumption that **a SKILL.md file could be malicious**. This document describes the protections in place, what they guard against, and where the responsibility boundary lies.

---

## Threat Model

The primary threat Arcana protects against is a **malicious or misconfigured SKILL.md** that attempts to:

- Read arbitrary files from the server filesystem (e.g. `/etc/passwd`, `.env`, private keys)
- Execute scripts outside the skill's own directory
- Cause the agent to act on injected or fabricated content

Arcana does **not** protect against a compromised skills directory itself (if an attacker can write arbitrary files to the skills root, they can write a valid SKILL.md). Access control to the skills directory is the operator's responsibility.

---

## Skill Name Validation

The first line of defence applies before any filesystem access. Skill names are validated against a strict allowlist:

- Must match `/^[a-z][a-z0-9\-]*$/`
- Maximum 64 characters
- No directory separators (`/`, `\`), dots, null bytes, or special characters

This is enforced in three independent places (`SkillLibrary`, `FlysystemSkillLibrary`, `SkillParser`) so that no code path reaches the filesystem with an unvalidated name. Any call to `loadSkill('../etc/passwd')` or `loadSkill('/etc/shadow')` throws a `ValidationException` before a single file is opened.

---

## Resource Path Protection

Skills can declare supplementary resource files in their frontmatter:

```yaml
resources:
  - name: overview
    description: Background context
    path: resources/overview.md
```

When a resource is loaded, Arcana applies **three sequential guards** before reading the file. If any guard fails, a `SecurityException` is thrown and the file is never opened.

### Guard 1 — Reject absolute paths

```
path: /etc/passwd          ← rejected immediately
path: \windows\system32    ← rejected immediately
```

The check happens on the raw string before any I/O. Absolute paths can reference any location on the filesystem regardless of the skill directory, so they are unconditionally rejected.

### Guard 2 — Reject traversal sequences

```
path: ../../etc/passwd     ← rejected immediately
path: resources/../../../secret.key  ← rejected immediately
```

Any path containing `..` is rejected before filesystem access. This covers the common class of traversal attacks that use double-dot sequences to climb out of the skill directory.

### Guard 3 — Realpath containment check

Guards 1 and 2 are string-level checks. Guard 3 uses `realpath()` to resolve the full path after joining it with the skill directory, and then asserts that the resolved path is still within the skill's own directory. This guard catches traversal attempts that avoid `..` by using symlinks:

```
# If resources/secret is a symlink pointing to /etc/shadow:
path: resources/secret     ← passes Guards 1 and 2, caught by Guard 3
```

After `realpath()` resolution, the path must begin with the skill directory path. If it does not, `SecurityException` is thrown with a message identifying the escape attempt.

### Implementation

The three guards are implemented in `NativeResourceLoader` (native PHP filesystem) and `FlysystemResourceLoader` (any Flysystem adapter). The Flysystem loader applies Guards 1 and 2; Guard 3 is omitted because Flysystem virtualises paths and does not expose symlink semantics.

```
src/NativeResourceLoader.php
src/Flysystem/FlysystemResourceLoader.php
```

---

## Script Path Protection

Skills can declare executable scripts in their frontmatter:

```yaml
scripts:
  - name: fetch-context
    description: Fetches runtime deployment context
    path: scripts/fetch-context.php
    language: php
```

Scripts are potentially more dangerous than resources because they are intended to be *executed*, not just read. Arcana applies the same three guards to script paths via `NativeScriptRunner` and `FlysystemScriptRunner` before any execution can occur.

**Scripts are not executed by default.** Declaring a script in SKILL.md does nothing on its own. Execution only happens if the application implements a `SkillPreprocessorInterface` that calls a script runner. This is an explicit, opt-in decision by the developer.

### Building a script runner

`NativeScriptRunner` is an abstract class. Subclass it and implement `execute()` to define how scripts are run. The three path guards are applied in the `final run()` method before `execute()` is ever called, so your implementation only receives a path that has been verified to be within the skill directory.

```php
use PeterFox\Arcana\NativeScriptRunner;
use PeterFox\Arcana\SkillScript;

final class PhpScriptRunner extends NativeScriptRunner
{
    protected function execute(SkillScript $script, string $resolvedPath): string
    {
        // $resolvedPath is guaranteed to be within the skill directory.
        // You are still responsible for sandboxing the execution itself.
        ob_start();
        include $resolvedPath;
        return ob_get_clean() ?: '';
    }
}
```

> **Important:** Path containment guarantees the script is _within_ the skill directory. It does not sandbox what the script _does_ once it runs. If you allow PHP `include`, the script can still make network calls, read environment variables, write files, and so on. Use an appropriate sandbox (e.g. a subprocess, a container, or a restricted PHP environment) if you need to constrain script behaviour.

```
src/NativeScriptRunner.php
src/Contract/SkillScriptRunnerInterface.php
src/Flysystem/FlysystemScriptRunner.php
```

---

## YAML Parsing Safety

Skill frontmatter is parsed using `symfony/yaml` with no additional flags. This means:

- `PARSE_OBJECT` is disabled — PHP objects cannot be deserialised from YAML
- `PARSE_PHP_CONSTANTS` is disabled — PHP constants cannot be injected
- `PARSE_CUSTOM_TAGS` is disabled — custom YAML tags are not processed

A `!php/object` or `!php/const` YAML tag in a SKILL.md file will cause a parse error, not object deserialisation.

---

## SecurityException

All security violations throw `PeterFox\Arcana\Exception\SecurityException`, which extends the base `ArcanaException`. Named static constructors produce consistent, descriptive messages that identify the type of violation, the name of the offending resource or script, and the path that was rejected:

| Factory method | Guard |
|---|---|
| `SecurityException::absolutePathRejected($type, $name, $path)` | Guard 1 |
| `SecurityException::traversalSequenceRejected($type, $name, $path)` | Guard 2 |
| `SecurityException::directoryEscapeDetected($type, $name, $resolvedPath, $dir)` | Guard 3 |
| `SecurityException::skillDirectoryUnresolvable($dir)` | Pre-Guard 3 |

Every message includes the phrase "This is a safety restriction" so that operators reading logs can immediately distinguish a security block from an application bug.

Example messages:

```
Absolute paths are not permitted in skill resource declarations.
This is a safety restriction — resources must be relative to their skill directory.
Resource 'evil' declared path: '/etc/passwd'.

Path traversal sequences ('..') are not permitted in skill script declarations.
This is a safety restriction — scripts must be contained within their skill directory.
Script 'fetch-data' declared path: '../../outside/script.php'.

Path traversal detected: resource 'secret' resolved to '/etc/shadow',
which is outside the skill directory '/var/app/skills/my-skill'.
This is a safety restriction — resources must be contained within their skill directory.
```

---

## Summary of Protections

| Attack vector | Protection |
|---|---|
| `loadSkill('../etc/passwd')` | Skill name allowlist (`ValidationException`) |
| Resource `path: /etc/passwd` | Guard 1 — absolute path rejection |
| Resource `path: ../../etc/passwd` | Guard 2 — traversal sequence rejection |
| Resource `path: link-to-outside` (symlink) | Guard 3 — realpath containment check |
| Script `path: /etc/cron.d/backdoor` | Same three guards in NativeScriptRunner |
| YAML object deserialisation | symfony/yaml parsed with no unsafe flags |
| Script execution by default | Scripts are opt-in; no runner is invoked automatically |

---

## Reporting a Vulnerability

If you discover a security issue in Arcana, please open a [GitHub issue](https://github.com/peterfox/arcana/issues) marked as a security concern, or contact the maintainer directly. Please avoid disclosing vulnerabilities publicly until a fix is available.
