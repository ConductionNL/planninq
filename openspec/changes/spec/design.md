# Label CRUD

**Status**: pr-created
**Priority**: MVP

## Summary

Add label management to Planix. Labels are simple name+color objects stored in OpenRegister.
One backend controller + one frontend component.

## Scope (1 task)

- Backend: LabelController with GET/POST/DELETE via OpenRegister

## Architecture

- Labels stored as OpenRegister objects (use existing schema)
- LabelController follows Controller→Service→Mapper pattern
