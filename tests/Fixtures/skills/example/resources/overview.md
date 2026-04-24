# Example Skill — Overview

This resource file is bundled with the `example-skill` fixture and is used
to test Arcana's resource loading and path traversal protection.

## Background

Agent skills in Arcana follow the open Agent Skills specification. Each skill
is a directory containing a `SKILL.md` file and any number of supplementary
resource files.

## Key Concepts

**Progressive Disclosure**
Skills expose lightweight metadata (name, description, tags, triggers) for
fast discovery. Full content — including this resource — is loaded only on
demand.

**Path Traversal Protection**
Resource files must reside within their skill's own directory. Any path that
would escape the skill directory is rejected with a `SecurityException`.
