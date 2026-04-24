---
name: example-skill
description: An example skill demonstrating the Arcana SKILL.md format with all supported frontmatter fields.
version: 1.2.0
author: Peter Fox
tags:
  - example
  - demo
  - testing
triggers:
  - when you need a demonstration
  - show me an example skill
  - demonstrate skill format
resources:
  - name: overview
    description: High-level overview and background reading for this skill.
    path: resources/overview.md
scripts: []
references:
  - title: Arcana Documentation
    url: https://github.com/peterfox/arcana
  - title: Open Agent Skills Specification
    url: https://github.com/peterfox/arcana/blob/main/SKILL.md
---

# Example Skill

This is a fully-featured example skill demonstrating the Arcana SKILL.md format.

## Purpose

Use this skill when you need to understand how Arcana skill files are structured.

## Capabilities

- Demonstrates YAML frontmatter with all supported fields
- Shows how to declare resources, scripts, and references
- Provides a working test fixture for the Arcana test suite

## Instructions

1. Review the YAML frontmatter at the top of this file
2. Note the `name`, `description`, `version`, and `author` fields
3. Check the `tags` and `triggers` for discovery metadata
4. See the `resources` section for bundled documentation links
