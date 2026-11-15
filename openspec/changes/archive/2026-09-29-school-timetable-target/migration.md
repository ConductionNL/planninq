# Migration: school-timetable-target

## Current State
Register `planninq` 0.5.0 (after planninq#683) declares seven schemas. No timetable schema exists and no timetable rows exist.

## Target State
Register `planninq` 0.6.0 declares eight schemas; the new `timetableSession` schema (version 0.1.0) is empty until a rostering delivery or the demo import fills it.

## Migration Class
```
Version: none
File: none
Key operations:
- No Nextcloud migration class. Planninq owns no table (ADR-022).
- The existing repair step imports lib/Settings/planninq_register.json through
  OpenRegister's ConfigurationService; the info.version bump to 0.6.0 makes the
  version-based skip logic import it again, which creates the schema.
```

## Migration Steps
1. Bump `info.version` and `components.registers.planninq.version` to 0.6.0 (development reached 0.5.0 with planninq#683 while this change was open) and add the schema with `version: 0.1.0`. Verifiable: the register file parses and the schema test passes.
2. On upgrade, the repair step imports the register. Verifiable: `timetableSession` is listed under the `planninq` register in OpenRegister.

## Data Impact
No existing record is read, changed or removed. The import only adds a schema.

## Rollback Procedure
Revert the merge commit. The schema remains in OpenRegister, unused; it can be deleted from the OpenRegister schema admin if wanted. No data outside it is affected.

## Validation
- `PlanninqRegisterSchemaTest` asserts the schema, its required fields and its authorization.
- After upgrade on an instance: `GET /apps/openregister/api/registers` lists `timetableSession` under `planninq`.
