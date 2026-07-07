import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import Dashboard from '../views/Dashboard.vue'

Vue.use(Router)

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/planix'),
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
		{ path: '*', redirect: '/' },
	],
})
