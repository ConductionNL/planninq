# Tasks: portfolio-people-capacity

## 1. Grouping (V1)

- [x] 1.1 Add `summariseByAssignee` to `src/utils/portfolioHelpers.js`, reusing `CLOSED_STATUSES` and `dueDateStatus`, with the unassigned row, the missing-estimate count, the 14-day window and `sharedWith` handled as shared without hours. Verify: new cases in `tests/vitest/portfolio.spec.js` for each field, the unassigned row and a shared task.

## 2. Page (V1)

- [ ] 2.1 Add the "By person" view as the default of `src/views/Portfolio.vue`, with a disclosure per person for the per-project breakdown, and keep "By project" unchanged behind a toggle with `aria-pressed`. Verify: `tests/e2e/capacity.spec.ts` "two people across two projects" and "switching between people and projects by keyboard" on seeded data.
- [ ] 2.2 Add the portfolio picker and project filter, reading only the chosen projects. Verify: e2e "limiting to a portfolio"; `tests/vitest/portfolio.spec.js` case that tasks outside the chosen projects are not counted.
- [ ] 2.3 Rewrite the `_note` of the `Portfolio` page and the Capacity card description in `src/manifest.json`. Verify: the Reports page e2e reads the new card text.

## 3. Verification

- [ ] 3.1 `openspec validate portfolio-people-capacity --type change --strict` passes.
- [ ] 3.2 Every scenario in specs/ is covered by a test named in the task above it, or carries an `@e2e exclude <reason>` note.
