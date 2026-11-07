# Finance import

Your finance system sends actual costs and commitments to planninq. Each line lands on the project with the same project number. Planninq holds no import code for this. The integration app (integriq) reads the finance system and writes the lines.

## What a project manager sees

Open a project and go to the Finance tab. Lines from the finance system show "From the finance system". You cannot edit or remove them there. The next import would overwrite a hand edit, so only the import changes them.

## What an admin sets up

1. Create a Nextcloud group named `planninq-finance-import`.
2. Add the integration app's service account to that group.
3. Give each project a key that matches its project number in the finance system.
4. Map the finance system's export onto the fields below in the integration app.

Only admins and the members of that group can write imported lines. The project owner, the portfolio managers and admins can read them.

## The contract

The integration writes to `POST /apps/openregister/api/objects/planninq/financeLine` with these fields:

| Field | Required | Value |
|---|---|---|
| `source` | yes | Always `import`. |
| `externalRef` | yes | The finance system's own id of the line. |
| `projectKey` | yes | The project number. Planninq matches it against `project.key`. |
| `kind` | yes | `actual` for spend, `commitment` for orders not yet paid. `budget` and `forecast` are allowed too. |
| `amount` | yes | The amount in euro. |
| `category` | no | One of the cost categories an admin set under Beheer, Cost categories. |
| `date` | no | The booking date, as `YYYY-MM-DD`. |
| `description` | no | What the amount is for. |
| `phase` | no | A phase id of the project, when the finance system knows it. |

Leave `project` empty. Planninq fills it from `projectKey`.

## Sending a line again

Each `externalRef` exists once among imported lines. Sending it a second time is refused with the error code `planninq-finance-line-exists`. The answer names the existing line in `conflictingObject`. Send the new values to that line with `PATCH /apps/openregister/api/objects/planninq/financeLine/{conflictingObject}`.

## Lines that match no project

A line whose project number matches no project key is kept, with an empty project. Admins find these lines under Beheer, Unmatched finance lines. Open a line and pick its project. It then moves to that project's Finance tab.

A project number that two projects share also counts as unmatched. Planninq does not guess between them.

## Who sees the money

Project owners, the managers of the project's portfolio and admins see the amounts. Project members see the billing model and the planned dates, but no amount.
