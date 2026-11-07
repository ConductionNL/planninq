# platform-baseline Specification (delta)

## Purpose

What planninq is as a product, independent of any page: who can operate it, where its look and text direction come from, where it runs, how sign-in works, where an administrator sees security risks, and why its data cannot make the server call the internal network. Covers rows plt-accessibility, plt-nldesign, plt-rtl, plt-self-host, plt-sso, plt-2fa, plt-security-insights and plt-outbound-guard.

## ADDED Requirements

### Requirement: Every page works with a keyboard and a screen reader (REQ-PLB-001)

planninq SHALL meet WCAG 2.1 AA on every page, with WCAG 2.2 AA as the target, using the accessible components of `@conduction/nextcloud-vue` (spec `nextcloud-vue/wcag-a11y-anchor`). Every action a mouse user can take SHALL have a keyboard path. Moving a card SHALL be possible without dragging, through the card's "Move to" control. A column header SHALL expose its title and its card count to assistive technology, and focus SHALL stay visible on every control.

#### Scenario: A keyboard user moves a card
- GIVEN a member on a project board who uses only the keyboard
- WHEN they tab to a card, open its "Move to" control and choose "Done"
- THEN the task moves to Done exactly as a drag would, and focus returns to the card

#### Scenario: A screen-reader user hears the column count
- GIVEN the column "In progress" holds three cards
- WHEN a screen-reader user moves into that column
- THEN they hear the column title and the count "3"

#### Scenario: An automated accessibility check on the main pages
- GIVEN the dashboard, a project board and a task page with seeded data
- WHEN axe-core runs on each page in the end-to-end suite
- THEN it reports no violation of a WCAG 2.1 A or AA rule

### Requirement: The look comes from the theme, not the code (REQ-PLB-002)

planninq's styles SHALL use Nextcloud theme variables for colours, fonts and radii, so that an NL Design System theme applied through thematiq restyles every page. Components SHALL NOT hard-code a colour value, except as the fallback inside a `var()` or as a colour a user picked for their own project or label.

#### Scenario: A municipality applies its house style
- GIVEN thematiq is configured with a municipality's design tokens
- WHEN a user opens the planninq dashboard and a project board
- THEN buttons, links, headings and focus rings use the municipality's colours

#### Scenario: A literal colour is added to a component
- GIVEN a component in `src/` with a literal hex colour in its styles
- WHEN the style lint runs
- THEN it reports the colour and the build of that change is red

### Requirement: Pages mirror in a right-to-left language (REQ-PLB-003)

planninq SHALL lay out every page with logical CSS properties (`inline-start`, `inline-end`, `margin-inline`, `inset-inline-start`), so that a page mirrors when Nextcloud sets a right-to-left direction. Positions computed in script, such as timeline bars, ticks and the today marker, SHALL be applied as an inline-start offset, not as `left`. Every user-facing string SHALL go through the translation function, so that a right-to-left catalogue can translate it.

#### Scenario: A user with Arabic as their language opens the timeline
- GIVEN a user whose Nextcloud language is Arabic
- WHEN they open a project timeline
- THEN time runs from right to left, bars start at their start date counted from the right edge, and the today marker sits on today

#### Scenario: A physical margin is added
- GIVEN a change that adds `margin-left` to a component in `src/`
- WHEN the style lint runs
- THEN it reports the property and names the logical one to use instead

### Requirement: The app runs on the customer's own server (REQ-PLB-004)

planninq SHALL install and run as a Nextcloud app on a server the customer operates, with OpenRegister as its only required app dependency. All its data SHALL live in the local OpenRegister register. It SHALL NOT need a service operated by Conduction or another vendor to start, store data or serve its pages, and the app store page SHALL make no network call until an administrator configures a registry.

#### Scenario: An installation without access to vendor services
- GIVEN a Nextcloud server with OpenRegister and no route to any Conduction service
- WHEN an administrator enables planninq and a user creates a project and a task
- THEN the app enables, its pages load, and the project and task are saved in the local register

### Requirement: Sign-in is the Nextcloud sign-in (REQ-PLB-005)

planninq SHALL have no login form, password store or session of its own. Every page and API route for signed-in users SHALL run in the Nextcloud session, so whichever sign-in the instance uses (local accounts, LDAP, SAML or OpenID Connect single sign-on) is the sign-in for planninq.

#### Scenario: A user signs in through the organisation's identity provider
- GIVEN an instance where users sign in through SAML or OpenID Connect
- WHEN a signed-in user opens planninq
- THEN planninq opens without asking for credentials and acts as that user

### Requirement: The second factor guards every planninq route (REQ-PLB-006)

planninq SHALL NOT offer a route that a user can reach with a password alone when the instance enforces a second factor. Every route SHALL declare its access with a Nextcloud attribute. Routes for signed-in users SHALL rely on the Nextcloud session, which a two-factor provider completes before any app page loads. A route reachable without a session SHALL be marked `#[PublicPage]` and check its own token.

#### Scenario: Two-factor sign-in is enforced
- GIVEN an administrator enforces an authenticator app as second factor
- WHEN a user who entered only their password requests a planninq page or API route
- THEN Nextcloud asks for the second factor and planninq serves nothing until it is given

#### Scenario: A route without an access attribute
- GIVEN a controller method registered in `appinfo/routes.php` with no Nextcloud access attribute
- WHEN the route-auth gate runs
- THEN the gate fails on that method

### Requirement: Security risks show in the Nextcloud administration overview (REQ-PLB-007)

planninq SHALL NOT keep a separate security dashboard. An administrator SHALL see the risks of the installation planninq runs in, with suggested fixes, in Nextcloud Administration, Overview ("Security and setup warnings"), and SHALL reach planninq's own settings in Nextcloud Administration through `lib/Settings/AdminSettings.php`.

#### Scenario: An administrator reviews the installation
- GIVEN an instance with planninq enabled and a setup warning raised by Nextcloud
- WHEN an administrator opens Administration, Overview
- THEN the warning and its suggested fix are listed there, and planninq's settings are one section away in the same Administration area

### Requirement: Outgoing requests for planninq data go through OpenRegister's guard (REQ-PLB-008)

planninq's own code SHALL NOT open outgoing HTTP connections. Webhooks and link lookups fired for planninq projects, tasks and other objects SHALL be sent by OpenRegister, whose outbound guard refuses targets that resolve to loopback, private, link-local or cloud metadata addresses and re-checks every redirect (spec `openregister/notificatie-engine`, requirement "The system MUST support multiple notification channels").

#### Scenario: A webhook points at the internal network
- GIVEN an administrator registers a webhook for planninq task events with the target `http://10.0.0.5/hook`
- WHEN a task is moved on a board
- THEN OpenRegister refuses to deliver the webhook and logs the blocked target, and no request reaches `10.0.0.5`

#### Scenario: An HTTP client is added to planninq
- GIVEN a change that injects `OCP\Http\Client\IClientService` into a class under `lib/`
- WHEN the platform unit test runs
- THEN it fails and names the class
