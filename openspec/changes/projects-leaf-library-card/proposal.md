# projects-leaf-library-card

## Why

On a dossiq case the `planninq-projects` leaf drew no card: "0 Projecten", a sentence and two buttons sat on the bare
page, with no title, border or designed empty state. nextcloud-vue now hosts a mount-mode leaf in the library widget
card when its descriptor asks for it (integration-leaf-card), so every app's leaf gets one look.

## What changes

- The descriptor declares `chrome: "card"`.
- Given the host's `onHeader` prop, the leaf reports its count, its budget as a note, "Open in Planninq" and
  "New project" as title-row actions, and its empty state, and renders only its rows. Without the prop (an older host,
  a dashboard) it renders as before.
