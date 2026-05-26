/**
 * Planix landing page.
 *
 * Composes the brand <DetailHero> + <WidgetShelf> from
 * @conduction/docusaurus-preset/components, mirroring the OpenRegister
 * landing page at openregister.conduction.nl (docs/src/pages/index.js)
 * and the decidesk landing.
 *
 * Written as .js (not .mdx) because the docs site has the docs plugin
 * pointed at `path: './'`, and an MDX file in src/pages/ trips the
 * MDX-ESM parser even with the docs plugin's `src/**` exclude — likely
 * a quirk of how mdx-loader's micromark stack reuses parser state
 * across files in this Docusaurus 3 + this preset combination.
 * Authoring the page in JSX keeps the same component composition.
 *
 * Planix isn't a registered <AppMock> variant yet, so the hero
 * illustration is a small inline three-column Kanban mock built from
 * brand tokens — same level of abstraction as the AppMock variants.
 */

import React from 'react';
import Layout from '@theme/Layout';
import {
  DetailHero,
  WidgetShelf,
} from '@conduction/docusaurus-preset/components';

/* 3-column Kanban glyph — same shape as planix/img/app.svg (Material
   Design Icons "view-column" read as a Kanban board), redrawn as a
   stroke line-icon because the preset's `.titleIcon svg` rule forces
   `fill: none; stroke: currentColor`. */
const PLANIX_ICON = (
  <svg viewBox="0 0 24 24">
    <rect x="3" y="4" width="5" height="16" rx="1" />
    <rect x="9.5" y="4" width="5" height="16" rx="1" />
    <rect x="16" y="4" width="5" height="16" rx="1" />
    <line x1="3" y1="9" x2="8" y2="9" />
    <line x1="9.5" y1="9" x2="14.5" y2="9" />
    <line x1="16" y1="9" x2="21" y2="9" />
  </svg>
);

const TAGLINE = (
  <>
    Planix runs the board: projects, tasks, Kanban columns with WIP
    limits, a real backlog, and per-task time entries — a focused
    workflow tool for dev and IT teams, built straight into Nextcloud
    on top of OpenRegister.
  </>
);

/* Small inline three-column Kanban illustration for the hero right
   column. Cards are token-painted blocks; the centre column shows a
   WIP cap. Recognisable as a Kanban board, never literal. */
function KanbanMock() {
  const columns = [
    {
      title: 'Backlog',
      tint: 'var(--c-cobalt-50)',
      cards: [
        'var(--c-cobalt-200)',
        'var(--c-lavender-300)',
        'var(--c-cobalt-200)',
        'var(--c-mint-300)',
      ],
    },
    {
      title: 'In progress',
      tint: 'var(--c-cobalt-50)',
      wip: '3 / 3',
      cards: ['var(--c-cobalt-300)', 'var(--c-forest-300)', 'var(--c-cobalt-300)'],
    },
    {
      title: 'Done',
      tint: 'var(--c-cobalt-50)',
      cards: ['var(--c-mint-500)', 'var(--c-mint-500)'],
    },
  ];
  return (
    <div
      style={{
        display: 'flex',
        gap: 10,
        padding: 14,
        background: 'var(--c-cobalt-50)',
        borderRadius: 8,
        border: '1px solid var(--c-cobalt-100)',
        maxWidth: 360,
      }}
    >
      {columns.map((col, ci) => (
        <div
          key={ci}
          style={{
            flex: 1,
            display: 'flex',
            flexDirection: 'column',
            gap: 6,
            background: 'var(--c-white, #fff)',
            borderRadius: 6,
            padding: 8,
          }}
        >
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              gap: 4,
            }}
          >
            <div
              style={{
                fontFamily: 'var(--conduction-typography-font-family-code)',
                fontSize: 8,
                fontWeight: 700,
                letterSpacing: '0.05em',
                textTransform: 'uppercase',
                color: 'var(--c-cobalt-700)',
              }}
            >
              {col.title}
            </div>
            {col.wip && (
              <div
                style={{
                  fontFamily: 'var(--conduction-typography-font-family-code)',
                  fontSize: 7,
                  fontWeight: 700,
                  color: 'var(--c-orange-knvb)',
                }}
              >
                {col.wip}
              </div>
            )}
          </div>
          {col.cards.map((tone, i) => (
            <div
              key={i}
              style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 3,
                padding: '6px 6px',
                borderRadius: 3,
                background: 'var(--c-cobalt-50)',
                borderLeft: `3px solid ${tone}`,
              }}
            >
              <div
                style={{
                  height: 4,
                  width: '80%',
                  background: 'var(--c-cobalt-700)',
                  borderRadius: 1,
                }}
              />
              <div
                style={{
                  height: 3,
                  width: '55%',
                  background: 'var(--c-cobalt-200)',
                  borderRadius: 1,
                }}
              />
            </div>
          ))}
        </div>
      ))}
    </div>
  );
}

function ActiveBoardsPanel() {
  const rows = [
    { tone: 'var(--c-cobalt-300)', meta: '12 cards' },
    { tone: 'var(--c-lavender-300)', meta: '7 cards' },
    { tone: 'var(--c-mint-500)', meta: '24 cards' },
    { tone: 'var(--c-forest-300)', meta: '3 cards' },
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {rows.map((row, i) => (
        <div
          key={i}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 8,
            padding: '4px 0',
            borderBottom:
              i < rows.length - 1 ? '1px solid var(--c-cobalt-50)' : 'none',
          }}
        >
          <span
            style={{
              width: 12,
              height: 14,
              clipPath: 'var(--hex-pointy-top)',
              background: row.tone,
              flexShrink: 0,
            }}
          />
          <div
            style={{
              flex: 1,
              display: 'flex',
              flexDirection: 'column',
              gap: 2,
            }}
          >
            <div
              style={{
                height: 4,
                width: '70%',
                background: 'var(--c-cobalt-700)',
                borderRadius: 1,
              }}
            />
            <div
              style={{
                height: 3,
                width: '50%',
                background: 'var(--c-cobalt-200)',
                borderRadius: 1,
              }}
            />
          </div>
          <div
            style={{
              fontFamily: 'var(--conduction-typography-font-family-code)',
              fontSize: 8,
              letterSpacing: '0.05em',
              textTransform: 'uppercase',
              color: 'var(--c-cobalt-500)',
            }}
          >
            {row.meta}
          </div>
        </div>
      ))}
    </div>
  );
}

function MyTasksPanel() {
  const rows = [
    { tone: 'var(--c-orange-knvb)', due: 'overdue' },
    { tone: 'var(--c-cobalt-300)', due: 'today' },
    { tone: 'var(--c-lavender-300)', due: 'Fri' },
    { tone: 'var(--c-mint-500)', due: 'next wk' },
    { tone: 'var(--c-forest-300)', due: 'next wk' },
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {rows.map((row, i) => (
        <div
          key={i}
          style={{ display: 'flex', alignItems: 'center', gap: 8 }}
        >
          <span
            style={{
              width: 18,
              height: 18,
              borderRadius: 2,
              background: row.tone,
              flexShrink: 0,
            }}
          />
          <div
            style={{
              flex: 1,
              display: 'flex',
              flexDirection: 'column',
              gap: 3,
            }}
          >
            <div
              style={{
                height: 4,
                width: '70%',
                background: 'var(--c-cobalt-700)',
                borderRadius: 1,
              }}
            />
            <div
              style={{
                height: 3,
                width: '50%',
                background: 'var(--c-cobalt-200)',
                borderRadius: 1,
              }}
            />
          </div>
          <div
            style={{
              fontFamily: 'var(--conduction-typography-font-family-code)',
              fontSize: 8,
              letterSpacing: '0.05em',
              textTransform: 'uppercase',
              color: 'var(--c-cobalt-500)',
            }}
          >
            {row.due}
          </div>
        </div>
      ))}
    </div>
  );
}

function BacklogPanel() {
  const rows = [
    { tone: 'var(--c-cobalt-300)', label: 'P1', w: '85%' },
    { tone: 'var(--c-lavender-300)', label: 'P2', w: '70%' },
    { tone: 'var(--c-mint-500)', label: 'P2', w: '60%' },
    { tone: 'var(--c-forest-300)', label: 'P3', w: '45%' },
    { tone: 'var(--c-cobalt-200)', label: 'P3', w: '35%' },
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      {rows.map((row, i) => (
        <div
          key={i}
          style={{ display: 'flex', alignItems: 'center', gap: 8 }}
        >
          <span
            style={{
              width: 14,
              height: 16,
              clipPath: 'var(--hex-pointy-top)',
              background: row.tone,
              flexShrink: 0,
            }}
          />
          <div
            style={{
              fontFamily: 'var(--conduction-typography-font-family-code)',
              fontSize: 8,
              fontWeight: 700,
              letterSpacing: '0.05em',
              color: 'var(--c-cobalt-700)',
              minWidth: 20,
            }}
          >
            {row.label}
          </div>
          <div
            style={{
              flex: 1,
              height: 6,
              background: 'var(--c-cobalt-50)',
              borderRadius: 1,
              overflow: 'hidden',
            }}
          >
            <div
              style={{
                height: '100%',
                width: row.w,
                background: 'var(--c-cobalt-300)',
                borderRadius: 1,
              }}
            />
          </div>
        </div>
      ))}
    </div>
  );
}

const WIDGETS = [
  {
    title: 'Active boards',
    desc: 'Your team’s Kanban boards across every project — column counts, WIP status, and the board you opened last, all on the dashboard you already use.',
    panel: <ActiveBoardsPanel />,
  },
  {
    title: 'My tasks',
    desc: 'Every task assigned to you, sorted by due date: overdue, today, this week. Priorities, labels, subtasks, and a clear path from backlog to done.',
    panel: <MyTasksPanel />,
  },
  {
    title: 'Backlog',
    desc: 'A real backlog — not just a board column. Rank by priority, groom, estimate, and pull the next item into flow when a WIP slot opens up.',
    panel: <BacklogPanel />,
  },
];

export default function Home() {
  return (
    <Layout
      title="Planix, kanban project management for Nextcloud teams"
      description="Kanban project management for Nextcloud dev and IT teams. Projects, tasks, WIP-limited columns, a real backlog, and per-task time tracking."
    >
      <main className="marketing-page">
        <DetailHero
          background="cobalt"
          appId="planix"
          /* status + version dropped — preset 2.10+ auto-derives from appinfo/info.xml */
          locales="EN"
          title="Planix"
          tagline={TAGLINE}
          primaryCta={{
            label: 'Install from app store',
            href: 'https://apps.nextcloud.com/apps/planix',
            tone: 'orange',
          }}
          secondaryCta={{ label: 'Read the docs', href: '/docs/intro' }}
          tertiaryCta={{
            label: 'View on GitHub',
            href: 'https://github.com/ConductionNL/planix',
          }}
          iconColor="var(--c-orange-knvb)"
          icon={PLANIX_ICON}
          illustration={<KanbanMock />}
        />

        <WidgetShelf
          eyebrow="Widgets we ship"
          title="The board follows you to the dashboard."
          lede="Install Planix and the home screen surfaces your active boards, the tasks due against you, and the backlog still waiting to be pulled into flow."
          widgets={WIDGETS}
        />
      </main>
    </Layout>
  );
}
