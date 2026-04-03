# 2026-04-03 Superpowers Skill Discovery Setup

## Context
- Пользователь попросил добавить `superpowers` skills в проектную среду Codex.
- Был указан локальный источник skills:
  - `C:\Users\OMEN\.cursor\plugins\cache\cursor-public\superpowers\8ea39819eed74fe2a0338e71789f06b30e953041`
- Для Codex корректный механизм discovery — не project-local каталог, а global discovery path `~/.agents/skills/`.

## Key Findings
- Локальный каталог `superpowers` существует и содержит полноценную папку `skills/` с набором `SKILL.md`.
- В текущей среде не существовало готового `~/.agents/skills/superpowers`.
- `C:\Users\OMEN\.codex\config.toml` не содержит `[features].multi_agent`, поэтому skills, завязанные на multi-agent mode, пока не смогут использовать полный capability surface.

## Decisions
- Не копировать skills в репозиторий: это не дало бы Codex-native discovery.
- Не клонировать заново репозиторий `superpowers`, потому что уже существует локальный источник.
- Создать global Codex discovery junction:
  - `C:\Users\OMEN\.agents\skills\superpowers`
  - `-> C:\Users\OMEN\.cursor\plugins\cache\cursor-public\superpowers\8ea39819eed74fe2a0338e71789f06b30e953041\skills`

## What This Changes
- При следующем старте Codex сможет обнаружить skills из `superpowers`.
- Изменение относится к пользовательской среде Codex, а не к коду framework repo.
- Для full use некоторых superpowers-skills может позже понадобиться отдельное включение multi-agent feature.

## Changed Files
- `artifacts/execution/2026-04-03-superpowers-skill-discovery-setup.md`

## Validation
- Проверено существование source directory `superpowers`.
- Проверено создание junction в `C:\Users\OMEN\.agents\skills\superpowers`.
- Проверено, что через junction видны skill directories:
  - `brainstorming`
  - `dispatching-parallel-agents`
  - `executing-plans`
  - `finishing-a-development-branch`
  - `receiving-code-review`
  - `requesting-code-review`
  - `subagent-driven-development`
  - `systematic-debugging`
  - `test-driven-development`
  - `using-git-worktrees`
  - `using-superpowers`
  - `verification-before-completion`
  - `writing-plans`
  - `writing-skills`
- Репозиторий framework не менялся; PHP QA-контур для этого шага `N/A`.

## Open Risks
- Junction указывает на Cursor plugin cache path с конкретным hash; если Cursor позже очистит или переместит этот cache, link перестанет быть валидным.
- Multi-agent dependent skills пока ограничены текущим `C:\Users\OMEN\.codex\config.toml`.

## Next Actions
- Перезапустить Codex, чтобы skills были обнаружены новой сессией.
- При желании сделать setup более устойчивым:
  - перенести `superpowers` в `C:\Users\OMEN\.codex\superpowers`
  - оставить `~/.agents/skills/superpowers` как junction уже на стабильный каталог
- Если понадобится full support subagent-skills, отдельно включить в Codex config:
  - `[features]`
  - `multi_agent = true`
