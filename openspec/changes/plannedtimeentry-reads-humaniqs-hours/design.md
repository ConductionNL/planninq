# Design: plannedTimeEntry reads humaniq's hours

## Context

Row `tim-hours-once` in `openspec/parity/capabilities.json` asks for logged hours that live once, in the HR app, so project reports and payroll read the same number. humaniq owns `TimeEntry` and accepts a day booking since humaniq#323. The proposal measured the field mapping; this file records the choices a builder needs.

## Decisions

- **humaniq owns the hours.** planninq writes through OpenRegister's object API to humaniq's register (ADR-022: consume OpenRegister, never rebuild it). No planninq controller sits between the page and the object.
- **`task` maps onto `domainObjectType` + `domainObjectRef`.** humaniq already carries that polymorphic pair; no new field on the owner.
- **`projectId` is a plain string**, cross-register, per ADR-062 rule 7. It is not a `$ref`.
- **Widgets hide, they do not go blank.** The four widgets that read `duration`, `user` and `date` declare `requiredApp: humaniq` (gate 55), the same pattern pipelinq uses to read planninq's `project`.
- **The repair step renames by `(application, slug)`.** The import's not-found branch creates a second schema, so a descriptor change alone would strand every existing row.

## Risks

- An instance without humaniq loses time logging. That is the decision: the hours have one home. The UI says so instead of failing silently.
- Rows written between the deploy and the repair step. The repair step is idempotent and runs on upgrade, so a second run picks them up.

## Out of scope

- Approval of hours (humaniq's timesheet approval owns it).
- Rates in humaniq. `hourlyRate` and `contractorRef` stay in planninq.
