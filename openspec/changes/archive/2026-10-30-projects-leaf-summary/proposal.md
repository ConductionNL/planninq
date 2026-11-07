# projects-leaf-summary

## Why

DqZaak draws projects inside the "Gekoppeld" side card, not as a widget of their own. nextcloud-vue's `linked` card
(linked-integrations-card) mounts each leaf with `onSummary` and draws the rows. On a case the empty state also said
"voor deze klant" (for this client), which is wrong there.

## What changes

- Given `onSummary`, the projects leaf draws nothing and reports `{ count, noun: "projects", rows, actions }`: a row per
  listed project linking to it, and "New project" (with an object in scope) and "Open in Planninq".
- Nothing is reported while loading or after a failed read.
- On a case the empty state reads "No projects for this case yet" (nl "Nog geen projecten voor deze zaak").
