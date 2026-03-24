import React from 'react';
import clsx from 'clsx';
import styles from './styles.module.css';

const FeatureList = [
  {
    title: 'Kanban Boards',
    description: (
      <>
        Flow-based kanban with configurable columns, WIP limits, drag-and-drop cards,
        and a structured backlog. Built for dev and IT teams who need flow, not sprints.
      </>
    ),
  },
  {
    title: 'Time Tracking',
    description: (
      <>
        Log time per task, set estimates, and view personal timesheets.
        Track actual vs estimated effort across projects — a gap that Nextcloud Deck and most
        kanban tools leave open.
      </>
    ),
  },
  {
    title: 'Nextcloud-Native',
    description: (
      <>
        Deep integration with Nextcloud Files, Calendar, Talk, and Notifications.
        Sovereign, self-hosted, WCAG AA accessible. Bridges natively with Procest
        for case-to-task workflows.
      </>
    ),
  },
];

function Feature({title, description}) {
  return (
    <div className={clsx('col col--4')}>
      <div className="text--center padding-horiz--md">
        <h3>{title}</h3>
        <p>{description}</p>
      </div>
    </div>
  );
}

export default function HomepageFeatures() {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}
