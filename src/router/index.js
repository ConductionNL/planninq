import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import Dashboard from '../views/Dashboard.vue'

export default createRouter({
	// vue-router 4 replaces `mode: 'history'` + `base` with a history object
	// that carries the base itself. The router is installed per app instance
	// (`app.use(router)` in main.js), so there is no `Vue.use(Router)` any more.
	history: createWebHistory(generateUrl('/apps/planix')),
	routes: [
		{ path: '/', name: 'Dashboard', component: Dashboard },
		// Settings is a modal dialog (UserSettings.vue), not a route — see ADR-004
		{
			path: '/projects',
			name: 'Projects',
			component: () => import('../views/ProjectList.vue'),
		},
		{
			path: '/projects/:id',
			name: 'ProjectBoard',
			component: () => import('../views/ProjectBoard.vue'),
		},
		{
			path: '/projects/:id/backlog',
			name: 'ProjectBacklog',
			component: () => import('../views/ProjectBacklog.vue'),
		},
		{
			path: '/projects/:id/timeline',
			name: 'ProjectTimeline',
			component: () => import('../views/ProjectTimeline.vue'),
		},
		{
			path: '/projects/:id/tasks/:taskId',
			name: 'TaskDetail',
			component: () => import('../views/TaskDetail.vue'),
		},
		{
			path: '/timesheet',
			name: 'Timesheet',
			component: () => import('../views/Timesheet.vue'),
		},
		{
			path: '/boards',
			name: 'Boards',
			component: () => import('../views/Boards.vue'),
		},
		{
			path: '/portfolio',
			name: 'Portfolio',
			component: () => import('../views/Portfolio.vue'),
		},
		// vue-router 4 REMOVED the bare `path: '*'` wildcard. It does not throw —
		// the route simply never matches, so an unknown URL renders the app shell
		// with an empty <main> and nothing in the console. This is the v4 spelling.
		{ path: '/:pathMatch(.*)*', redirect: '/' },
	],
})
